<?php
/**
 * Fichier d'export de tous les comptes collaborateurs
 * Fichier: /htdocs/custom/revenuesharing/export_all_accounts.php
 */

require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$format = GETPOST('format', 'alpha'); // 'csv' ou 'pdf' pour l'export global
$filter_year = GETPOST('filter_year', 'int');

// Vérification du token CSRF  
if ($action == 'export' && !newToken('check')) {
    setEventMessages('Token de sécurité invalide', null, 'errors');
    header('Location: account_list.php');
    exit;
}

if ($action == 'export' && in_array($format, array('csv', 'pdf'))) {
    
    try {
        // Récupérer tous les comptes avec leurs soldes
        $year_filter_sql = "";
        if ($filter_year > 0) {
            $year_filter_sql = " AND YEAR(t.transaction_date) = ".(int)$filter_year;
        }
        
        if ($filter_year > 0) {
            // Requête avec filtrage par année
            $sql = "SELECT c.rowid, c.label, u.firstname, u.lastname, u.email, c.default_percentage, c.active,
                           (SELECT COALESCE(SUM(t.amount), 0) 
                            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                            WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as current_balance,
                           (SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) 
                            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                            WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as total_credits,
                           (SELECT COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0) 
                            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                            WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as total_debits,
                           ab.date_updated,
                           (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                            WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as nb_transactions
                    FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                    LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
                    LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_balance ab ON ab.fk_collaborator = c.rowid
                    WHERE c.active = 1
                    ORDER BY c.label";
        } else {
            // Requête sans filtre année (données globales)
            $sql = "SELECT c.rowid, c.label, u.firstname, u.lastname, u.email, c.default_percentage, c.active,
                           COALESCE(ab.current_balance, 0) as current_balance,
                           COALESCE(ab.total_credits, 0) as total_credits,
                           COALESCE(ab.total_debits, 0) as total_debits,
                           ab.date_updated,
                           (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                            WHERE t.fk_collaborator = c.rowid AND t.status = 1) as nb_transactions
                    FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                    LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
                    LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_balance ab ON ab.fk_collaborator = c.rowid
                    WHERE c.active = 1
                    ORDER BY c.label";
        }
        
        $resql = $db->query($sql);
        if (!$resql) {
            setEventMessages('Erreur SQL: '.$db->lasterror(), null, 'errors');
            header('Location: account_list.php');
            exit;
        }
        
        // Générer le nom de fichier avec filtre année si applicable
        $year_suffix = ($filter_year > 0) ? '_'.$filter_year : '';
        
        if ($format == 'csv') {
            // === EXPORT CSV ===
            $filename = 'export_comptes_collaborateurs'.$year_suffix.'_'.dol_print_date(dol_now(), 'dayrfc').'.csv';
            $filename = dol_sanitizeFileName($filename);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // BOM pour UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        // En-tête du fichier
        $title = 'EXPORT COMPTES COLLABORATEURS';
        if ($filter_year > 0) {
            $title .= ' - ANNÉE '.$filter_year;
        }
        fputcsv($output, array($title), ';');
        fputcsv($output, array('Date d\'export', dol_print_date(dol_now(), 'daytext')), ';');
        fputcsv($output, array('Module', 'Revenue Sharing - Dolibarr'), ';');
        fputcsv($output, array(''), ';');
        
        // En-têtes des colonnes
        fputcsv($output, array(
            'ID',
            'Nom collaborateur', 
            'Prénom', 
            'Nom de famille',
            'Email',
            '% défaut',
            'Statut',
            'Solde actuel (€)',
            'Total crédits (€)',
            'Total débits (€)',
            'Nb transactions',
            'Dernière MAJ'
        ), ';');
        
        // Données des comptes
        while ($account = $db->fetch_object($resql)) {
            fputcsv($output, array(
                $account->rowid,
                $account->label,
                $account->firstname ?? '',
                $account->lastname ?? '',
                $account->email ?? '',
                $account->default_percentage ?? '',
                $account->active ? 'Actif' : 'Inactif',
                number_format($account->current_balance, 2, ',', ''),
                number_format($account->total_credits, 2, ',', ''),
                number_format($account->total_debits, 2, ',', ''),
                $account->nb_transactions,
                $account->date_updated ? dol_print_date($db->jdate($account->date_updated), 'daytext') : ''
            ), ';');
        }
        
            $db->free($resql);
            fclose($output);
            exit;
            
        } elseif ($format == 'pdf') {
            // === EXPORT PDF ===
            require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
            
            $filename = 'export_comptes_collaborateurs'.$year_suffix.'_'.dol_print_date(dol_now(), 'dayrfc').'.pdf';
            $filename = dol_sanitizeFileName($filename);
            
            // Créer le PDF avec TCPDF (inclus dans Dolibarr)
            $pdf = pdf_getInstance();
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();
            
            // En-tête
            $pdf->SetFont('helvetica', 'B', 16);
            $title = 'EXPORT COMPTES COLLABORATEURS';
            if ($filter_year > 0) {
                $title .= ' - ANNÉE '.$filter_year;
            }
            $pdf->Cell(0, 10, $title, 0, 1, 'C');
            $pdf->Ln(5);
            
            // Date d'export
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, 'Date d\'export: '.dol_print_date(dol_now(), 'daytext'), 0, 1, 'C');
            $pdf->Cell(0, 6, 'Module: Revenue Sharing - Dolibarr', 0, 1, 'C');
            $pdf->Ln(10);
            
            // En-têtes du tableau
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(50, 8, 'Collaborateur', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Crédits (€)', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Débits (€)', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Solde (€)', 1, 0, 'C');
            $pdf->Cell(20, 8, 'Nb Trans.', 1, 0, 'C');
            $pdf->Cell(45, 8, 'Contact', 1, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 8);
            
            // Données des comptes
            $total_credits = 0;
            $total_debits = 0;
            $total_balance = 0;
            $total_transactions = 0;
            
            while ($account = $db->fetch_object($resql)) {
                // Vérifier si on doit ajouter une nouvelle page
                if ($pdf->GetY() > 250) {
                    $pdf->AddPage();
                    // Répéter les en-têtes
                    $pdf->SetFont('helvetica', 'B', 9);
                    $pdf->Cell(50, 8, 'Collaborateur', 1, 0, 'C');
                    $pdf->Cell(25, 8, 'Crédits (€)', 1, 0, 'C');
                    $pdf->Cell(25, 8, 'Débits (€)', 1, 0, 'C');
                    $pdf->Cell(25, 8, 'Solde (€)', 1, 0, 'C');
                    $pdf->Cell(20, 8, 'Nb Trans.', 1, 0, 'C');
                    $pdf->Cell(45, 8, 'Contact', 1, 1, 'C');
                    $pdf->SetFont('helvetica', '', 8);
                }
                
                $pdf->Cell(50, 6, $account->label, 1, 0, 'L');
                $pdf->Cell(25, 6, price($account->total_credits), 1, 0, 'R');
                $pdf->Cell(25, 6, price($account->total_debits), 1, 0, 'R');
                $pdf->Cell(25, 6, price($account->current_balance), 1, 0, 'R');
                $pdf->Cell(20, 6, $account->nb_transactions, 1, 0, 'C');
                
                $contact = '';
                if ($account->firstname && $account->lastname) {
                    $contact = $account->firstname.' '.$account->lastname;
                }
                if ($account->email) {
                    $contact .= ($contact ? ' - ' : '').$account->email;
                }
                $contact = strlen($contact) > 35 ? substr($contact, 0, 32).'...' : $contact;
                $pdf->Cell(45, 6, $contact, 1, 1, 'L');
                
                $total_credits += $account->total_credits;
                $total_debits += $account->total_debits;
                $total_balance += $account->current_balance;
                $total_transactions += $account->nb_transactions;
            }
            
            // Ligne de totaux
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(50, 8, 'TOTAUX', 1, 0, 'C');
            $pdf->Cell(25, 8, price($total_credits), 1, 0, 'R');
            $pdf->Cell(25, 8, price($total_debits), 1, 0, 'R');
            $pdf->Cell(25, 8, price($total_balance), 1, 0, 'R');
            $pdf->Cell(20, 8, $total_transactions, 1, 0, 'C');
            $pdf->Cell(45, 8, '', 1, 1, 'C');
            
            // Pied de page
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(0, 6, 'Document généré le '.dol_print_date(dol_now(), 'dayhourtext'), 0, 1, 'C');
            $pdf->Cell(0, 6, 'Module Revenue Sharing - Dolibarr', 0, 1, 'C');
            
            $db->free($resql);
            
            // Output du PDF
            $pdf->Output($filename, 'D');
            exit;
        }
        
    } catch (Exception $e) {
        setEventMessages('Erreur lors de l\'export: '.$e->getMessage(), null, 'errors');
        header('Location: account_list.php');
        exit;
    }
    
} else {
    // Action non reconnue, retour à la liste
    header('Location: account_list.php');
    exit;
}
?>