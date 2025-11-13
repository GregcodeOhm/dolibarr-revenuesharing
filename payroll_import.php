<?php
/**
 * Import et conversion des exports de paie vers format comptable
 * Fichier: /htdocs/custom/revenuesharing/payroll_import.php
 */

require_once '../../main.inc.php';
require_once __DIR__.'/class/PayrollToAccounting.class.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$piece_num = GETPOST('piece_num', 'alpha');
$journal_code = GETPOST('journal_code', 'alpha') ? GETPOST('journal_code', 'alpha') : 'SAL';
$journal_label = GETPOST('journal_label', 'alpha') ? GETPOST('journal_label', 'alpha') : 'Journal de Paie';

llxHeader('', 'Import Export Paie vers Comptabilité', '');

print load_fiche_titre('Conversion Export Paie vers Format Comptable', '', 'generic');

$converter = new PayrollToAccounting($db);

// Traitement de l'upload et conversion
if ($action == 'convert' && !empty($_FILES['payroll_file']['tmp_name'])) {
    try {
        $upload_file = $_FILES['payroll_file']['tmp_name'];
        $original_name = $_FILES['payroll_file']['name'];

        // Validation du numéro d'écriture
        if (empty($piece_num)) {
            throw new Exception("Le numéro d'écriture est obligatoire");
        }

        // Configuration du convertisseur
        $converter->setPieceNum($piece_num);
        $converter->setJournal($journal_code, $journal_label);

        // Parser le fichier
        if (!$converter->parsePayrollFile($upload_file)) {
            $errors = $converter->getErrors();
            throw new Exception("Erreur de parsing : ".implode(', ', $errors));
        }

        // Récupérer les statistiques
        $stats = $converter->getStatistics();

        // Afficher les informations avant génération
        print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 15px; margin: 20px 0;">';
        print '<h4 style="margin: 0 0 10px 0; color: #2d7d2d;">✅ Fichier parsé avec succès</h4>';
        print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';

        print '<div style="text-align: center;">';
        print '<div style="font-size: 1.5em; font-weight: bold; color: #2d7d2d;">'.$stats->nb_entries.'</div>';
        print '<div class="opacitymedium">Écritures comptables</div>';
        print '</div>';

        print '<div style="text-align: center;">';
        print '<div style="font-size: 1.5em; font-weight: bold; color: #28a745;">'.price($stats->total_debit).'</div>';
        print '<div class="opacitymedium">Total Débits</div>';
        print '</div>';

        print '<div style="text-align: center;">';
        print '<div style="font-size: 1.5em; font-weight: bold; color: #dc3545;">'.price($stats->total_credit).'</div>';
        print '<div class="opacitymedium">Total Crédits</div>';
        print '</div>';

        print '<div style="text-align: center;">';
        if ($stats->is_balanced) {
            print '<div style="font-size: 1.5em; font-weight: bold; color: #28a745;">✓ Équilibré</div>';
        } else {
            print '<div style="font-size: 1.5em; font-weight: bold; color: #dc3545;">✗ Déséquilibré</div>';
            print '<div style="color: #dc3545;">Écart : '.price(abs($stats->balance)).'</div>';
        }
        print '<div class="opacitymedium">Balance</div>';
        print '</div>';

        print '</div>';
        print '</div>';

        // Générer le fichier CSV (plus simple et compatible)
        $output_name = 'Import_Comptable_'.date('Y-m-d_His').'.csv';
        $output_path = DOL_DATA_ROOT.'/revenuesharing/temp/'.$output_name;

        // Créer le répertoire si nécessaire
        $temp_dir = DOL_DATA_ROOT.'/revenuesharing/temp';
        if (!is_dir($temp_dir)) {
            dol_mkdir($temp_dir);
        }

        if ($converter->generateAccountingCSV($output_path, $piece_num)) {
            print '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 20px 0; border-radius: 4px;">';
            print '<strong>✅ Fichier CSV généré avec succès !</strong><br>';
            print '<p style="margin: 10px 0;">Le fichier CSV est prêt à être importé dans le module Comptabilité de Dolibarr.</p>';
            print '<div style="margin: 15px 0; display: flex; gap: 10px; flex-wrap: wrap;">';
            print '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=revenuesharing&file=temp/'.$output_name.'" class="button">';
            print img_picto('', 'download', 'class="pictofixedwidth"').' Télécharger le fichier CSV';
            print '</a>';
            print '<a href="'.DOL_URL_ROOT.'/imports/import.php?leftmenu=import" class="button" style="background: #28a745; color: white;">';
            print img_picto('', 'import', 'class="pictofixedwidth"').' Importer dans la Comptabilité';
            print '</a>';
            print '</div>';
            print '<p style="margin-top: 10px; font-size: 0.9em; color: var(--colortextbackhmenu);"><strong>💡 Astuce :</strong> Téléchargez d\'abord le fichier CSV, puis cliquez sur "Importer dans la Comptabilité" pour accéder directement au module d\'import.</p>';
            print '</div>';

            // Aperçu des premières lignes
            print '<h3>Aperçu des données converties (5 premières lignes)</h3>';
            print '<div class="div-table-responsive-no-min">';
            print '<table class="noborder centpercent">';
            print '<tr class="liste_titre">';
            print '<th>Date</th>';
            print '<th>Pièce</th>';
            print '<th>Journal</th>';
            print '<th>Compte</th>';
            print '<th>Libellé</th>';
            print '<th class="right">Débit</th>';
            print '<th class="right">Crédit</th>';
            print '<th>Sens</th>';
            print '</tr>';

            $data = $converter->getData();
            $count = 0;
            foreach ($data as $entry) {
                if ($count >= 5) break;

                print '<tr class="oddeven">';
                print '<td>'.$entry['date'].'</td>';
                print '<td>'.$entry['doc_ref'].'</td>';
                print '<td>'.$journal_code.'</td>';
                print '<td>'.$entry['numero_compte'].'</td>';
                print '<td>'.$entry['label_operation'].'</td>';
                print '<td class="right">'.($entry['debit'] > 0 ? price($entry['debit']) : '-').'</td>';
                print '<td class="right">'.($entry['credit'] > 0 ? price($entry['credit']) : '-').'</td>';
                print '<td>'.($entry['debit'] > 0 ? 'D' : 'C').'</td>';
                print '</tr>';

                $count++;
            }

            if (count($data) > 5) {
                print '<tr><td colspan="8" class="center"><em>... et '.( count($data) - 5).' autres lignes</em></td></tr>';
            }

            print '</table>';
            print '</div>';

        } else {
            $errors = $converter->getErrors();
            throw new Exception("Erreur lors de la génération : ".implode(', ', $errors));
        }

    } catch (Exception $e) {
        print '<div style="background: var(--colorbacktabcard1); border: 1px solid #dc3545; color: var(--colortext); padding: 15px; margin: 20px 0; border-radius: 4px;">';
        print '<strong>⚠️ Erreur:</strong> '.htmlspecialchars($e->getMessage()).'<br>';
        print '<strong>Fichier:</strong> '.$e->getFile().' ligne '.$e->getLine().'<br>';
        print '<pre>'.htmlspecialchars($e->getTraceAsString()).'</pre>';
        print '</div>';
    } catch (Throwable $e) {
        print '<div style="background: var(--colorbacktabcard1); border: 1px solid #dc3545; color: var(--colortext); padding: 15px; margin: 20px 0; border-radius: 4px;">';
        print '<strong>⚠️ Erreur fatale:</strong> '.htmlspecialchars($e->getMessage()).'<br>';
        print '<strong>Fichier:</strong> '.$e->getFile().' ligne '.$e->getLine().'<br>';
        print '<pre>'.htmlspecialchars($e->getTraceAsString()).'</pre>';
        print '</div>';
    }
}

// Formulaire d'upload
print '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
print '<h3>📤 Upload du fichier d\'export de paie</h3>';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="convert">';

print '<table class="border centpercent">';

// Fichier à uploader
print '<tr>';
print '<td class="fieldrequired" width="25%">Fichier export paie (.txt)</td>';
print '<td><input type="file" name="payroll_file" accept=".txt" required style="width: 100%;"></td>';
print '</tr>';

// Numéro d'écriture
print '<tr>';
print '<td class="fieldrequired">Numéro d\'écriture (b.piece_num)</td>';
print '<td><input type="text" name="piece_num" value="'.dol_escape_htmltag($piece_num).'" required placeholder="Ex: 2025-10-001" style="width: 100%;"></td>';
print '</tr>';

// Code journal
print '<tr>';
print '<td>Code journal</td>';
print '<td><input type="text" name="journal_code" value="'.dol_escape_htmltag($journal_code).'" placeholder="SAL" style="width: 100%;"></td>';
print '</tr>';

// Libellé journal
print '<tr>';
print '<td>Libellé journal</td>';
print '<td><input type="text" name="journal_label" value="'.dol_escape_htmltag($journal_label).'" placeholder="Journal de Paie" style="width: 100%;"></td>';
print '</tr>';

print '</table>';

print '<div style="text-align: center; margin-top: 20px;">';
print '<input type="submit" class="button" value="Convertir en format CSV">';
print '</div>';

print '</form>';
print '</div>';

// Documentation
print '<div style="background: #e3f2fd; border: 1px solid #90caf9; padding: 15px; margin: 20px 0; border-radius: 4px;">';
print '<h4 style="margin-top: 0;">ℹ️ Format attendu du fichier texte</h4>';
print '<p>Le fichier doit contenir des lignes séparées par des <strong>tabulations</strong> avec le format suivant :</p>';
print '<pre style="background: white; padding: 10px; border-radius: 4px; overflow-x: auto;">';
print 'journal | date | ref | compte | vide | libellé | montant_débit | montant_crédit';
print "\n";
print 'odp | 31/10/2025 | SAL10 | 64110000 | | Brut | 1800 | 0';
print '</pre>';
print '<p><strong>Colonnes attendues :</strong></p>';
print '<ol>';
print '<li>Code journal source (ignoré, remplacé par le code journal saisi)</li>';
print '<li>Date au format JJ/MM/AAAA</li>';
print '<li>Référence de la pièce</li>';
print '<li>Numéro de compte comptable</li>';
print '<li>Colonne vide (ignorée)</li>';
print '<li>Libellé de l\'opération</li>';
print '<li>Montant au débit</li>';
print '<li>Montant au crédit</li>';
print '</ol>';
print '</div>';

print '<div class="tabsAction">';
print '<a href="index.php" class="butAction">🏠 Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>
