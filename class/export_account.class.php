<?php
/**
 * Class pour export des comptes collaborateurs en PDF et Excel
 * Fichier: /htdocs/custom/revenuesharing/class/export_account.class.php
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

class ExportAccount
{
    public $db;
    public $collaborator_id;
    public $collaborator_data;
    public $transactions;
    public $balance_info;
    public $ca_info;
    
    public function __construct($db)
    {
        $this->db = $db;
        
        // Charger les informations de la société
        global $mysoc, $conf;
        if (empty($mysoc)) {
            $mysoc = new Societe($db);
            $mysoc->setMysoc($conf);
        }
    }
    
    /**
     * Charge les données du collaborateur
     */
    public function loadCollaboratorData($collaborator_id)
    {
        $this->collaborator_id = (int) $collaborator_id;
        
        // Récupérer les infos du collaborateur
        $sql_collab = "SELECT c.*, u.firstname, u.lastname, u.email 
                      FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                      LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
                      WHERE c.rowid = ".$this->collaborator_id;
        
        $resql_collab = $this->db->query($sql_collab);
        if (!$resql_collab || $this->db->num_rows($resql_collab) == 0) {
            return false;
        }
        
        $this->collaborator_data = $this->db->fetch_object($resql_collab);
        $this->db->free($resql_collab);
        
        // Récupérer le solde actuel
        $sql_balance = "SELECT 
            COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_credits,
            COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_debits,
            COALESCE(SUM(amount), 0) as current_balance,
            COUNT(*) as nb_transactions,
            MAX(transaction_date) as last_transaction_date
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
            WHERE fk_collaborator = ".$this->collaborator_id." AND status = 1";
        
        $resql_balance = $this->db->query($sql_balance);
        $this->balance_info = $this->db->fetch_object($resql_balance);
        $this->db->free($resql_balance);
        
        return true;
    }
    
    /**
     * Charge les données de chiffre d'affaires du collaborateur
     */
    public function loadCAData($filter_year = 0)
    {
        $sql_ca = "SELECT 
            COALESCE(SUM(f.total_ht), 0) as ca_total_ht,
            COALESCE(SUM(f.total_ttc), 0) as ca_total_ttc,
            COALESCE(SUM(c.collaborator_amount_ht), 0) as collaborator_total_ht,
            COALESCE(SUM(c.studio_amount_ht), 0) as studio_total_ht,
            AVG(c.collaborator_percentage) as avg_percentage,
            COUNT(DISTINCT f.rowid) as nb_factures_clients,
            COUNT(DISTINCT c.rowid) as nb_contrats
            FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = c.fk_facture AND f.fk_statut IN (1,2)
            WHERE c.fk_collaborator = ".$this->collaborator_id." AND c.status = 1";

        if ($filter_year > 0) {
            $sql_ca .= " AND YEAR(f.datef) = ".(int)$filter_year;
        }
        
        $resql_ca = $this->db->query($sql_ca);
        if ($resql_ca) {
            $this->ca_info = $this->db->fetch_object($resql_ca);
            $this->db->free($resql_ca);
            return true;
        }
        return false;
    }
    
    /**
     * Charge les transactions avec filtres optionnels (incluant les salaires payés)
     */
    public function loadTransactions($filter_type = '', $filter_year = 0, $show_previsionnel = true, $limit = 500)
    {
        // Utiliser UNION pour combiner transactions et salaires
        $sql_trans = "SELECT * FROM (
            SELECT
                t.rowid,
                t.fk_collaborator,
                t.transaction_type,
                t.amount,
                t.description,
                t.transaction_date,
                t.date_creation,
                t.status,
                t.fk_contract,
                t.fk_facture,
                t.fk_facture_fourn,
                t.fk_user_creat,
                t.note_private,
                c.ref as contract_ref,
                f.ref as facture_ref,
                f.datef as facture_date,
                ff.ref as facture_fourn_ref,
                ff.datef as facture_fourn_date,
                u.login as user_login,
                COALESCE(f.datef, ff.datef, t.transaction_date) as display_date
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
            LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
            LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = t.fk_user_creat
            WHERE t.fk_collaborator = ".$this->collaborator_id." AND t.status = 1"
            .($filter_type && $filter_type !== 'salary' ? " AND t.transaction_type = '".$this->db->escape($filter_type)."'" : "")
            .($filter_type === 'salary' ? " AND 1=0" : "")
            .($filter_year ? " AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) = ".(int)$filter_year : "")
            .(!$show_previsionnel ? " AND (c.type_contrat IS NULL OR c.type_contrat != 'previsionnel')" : "")."

            UNION ALL

            SELECT
                -sd.rowid as rowid,
                sd.fk_collaborator,
                'salary' as transaction_type,
                -sd.solde_utilise as amount,
                CONCAT('Salaire ',
                    CASE sd.declaration_month
                        WHEN 1 THEN 'Janvier' WHEN 2 THEN 'Février' WHEN 3 THEN 'Mars'
                        WHEN 4 THEN 'Avril' WHEN 5 THEN 'Mai' WHEN 6 THEN 'Juin'
                        WHEN 7 THEN 'Juillet' WHEN 8 THEN 'Août' WHEN 9 THEN 'Septembre'
                        WHEN 10 THEN 'Octobre' WHEN 11 THEN 'Novembre' WHEN 12 THEN 'Décembre'
                    END,
                    ' ', sd.declaration_year,
                    ' (', sd.total_days, ' jours)'
                ) as description,
                DATE(sd.date_modification) as transaction_date,
                sd.date_creation,
                sd.status,
                NULL as fk_contract,
                NULL as fk_facture,
                NULL as fk_facture_fourn,
                sd.fk_user_creat,
                sd.note_private,
                NULL as contract_ref,
                NULL as facture_ref,
                NULL as facture_date,
                NULL as facture_fourn_ref,
                NULL as facture_fourn_date,
                u.login as user_login,
                DATE(sd.date_modification) as display_date
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
            LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = sd.fk_user_creat
            WHERE sd.fk_collaborator = ".$this->collaborator_id."
            AND sd.status = 3"
            .($filter_year ? " AND sd.declaration_year = ".(int)$filter_year : "")
            .($filter_type && $filter_type !== 'salary' ? " AND 1=0" : "")."
        ) AS combined_transactions
        ORDER BY display_date DESC, date_creation DESC
        LIMIT ".(int)$limit;

        $resql_trans = $this->db->query($sql_trans);
        if ($resql_trans) {
            $this->transactions = array();
            while ($trans = $this->db->fetch_object($resql_trans)) {
                $this->transactions[] = $trans;
            }
            $this->db->free($resql_trans);
            return true;
        }
        return false;
    }
    
    /**
     * Export en PDF
     */
    public function exportToPDF($filter_type = '', $filter_year = 0, $show_previsionnel = false)
    {
        global $conf, $langs;
        
        if (!$this->loadCollaboratorData($this->collaborator_id)) {
            return false;
        }
        
        if (!$this->loadTransactions($filter_type, $filter_year, $show_previsionnel)) {
            return false;
        }
        
        // Charger les données de chiffre d'affaires
        $this->loadCAData($filter_year);
        
        // Calculer les statistiques avec solde cumulé si filtré par année
        $filtered_credits = 0;
        $filtered_debits = 0; 
        $filtered_balance = 0;
        $previous_balance = 0;
        $filtered_count = count($this->transactions);
        
        // Si filtre par année, calculer le solde reporté (transactions + salaires)
        if ($filter_year > 0) {
            // Transactions classiques
            $sql_previous = "SELECT COALESCE(SUM(t.amount), 0) as previous_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                WHERE t.fk_collaborator = ".$this->collaborator_id." AND t.status = 1
                AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) < ".$filter_year;

            $resql_previous = $this->db->query($sql_previous);
            if ($resql_previous) {
                $previous_balance = $this->db->fetch_object($resql_previous)->previous_balance;
                $this->db->free($resql_previous);
            }

            // Soustraire les salaires payés des années précédentes
            $sql_previous_salaries = "SELECT COALESCE(SUM(solde_utilise), 0) as previous_salaries
                FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                WHERE fk_collaborator = ".$this->collaborator_id." AND status = 3
                AND declaration_year < ".$filter_year;

            $resql_previous_salaries = $this->db->query($sql_previous_salaries);
            if ($resql_previous_salaries) {
                $previous_salaries = $this->db->fetch_object($resql_previous_salaries)->previous_salaries;
                $previous_balance -= $previous_salaries;
                $this->db->free($resql_previous_salaries);
            }
        }
        
        foreach ($this->transactions as $trans) {
            if ($trans->amount > 0) {
                $filtered_credits += $trans->amount;
            } else {
                $filtered_debits += abs($trans->amount);
            }
            $filtered_balance += $trans->amount;
        }
        
        // Solde cumulé = solde reporté + mouvements de l'année
        $cumulative_balance = $previous_balance + $filtered_balance;
        
        // Utiliser la classe PDF de Dolibarr
        require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
        
        // Créer le PDF avec TCPDF (inclus dans Dolibarr)
        $pdf = pdf_getInstance();
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        
        // En-tête avec logo
        $this->addPDFHeader($pdf);
        
        // Titre principal
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'RELEVÉ DE COMPTE COLLABORATEUR', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Infos collaborateur
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Collaborateur: '.$this->collaborator_data->label, 0, 1);
        
        if ($this->collaborator_data->firstname && $this->collaborator_data->lastname) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, 'Nom complet: '.$this->collaborator_data->firstname.' '.$this->collaborator_data->lastname, 0, 1);
        }
        
        if ($this->collaborator_data->email) {
            $pdf->Cell(0, 6, 'Email: '.$this->collaborator_data->email, 0, 1);
        }
        
        $pdf->Cell(0, 6, 'Date: '.dol_print_date(dol_now(), 'daytext'), 0, 1);
        $pdf->Ln(5);
        
        // Section Chiffre d'Affaires & Répartition
        if ($this->ca_info && $this->ca_info->ca_total_ht > 0) {
            $pdf->SetFont('helvetica', 'B', 12);
            $ca_title = 'CHIFFRE D\'AFFAIRES & RÉPARTITION';
            if ($filter_year > 0) {
                $ca_title .= ' ('.$filter_year.')';
            }
            $pdf->Cell(0, 8, $ca_title, 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            
            // Chiffre d'affaires global
            $pdf->Cell(60, 6, 'CA HT:', 0, 0);
            $pdf->Cell(0, 6, price($this->ca_info->ca_total_ht).' €', 0, 1);
            
            $pdf->Cell(60, 6, 'Nombre de factures:', 0, 0);
            $pdf->Cell(0, 6, $this->ca_info->nb_factures_clients, 0, 1);
            
            // Répartition des montants
            if ($this->ca_info->collaborator_total_ht > 0 || $this->ca_info->studio_total_ht > 0) {
                $pdf->Ln(3);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Répartition des montants:', 0, 1);
                $pdf->SetFont('helvetica', '', 10);
                
                $pdf->Cell(60, 6, 'Part Collaborateur:', 0, 0);
                $pdf->Cell(0, 6, price($this->ca_info->collaborator_total_ht).' €', 0, 1);
                
                $pdf->Cell(60, 6, 'Part Structure:', 0, 0);
                $pdf->Cell(0, 6, price($this->ca_info->studio_total_ht).' €', 0, 1);
                
                if ($this->ca_info->avg_percentage > 0) {
                    $pdf->Cell(60, 6, 'Pourcentage moyen:', 0, 0);
                    $pdf->Cell(0, 6, number_format($this->ca_info->avg_percentage, 1).'%', 0, 1);
                }
            }
            
            $pdf->Cell(60, 6, 'Nombre de contrats:', 0, 0);
            $pdf->Cell(0, 6, $this->ca_info->nb_contrats, 0, 1);
            
        }

        $pdf->Ln(5);

        // === RÉSUMÉ FINANCIER ===
        $pdf->SetX(10);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $resume_title = 'RÉSUMÉ FINANCIER';
        if ($filter_type || $filter_year) {
            $resume_title .= ' (FILTRÉ)';
        }
        $pdf->Cell(0, 8, $resume_title, 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        // Utiliser les données filtrées si des filtres sont appliqués, sinon les globales
        $display_balance = ($filter_type || $filter_year) ? $cumulative_balance : $this->balance_info->current_balance;
        $display_credits = ($filter_type || $filter_year) ? $filtered_credits : $this->balance_info->total_credits;
        $display_debits = ($filter_type || $filter_year) ? $filtered_debits : $this->balance_info->total_debits;
        $display_count = ($filter_type || $filter_year) ? $filtered_count : $this->balance_info->nb_transactions;

        // Si filtré par année, afficher le solde reporté d'abord
        if ($filter_year > 0) {
            $pdf->Cell(60, 6, 'Solde reporté:', 0, 0);
            $pdf->Cell(0, 6, price($previous_balance).' €', 0, 1);
        }

        // Afficher les mouvements de l'année ou totaux
        if ($filter_year > 0) {
            $pdf->Cell(60, 6, 'Crédits '.$filter_year.':', 0, 0);
            $pdf->Cell(0, 6, price($display_credits).' €', 0, 1);

            $pdf->Cell(60, 6, 'Débits '.$filter_year.':', 0, 0);
            $pdf->Cell(0, 6, price($display_debits).' €', 0, 1);
        } else {
            $pdf->Cell(60, 6, 'Total crédits:', 0, 0);
            $pdf->Cell(0, 6, price($display_credits).' €', 0, 1);

            $pdf->Cell(60, 6, 'Total débits:', 0, 0);
            $pdf->Cell(0, 6, price($display_debits).' €', 0, 1);
        }

        $pdf->Cell(60, 6, 'Nb transactions:', 0, 0);
        $pdf->Cell(0, 6, $display_count, 0, 1);

        // Solde cumulé en dernier, mis en évidence
        $pdf->Cell(60, 6, 'Solde cumulé:', 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, price($display_balance).' €', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        // Indication sur l'inclusion/exclusion des prévisionnels dans le solde
        if ($show_previsionnel) {
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(0, 124, 186); // Couleur bleue #007cba
            $pdf->Cell(0, 4, 'Inclut les contrats prévisionnels', 0, 1);
        } else {
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(102, 102, 102); // Couleur grise #666
            $pdf->Cell(0, 4, 'Contrats réels uniquement', 0, 1);
        }
        $pdf->SetTextColor(0, 0, 0); // Retour au noir
        $pdf->SetFont('helvetica', '', 10);

        $pdf->Ln(5);
        
        // Tableau des transactions
        if (!empty($this->transactions)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'HISTORIQUE DES TRANSACTIONS', 0, 1);
            
            // En-têtes du tableau
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(25, 6, 'Date', 1, 0, 'C');
            $pdf->Cell(35, 6, 'Type', 1, 0, 'C');
            $pdf->Cell(70, 6, 'Description', 1, 0, 'C');
            $pdf->Cell(25, 6, 'Montant', 1, 0, 'C');
            $pdf->Cell(35, 6, 'Référence', 1, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 8);
            
            $type_labels = array(
                'contract' => 'Contrats',
                'commission' => 'Commissions',
                'bonus' => 'Bonus',
                'interest' => 'Intéressements',
                'advance' => 'Avances',
                'fee' => 'Frais',
                'refund' => 'Remboursements',
                'adjustment' => 'Ajustements',
                'salary' => 'Salaires',
                'other_credit' => 'Autres crédits',
                'other_debit' => 'Autres débits'
            );

            $month_names = array(
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            );

            $previous_month = null;

            foreach ($this->transactions as $trans) {
                // Détecter le changement de mois
                $trans_date = $this->db->jdate($trans->display_date);
                $current_month = date('Y-m', $trans_date);
                $current_month_name = $month_names[(int)date('n', $trans_date)] . ' ' . date('Y', $trans_date);

                // Afficher le séparateur de mois si changement détecté
                if ($previous_month !== null && $previous_month !== $current_month) {
                    $pdf->SetFont('helvetica', 'B', 9);
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->Cell(190, 7, $current_month_name, 1, 1, 'L', true);
                    $pdf->SetFont('helvetica', '', 8);
                }
                $previous_month = $current_month;

                // Vérifier si on doit ajouter une nouvelle page
                if ($pdf->GetY() > 250) {
                    $pdf->AddPage();
                    // Répéter les en-têtes
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->Cell(25, 6, 'Date', 1, 0, 'C');
                    $pdf->Cell(35, 6, 'Type', 1, 0, 'C');
                    $pdf->Cell(70, 6, 'Description', 1, 0, 'C');
                    $pdf->Cell(25, 6, 'Montant', 1, 0, 'C');
                    $pdf->Cell(35, 6, 'Référence', 1, 1, 'C');
                    $pdf->SetFont('helvetica', '', 8);
                }

                $pdf->Cell(25, 6, dol_print_date($this->db->jdate($trans->display_date), 'day'), 1, 0, 'C');
                $pdf->Cell(35, 6, $type_labels[$trans->transaction_type] ?? $trans->transaction_type, 1, 0, 'L');
                
                // Limiter la description à 40 caractères
                $description = strlen($trans->description) > 40 ? substr($trans->description, 0, 37).'...' : $trans->description;
                $pdf->Cell(70, 6, $description, 1, 0, 'L');
                
                $pdf->Cell(25, 6, price($trans->amount), 1, 0, 'R');
                
                $reference = '';
                if ($trans->contract_ref) $reference = $trans->contract_ref;
                elseif ($trans->facture_ref) $reference = $trans->facture_ref;
                elseif ($trans->facture_fourn_ref) $reference = $trans->facture_fourn_ref;
                
                $pdf->Cell(35, 6, $reference, 1, 1, 'C');
            }
        }
        
        // Pied de page
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 6, 'Document généré le '.dol_print_date(dol_now(), 'dayhourtext'), 0, 1, 'C');
        $pdf->Cell(0, 6, 'Module Revenue Sharing - Dolibarr', 0, 1, 'C');
        
        // Nom du fichier
        $filename = 'releve_compte_'.$this->collaborator_data->label.'_'.dol_print_date(dol_now(), 'dayrfc').'.pdf';
        $filename = dol_sanitizeFileName($filename);
        
        // Output du PDF
        $pdf->Output($filename, 'D');
        exit(); // Arrêter l'exécution après l'envoi du PDF
    }
    
    /**
     * Export en Excel (CSV)
     */
    public function exportToExcel($filter_type = '', $filter_year = 0, $show_previsionnel = false)
    {
        if (!$this->loadCollaboratorData($this->collaborator_id)) {
            return false;
        }
        
        if (!$this->loadTransactions($filter_type, $filter_year, $show_previsionnel)) {
            return false;
        }
        
        // Charger les données de chiffre d'affaires
        $this->loadCAData($filter_year);
        
        // Calculer les statistiques avec solde cumulé si filtré par année
        $filtered_credits = 0;
        $filtered_debits = 0; 
        $filtered_balance = 0;
        $previous_balance = 0;
        $filtered_count = count($this->transactions);
        
        // Si filtre par année, calculer le solde reporté (transactions + salaires)
        if ($filter_year > 0) {
            // Transactions classiques
            $sql_previous = "SELECT COALESCE(SUM(t.amount), 0) as previous_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                WHERE t.fk_collaborator = ".$this->collaborator_id." AND t.status = 1
                AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) < ".$filter_year;

            $resql_previous = $this->db->query($sql_previous);
            if ($resql_previous) {
                $previous_balance = $this->db->fetch_object($resql_previous)->previous_balance;
                $this->db->free($resql_previous);
            }

            // Soustraire les salaires payés des années précédentes
            $sql_previous_salaries = "SELECT COALESCE(SUM(solde_utilise), 0) as previous_salaries
                FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                WHERE fk_collaborator = ".$this->collaborator_id." AND status = 3
                AND declaration_year < ".$filter_year;

            $resql_previous_salaries = $this->db->query($sql_previous_salaries);
            if ($resql_previous_salaries) {
                $previous_salaries = $this->db->fetch_object($resql_previous_salaries)->previous_salaries;
                $previous_balance -= $previous_salaries;
                $this->db->free($resql_previous_salaries);
            }
        }
        
        foreach ($this->transactions as $trans) {
            if ($trans->amount > 0) {
                $filtered_credits += $trans->amount;
            } else {
                $filtered_debits += abs($trans->amount);
            }
            $filtered_balance += $trans->amount;
        }
        
        // Solde cumulé = solde reporté + mouvements de l'année
        $cumulative_balance = $previous_balance + $filtered_balance;
        
        $filename = 'releve_compte_'.$this->collaborator_data->label.'_'.dol_print_date(dol_now(), 'dayrfc').'.csv';
        $filename = dol_sanitizeFileName($filename);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // BOM pour UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        // En-tête du fichier
        fputcsv($output, array('RELEVÉ DE COMPTE COLLABORATEUR'), ';');
        fputcsv($output, array(''), ';');
        fputcsv($output, array('Collaborateur', $this->collaborator_data->label), ';');
        
        if ($this->collaborator_data->firstname && $this->collaborator_data->lastname) {
            fputcsv($output, array('Nom complet', $this->collaborator_data->firstname.' '.$this->collaborator_data->lastname), ';');
        }
        
        if ($this->collaborator_data->email) {
            fputcsv($output, array('Email', $this->collaborator_data->email), ';');
        }
        
        fputcsv($output, array('Date d\'export', dol_print_date(dol_now(), 'daytext')), ';');
        fputcsv($output, array(''), ';');
        
        // Section Chiffre d'Affaires & Répartition
        if ($this->ca_info && $this->ca_info->ca_total_ht > 0) {
            $ca_title = 'CHIFFRE D\'AFFAIRES & RÉPARTITION';
            if ($filter_year > 0) {
                $ca_title .= ' ('.$filter_year.')';
            }
            fputcsv($output, array($ca_title), ';');
            
            fputcsv($output, array('CA HT', price($this->ca_info->ca_total_ht).' €'), ';');
            fputcsv($output, array('Nombre de factures', $this->ca_info->nb_factures_clients), ';');
            
            if ($this->ca_info->collaborator_total_ht > 0 || $this->ca_info->studio_total_ht > 0) {
                fputcsv($output, array('Part Collaborateur', price($this->ca_info->collaborator_total_ht).' €'), ';');
                fputcsv($output, array('Part Structure', price($this->ca_info->studio_total_ht).' €'), ';');
                if ($this->ca_info->avg_percentage > 0) {
                    fputcsv($output, array('Pourcentage moyen', number_format($this->ca_info->avg_percentage, 1).'%'), ';');
                }
            }
            
            fputcsv($output, array('Nombre de contrats', $this->ca_info->nb_contrats), ';');
            fputcsv($output, array(''), ';');
        }
        
        // Résumé financier (filtré ou global)
        $resume_title = 'RÉSUMÉ FINANCIER';
        if ($filter_type || $filter_year) {
            $resume_title .= ' (FILTRÉ)';
        }
        fputcsv($output, array($resume_title), ';');
        
        // Utiliser les données filtrées si des filtres sont appliqués, sinon les globales
        $display_balance = ($filter_type || $filter_year) ? $cumulative_balance : $this->balance_info->current_balance;
        $display_credits = ($filter_type || $filter_year) ? $filtered_credits : $this->balance_info->total_credits;
        $display_debits = ($filter_type || $filter_year) ? $filtered_debits : $this->balance_info->total_debits;
        $display_count = ($filter_type || $filter_year) ? $filtered_count : $this->balance_info->nb_transactions;
        
        // Si filtré par année, afficher le solde reporté d'abord
        if ($filter_year > 0) {
            fputcsv($output, array('Solde reporté', price($previous_balance).' €'), ';');
        }
        
        // Mouvements et détails
        if ($filter_year > 0) {
            fputcsv($output, array('Crédits '.$filter_year, price($display_credits).' €'), ';');
            fputcsv($output, array('Débits '.$filter_year, price($display_debits).' €'), ';');
        } else {
            fputcsv($output, array('Total crédits', price($display_credits).' €'), ';');
            fputcsv($output, array('Total débits', price($display_debits).' €'), ';');
        }
        
        fputcsv($output, array('Nombre de transactions', $display_count), ';');
        
        // Solde cumulé en dernier
        fputcsv($output, array('Solde cumulé', price($display_balance).' €'), ';');
        
        // Indication sur l'inclusion/exclusion des prévisionnels dans le solde
        if ($show_previsionnel) {
            fputcsv($output, array('Note', 'Inclut les contrats prévisionnels'), ';');
        } else {
            fputcsv($output, array('Note', 'Contrats réels uniquement'), ';');
        }
        
        fputcsv($output, array(''), ';');
        
        // En-têtes des transactions
        fputcsv($output, array('HISTORIQUE DES TRANSACTIONS'), ';');
        fputcsv($output, array('Date', 'Type', 'Description', 'Montant (€)', 'Note privée', 'Référence liée', 'Créé par'), ';');
        
        $type_labels = array(
            'contract' => 'Contrats',
            'commission' => 'Commissions', 
            'bonus' => 'Bonus',
            'interest' => 'Intéressements',
            'advance' => 'Avances',
            'fee' => 'Frais',
            'refund' => 'Remboursements',
            'adjustment' => 'Ajustements',
            'salary' => 'Salaires',
            'other_credit' => 'Autres crédits',
            'other_debit' => 'Autres débits'
        );
        
        // Données des transactions
        foreach ($this->transactions as $trans) {
            $reference = '';
            if ($trans->contract_ref) $reference = $trans->contract_ref;
            elseif ($trans->facture_ref) $reference = $trans->facture_ref;
            elseif ($trans->facture_fourn_ref) $reference = $trans->facture_fourn_ref;
            
            fputcsv($output, array(
                dol_print_date($this->db->jdate($trans->display_date), 'day'),
                $type_labels[$trans->transaction_type] ?? $trans->transaction_type,
                $trans->description,
                number_format($trans->amount, 2, ',', ''),
                $trans->note_private ?? '',
                $reference,
                $trans->user_login ?? ''
            ), ';');
        }
        
        fclose($output);
        exit(); // Arrêter l'exécution après l'envoi du CSV
    }
    
    /**
     * Ajoute l'en-tête avec logo dans le PDF
     */
    private function addPDFHeader($pdf)
    {
        global $conf, $mysoc;
        
        // Vérifier si un logo existe
        $logo = '';
        $logo_height = 0;
        
        // Chercher le logo principal de la société
        if (!empty($conf->global->MAIN_INFO_SOCIETE_LOGO)) {
            $logo = $conf->global->MAIN_INFO_SOCIETE_LOGO;
        }
        
        // Chemins possibles pour le logo
        $logo_paths = array(
            DOL_DATA_ROOT.'/mycompany/logos/'.$logo,
            DOL_DATA_ROOT.'/mycompany/logos/thumbs/'.$logo,
            DOL_DOCUMENT_ROOT.'/theme/common/logos/'.$logo,
            DOL_DATA_ROOT.'/'.$logo
        );
        
        $logo_file = '';
        foreach ($logo_paths as $path) {
            if (!empty($logo) && file_exists($path) && is_readable($path)) {
                $logo_file = $path;
                break;
            }
        }
        
        // Si pas de logo configuré, chercher des logos standards
        if (empty($logo_file)) {
            $standard_logos = array('logo.png', 'logo.jpg', 'logo.jpeg', 'logo.gif');
            foreach ($standard_logos as $std_logo) {
                foreach ($logo_paths as $base_path) {
                    $test_path = dirname($base_path).'/'.$std_logo;
                    if (file_exists($test_path) && is_readable($test_path)) {
                        $logo_file = $test_path;
                        break 2;
                    }
                }
            }
        }
        
        // Afficher le logo s'il existe
        if (!empty($logo_file)) {
            try {
                // Calculer les dimensions du logo (max 40mm de hauteur)
                $logo_height = 15; // hauteur par défaut en mm
                $logo_width = 0; // largeur auto
                
                // Positionner le logo en haut à gauche
                $pdf->Image($logo_file, 10, 10, $logo_width, $logo_height);
                
                // Ajouter les informations de la société à côté du logo
                $pdf->SetXY(60, 10);
                $pdf->SetFont('helvetica', 'B', 12);
                
                if (!empty($mysoc->name)) {
                    $pdf->Cell(0, 5, $mysoc->name, 0, 1);
                    $pdf->SetX(60);
                }
                
                $pdf->SetFont('helvetica', '', 9);
                if (!empty($mysoc->address)) {
                    $pdf->Cell(0, 4, $mysoc->address, 0, 1);
                    $pdf->SetX(60);
                }
                
                if (!empty($mysoc->zip) || !empty($mysoc->town)) {
                    $address_line = trim($mysoc->zip.' '.$mysoc->town);
                    if (!empty($address_line)) {
                        $pdf->Cell(0, 4, $address_line, 0, 1);
                        $pdf->SetX(60);
                    }
                }
                
                if (!empty($mysoc->phone)) {
                    $pdf->Cell(0, 4, 'Tél: '.$mysoc->phone, 0, 1);
                    $pdf->SetX(60);
                }
                
                if (!empty($mysoc->email)) {
                    $pdf->Cell(0, 4, 'Email: '.$mysoc->email, 0, 1);
                }
                
                // Espacer après l'en-tête
                $pdf->Ln($logo_height + 5);
                
            } catch (Exception $e) {
                // En cas d'erreur avec le logo, continuer sans
                $pdf->Ln(5);
            }
        } else {
            // Pas de logo, afficher juste les infos société
            $pdf->SetFont('helvetica', 'B', 12);
            if (!empty($mysoc->name)) {
                $pdf->Cell(0, 8, $mysoc->name, 0, 1, 'L');
            }
            $pdf->Ln(5);
        }
        
        // Ligne de séparation
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);
    }
}
?>