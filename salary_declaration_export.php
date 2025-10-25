<?php
/**
 * Export PDF des déclarations de salaires
 * Fichier: /htdocs/custom/revenuesharing/salary_declaration_export.php
 */

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once './lib/metiers_son.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$id = GETPOST('id', 'int');
$format = GETPOST('format', 'alpha');

if ($id <= 0) {
    setEventMessages('ID déclaration manquant', null, 'errors');
    header('Location: salary_declarations_list.php');
    exit;
}

if ($format !== 'pdf') {
    setEventMessages('Format non supporté', null, 'errors');
    header('Location: salary_declarations_list.php');
    exit;
}

// Charger la déclaration
$sql = "SELECT d.*, c.label as collaborator_name
        FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
        LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = d.fk_collaborator
        WHERE d.rowid = ".(int)$id;

$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    setEventMessages('Déclaration non trouvée', null, 'errors');
    header('Location: salary_declarations_list.php');
    exit;
}

$declaration = $db->fetch_object($resql);
$db->free($resql);

// Charger les détails
$sql_details = "SELECT * FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail 
               WHERE fk_declaration = ".(int)$id." ORDER BY work_date";
$resql_details = $db->query($sql_details);
$details = array();
if ($resql_details) {
    while ($detail = $db->fetch_object($resql_details)) {
        $details[] = $detail;
    }
    $db->free($resql_details);
}

// Charger les informations de la société
global $mysoc;
if (empty($mysoc)) {
    $mysoc = new Societe($db);
    $mysoc->setMysoc($conf);
}

// Créer le PDF
$pdf = pdf_getInstance();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// En-tête avec logo (réutiliser la fonction de export_account.class.php)
addPDFHeaderSalary($pdf, $mysoc);

// Titre principal
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'DÉCLARATION DE SALAIRES - INTERMITTENTS', 0, 1, 'C');
$pdf->Ln(5);

// Informations de la déclaration
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Informations de la déclaration', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$months = array(1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre');

$pdf->Cell(50, 6, 'Collaborateur:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, $declaration->collaborator_name, 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 6, 'Période:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, $months[$declaration->declaration_month].' '.$declaration->declaration_year, 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 6, 'Date de création:', 0, 0);
$pdf->Cell(0, 6, dol_print_date($db->jdate($declaration->date_creation), 'daytext'), 0, 1);

$pdf->Ln(5);

// Résumé financier
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Résumé financier', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 6, 'Nombre de jours:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, $declaration->total_days, 0, 1);
$pdf->SetFont('helvetica', '', 10);

// Calculer le total d'heures
$total_heures = 0;
foreach ($details as $detail) {
    $total_heures += isset($detail->nb_heures) ? floatval($detail->nb_heures) : 8.00;
}

$pdf->Cell(50, 6, 'Total heures:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, number_format($total_heures, 1, ',', ' ').' h', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 6, 'Cachet unitaire:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, price($declaration->cachet_brut_unitaire).' €', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(50, 6, 'Total cachets bruts:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, price($declaration->total_cachets).' €', 0, 1);
$pdf->SetFont('helvetica', '', 10);

if ($declaration->masse_salariale > 0) {
    $pdf->Cell(50, 6, 'Masse salariale:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, price($declaration->masse_salariale).' €', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
}

if ($declaration->solde_utilise > 0) {
    $pdf->Cell(50, 6, 'Solde utilisé:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, price($declaration->solde_utilise).' €', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
}

$pdf->Ln(8);

// Détail des jours travaillés
if (!empty($details)) {
    // Déterminer si on doit afficher la colonne cachet
    $has_non_technicien = false;
    $metiers_techniciens = array('technicien_son', 'technicien_audionumerique', 'technicien_antenne', 'technicien_sonorisation');

    foreach ($details as $detail) {
        $metier = isset($detail->metier_son) ? $detail->metier_son : '';
        if (!in_array($metier, $metiers_techniciens)) {
            $has_non_technicien = true;
            break;
        }
    }

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Détail des jours travaillés', 0, 1);

    // En-têtes du tableau - adapter selon si on affiche les cachets
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(22, 6, 'Date', 1, 0, 'C');
    $pdf->Cell(18, 6, 'Jour', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Contrat', 1, 0, 'C');
    $pdf->Cell(40, 6, 'Métier du son', 1, 0, 'C');
    $pdf->Cell(15, 6, 'Heures', 1, 0, 'C');

    if ($has_non_technicien) {
        $pdf->Cell(15, 6, 'Cachets', 1, 0, 'C');
        $pdf->Cell(22, 6, 'Cachet brut', 1, 0, 'C');
        $pdf->Cell(38, 6, 'Observations', 1, 1, 'C');
    } else {
        $pdf->Cell(22, 6, 'Cachet brut', 1, 0, 'C');
        $pdf->Cell(53, 6, 'Observations', 1, 1, 'C');
    }
    
    $pdf->SetFont('helvetica', '', 8);
    
    $total_displayed = 0;
    foreach ($details as $detail) {
        // Vérifier si on doit ajouter une nouvelle page
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Répéter les en-têtes
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(22, 6, 'Date', 1, 0, 'C');
            $pdf->Cell(18, 6, 'Jour', 1, 0, 'C');
            $pdf->Cell(20, 6, 'Contrat', 1, 0, 'C');
            $pdf->Cell(40, 6, 'Métier du son', 1, 0, 'C');
            $pdf->Cell(15, 6, 'Heures', 1, 0, 'C');

            if ($has_non_technicien) {
                $pdf->Cell(15, 6, 'Cachets', 1, 0, 'C');
                $pdf->Cell(22, 6, 'Cachet brut', 1, 0, 'C');
                $pdf->Cell(38, 6, 'Observations', 1, 1, 'C');
            } else {
                $pdf->Cell(22, 6, 'Cachet brut', 1, 0, 'C');
                $pdf->Cell(53, 6, 'Observations', 1, 1, 'C');
            }
            $pdf->SetFont('helvetica', '', 8);
        }

        $date = dol_print_date($db->jdate($detail->work_date), 'day');
        $day_name = date('l', $db->jdate($detail->work_date));
        $day_names = array(
            'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        );
        $jour = $day_names[$day_name] ?? $day_name;

        // Récupérer le libellé du métier
        $metier_label = getMetierSonLabel($detail->metier_son);

        // Récupérer les heures (défaut 8h si non défini)
        $heures = isset($detail->nb_heures) ? floatval($detail->nb_heures) : 8.00;

        $pdf->Cell(22, 6, $date, 1, 0, 'C');
        $pdf->Cell(18, 6, substr($jour, 0, 3), 1, 0, 'C'); // Abrégé
        $pdf->Cell(20, 6, $detail->type_contrat, 1, 0, 'C');
        $pdf->Cell(40, 6, substr($metier_label, 0, 20), 1, 0, 'L'); // Métier du son
        $pdf->Cell(15, 6, number_format($heures, 1, ',', '').'h', 1, 0, 'C'); // Heures

        if ($has_non_technicien) {
            $pdf->Cell(15, 6, number_format($detail->nb_cachets, 1), 1, 0, 'C');
            $pdf->Cell(22, 6, price($detail->cachet_brut), 1, 0, 'R');
            $description = $detail->description ? substr($detail->description, 0, 15) : '';
            $pdf->Cell(38, 6, $description, 1, 1, 'L');
        } else {
            $pdf->Cell(22, 6, price($detail->cachet_brut), 1, 0, 'R');
            $description = $detail->description ? substr($detail->description, 0, 25) : '';
            $pdf->Cell(53, 6, $description, 1, 1, 'L');
        }

        $total_displayed += $detail->cachet_brut;
    }

    // Ligne de total
    $pdf->SetFont('helvetica', 'B', 8);

    if ($has_non_technicien) {
        // Somme des largeurs: 22+18+20+40+15+15 = 130
        $pdf->Cell(130, 6, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(22, 6, price($total_displayed), 1, 0, 'R');
        $pdf->Cell(38, 6, '', 1, 1, 'L'); // Cellule vide pour observations
    } else {
        // Somme des largeurs: 22+18+20+40+15 = 115 (sans la colonne Cachets)
        $pdf->Cell(115, 6, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(22, 6, price($total_displayed), 1, 0, 'R');
        $pdf->Cell(53, 6, '', 1, 1, 'L'); // Cellule vide plus large pour observations
    }
}

$pdf->Ln(10);

// Note privée
if (!empty($declaration->note_private)) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Notes:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, $declaration->note_private, 0, 'L');
    $pdf->Ln(5);
}

// Pied de page
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, 'Document généré le '.dol_print_date(dol_now(), 'dayhourtext'), 0, 1, 'C');
$pdf->Cell(0, 6, 'Module Revenue Sharing - Déclarations Salaires Intermittents', 0, 1, 'C');
$pdf->Cell(0, 6, 'Métiers du son selon Convention Collective IDCC 2642 (Production audiovisuelle)', 0, 1, 'C');

// Signature
$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(95, 6, 'Signature employeur:', 0, 0, 'L');
$pdf->Cell(95, 6, 'Signature gestionnaire paie:', 0, 1, 'L');
$pdf->Ln(20);
$pdf->Cell(95, 6, 'Date: _______________', 0, 0, 'L');
$pdf->Cell(95, 6, 'Date: _______________', 0, 1, 'L');

// Nom du fichier
$filename = 'declaration_salaires_'.dol_sanitizeFileName($declaration->collaborator_name).'_'.$declaration->declaration_month.'_'.$declaration->declaration_year.'.pdf';

// Output du PDF
$pdf->Output($filename, 'D');

/**
 * Fonction pour l'en-tête PDF avec logo
 */
function addPDFHeaderSalary($pdf, $mysoc)
{
    global $conf;
    
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
            // Calculer les dimensions du logo (max 15mm de hauteur)
            $logo_height = 15;
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

$db->close();
?>