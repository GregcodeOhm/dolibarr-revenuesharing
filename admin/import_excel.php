<?php
// Fichier: /htdocs/custom/revenuesharing/admin/import_excel.php
// Import des données Excel vers Revenue Sharing

$dolibarr_main_document_root = '/home/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

// Load translation files
$langs->load("revenuesharing@revenuesharing");

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');

llxHeader('', 'Import Excel - Revenue Sharing', '');

$linkback = '<a href="setup.php">← Retour à la configuration</a>';
print load_fiche_titre('Import des données Excel', $linkback, 'generic');

// Traitement de l'import
if ($action == 'import') {
    if (isset($_FILES['importfile']) && $_FILES['importfile']['error'] == 0) {

        $upload_dir = DOL_DATA_ROOT.'/revenuesharing/temp';
        if (!is_dir($upload_dir)) {
            dol_mkdir($upload_dir);
        }

        $filename = $upload_dir.'/'.dol_sanitizeFileName($_FILES['importfile']['name']);

        if (move_uploaded_file($_FILES['importfile']['tmp_name'], $filename)) {

            print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 15px 0;">';
            print '<h3 style="margin: 0; color: #155724;">Fichier uploadé avec succès</h3>';
            print '<p>Fichier : '.basename($filename).'</p>';
            print '<p>Taille : '.number_format(filesize($filename)).' octets</p>';
            print '</div>';

            // Analyse basique du fichier
            $imported = 0;
            $errors = 0;
            $error_messages = array();

            try {
                // Vérifier si c'est un fichier CSV simple
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($extension, ['csv', 'txt'])) {
                    // Traitement CSV simple
                    $handle = fopen($filename, 'r');
                    if ($handle !== FALSE) {
                        $line_number = 0;
                        $headers = array();

                        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                            $line_number++;

                            if ($line_number == 1) {
                                // En-têtes
                                $headers = $data;
                                print '<h4>Structure détectée :</h4>';
                                print '<ul>';
                                foreach ($headers as $i => $header) {
                                    print '<li>Colonne '.($i+1).' : <strong>'.htmlspecialchars($header).'</strong></li>';
                                }
                                print '</ul>';
                                continue;
                            }

                            // Traitement des données (exemple basique)
                            if (count($data) >= 5) {
                                $prestation = trim($data[0]);
                                $montant_ttc = isset($data[3]) ? floatval(str_replace(',', '.', str_replace(' ', '', $data[3]))) : 0;
                                $montant_ht = isset($data[4]) ? floatval(str_replace(',', '.', str_replace(' ', '', $data[4]))) : 0;

                                if ($prestation && $montant_ht > 0) {
                                    print '<div style="background: #f8f9fa; padding: 10px; margin: 5px 0; border-left: 3px solid #007cba;">';
                                    print '<strong>Ligne '.$line_number.' :</strong> '.htmlspecialchars($prestation).' - '.price($montant_ht);
                                    print '</div>';
                                    $imported++;
                                }
                            }

                            if ($line_number > 10) {
                                print '<div style="color: orange; padding: 10px; background: #fff3cd; border-radius: 3px; margin: 10px 0;">';
                                print 'Affichage limité aux 10 premières lignes pour la démonstration.';
                                print '</div>';
                                break;
                            }
                        }

                        fclose($handle);

                        print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 15px 0;">';
                        print '<h3 style="margin: 0; color: #155724;">Analyse terminée</h3>';
                        print '<p><strong>'.$imported.' lignes</strong> ont été analysées avec succès.</p>';
                        print '<p><em>Note : Ceci est une démonstration. L\'import réel nécessiterait une validation et insertion en base.</em></p>';
                        print '</div>';

                    } else {
                        print '<div style="color: red; padding: 15px; background: #f8d7da; border-radius: 5px;">';
                        print 'Impossible de lire le fichier CSV.';
                        print '</div>';
                    }

                } elseif (in_array($extension, ['xlsx', 'xls'])) {

                    print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 15px 0;">';
                    print '<h3 style="margin: 0; color: #856404;">Fichier Excel détecté</h3>';
                    print '<p>Pour traiter les fichiers Excel (.xlsx/.xls), il faut installer la bibliothèque PhpSpreadsheet.</p>';
                    print '<p><strong>Alternative :</strong> Convertissez votre fichier Excel en CSV depuis Excel :</p>';
                    print '<ol>';
                    print '<li>Ouvrir le fichier dans Excel</li>';
                    print '<li>Fichier > Enregistrer sous</li>';
                    print '<li>Choisir le format "CSV (délimité par des points-virgules)"</li>';
                    print '<li>Réessayer l\'import avec le fichier CSV</li>';
                    print '</ol>';
                    print '</div>';

                } else {
                    print '<div style="color: red; padding: 15px; background: #f8d7da; border-radius: 5px;">';
                    print 'Format de fichier non supporté : .'.$extension;
                    print '</div>';
                }

            } catch (Exception $e) {
                print '<div style="color: red; padding: 15px; background: #f8d7da; border-radius: 5px;">';
                print 'Erreur lors de l\'analyse : '.htmlspecialchars($e->getMessage());
                print '</div>';
            }

            // Supprimer le fichier temporaire
            unlink($filename);

        } else {
            print '<div style="color: red; padding: 15px; background: #f8d7da; border-radius: 5px;">';
            print 'Erreur lors de l\'upload du fichier.';
            print '</div>';
        }

    } else {
        $error_message = "Aucun fichier sélectionné";
        if (isset($_FILES['importfile']['error'])) {
            switch ($_FILES['importfile']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = "Fichier trop volumineux";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message = "Upload partiel";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = "Aucun fichier sélectionné";
                    break;
                default:
                    $error_message = "Erreur d'upload (code: ".$_FILES['importfile']['error'].")";
            }
        }

        print '<div style="color: red; padding: 15px; background: #f8d7da; border-radius: 5px;">';
        print ''.$error_message;
        print '</div>';
    }
}

// Formulaire d'import
print '<div class="fichecenter">';

print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 5px; padding: 15px; margin: 15px 0;">';
print '<h3 style="margin: 0 0 10px 0; color: #1565c0;">Information</h3>';
print '<p>Cette page permet d\'importer vos données depuis un fichier Excel ou CSV.</p>';
print '<p><strong>Formats supportés :</strong> CSV (recommandé), TXT avec séparateur point-virgule</p>';
print '</div>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="import">';

print '<table class="border centpercent">';

print '<tr class="liste_titre">';
print '<th colspan="2">Sélection du fichier</th>';
print '</tr>';

print '<tr>';
print '<td class="fieldrequired titlefield">Fichier à importer</td>';
print '<td>';
print '<input type="file" name="importfile" accept=".xlsx,.xls,.csv,.txt" required style="width: 100%;">';
print '<br><small style="color: #666;">Formats acceptés : Excel (.xlsx, .xls) ou CSV (.csv, .txt)</small>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td colspan="2" class="center" style="padding: 20px;">';
print '<input type="submit" class="button" value="Analyser le fichier" style="font-size: 16px; padding: 10px 20px;">';
print '</td>';
print '</tr>';

print '</table>';

print '</form>';
print '</div>';

// Instructions détaillées
print '<br>';
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px;">';
print '<h3>Instructions d\'import</h3>';

print '<h4>Structure attendue du fichier :</h4>';
print '<table class="noborder" style="font-size: 14px;">';
print '<tr class="liste_titre">';
print '<th>Colonne</th>';
print '<th>Nom suggéré</th>';
print '<th>Description</th>';
print '<th>Exemple</th>';
print '</tr>';

$structure = array(
    array('A', 'Prestation', 'Description de la prestation', 'Enregistrement 3 titres'),
    array('B', 'Facture', 'Référence facture (optionnel)', 'FA2024-001'),
    array('C', 'Statut', 'Statut du règlement', 'Règlé'),
    array('D', 'Montant TTC', 'Montant toutes taxes comprises', '1200.00'),
    array('E', 'Montant HT', 'Montant hors taxes', '1000.00'),
    array('F', 'Part Tony', 'Part du collaborateur', '600.00'),
    array('G', 'Part Studio', 'Part du studio', '400.00'),
);

foreach ($structure as $row) {
    print '<tr class="oddeven">';
    print '<td><strong>'.$row[0].'</strong></td>';
    print '<td>'.$row[1].'</td>';
    print '<td>'.$row[2].'</td>';
    print '<td><code>'.$row[3].'</code></td>';
    print '</tr>';
}

print '</table>';

print '<h4>Conseils :</h4>';
print '<ul>';
print '<li><strong>Première ligne :</strong> Doit contenir les en-têtes de colonnes</li>';
print '<li><strong>Séparateur CSV :</strong> Point-virgule (;) de préférence</li>';
print '<li><strong>Nombres :</strong> Utiliser le point ou la virgule comme séparateur décimal</li>';
print '<li><strong>Encodage :</strong> UTF-8 recommandé</li>';
print '<li><strong>Test :</strong> Commencez par un petit fichier de test</li>';
print '</ul>';

print '<h4>Conversion Excel vers CSV :</h4>';
print '<ol>';
print '<li>Ouvrir votre fichier Excel</li>';
print '<li>Fichier > Enregistrer sous</li>';
print '<li>Type : "CSV (délimité par des points-virgules) (*.csv)"</li>';
print '<li>Enregistrer et utiliser ce fichier CSV pour l\'import</li>';
print '</ol>';

print '</div>';

// Informations techniques
print '<br>';
print '<div style="background: #f1f3f4; border: 1px solid #dadce0; border-radius: 5px; padding: 15px;">';
print '<h4>Informations techniques</h4>';
print '<table class="noborder">';
print '<tr><td><strong>Taille max :</strong></td><td>'.ini_get('upload_max_filesize').'</td></tr>';
print '<tr><td><strong>Mémoire PHP :</strong></td><td>'.ini_get('memory_limit').'</td></tr>';
print '<tr><td><strong>Répertoire temp :</strong></td><td>'.DOL_DATA_ROOT.'/revenuesharing/temp</td></tr>';
print '<tr><td><strong>Extensions PHP :</strong></td><td>';
if (extension_loaded('zip')) print 'ZIP '; else print 'ZIP ';
if (extension_loaded('xml')) print 'XML '; else print 'XML ';
if (function_exists('fgetcsv')) print 'CSV '; else print 'CSV ';
print '</td></tr>';
print '</table>';
print '</div>';

print '<br>';
print '<div class="tabsAction">';
print '<a href="setup.php" class="butAction">Retour à la configuration</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>
