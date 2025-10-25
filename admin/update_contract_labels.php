<?php
/**
 * Script de mise à jour des libellés des contrats depuis les documents joints
 * Fichier: /htdocs/custom/revenuesharing/admin/update_contract_labels.php
 */

// Chemin corrigé pour votre installation
$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';
require_once $dolibarr_main_document_root.'/core/lib/admin.lib.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$mode = GETPOST('mode', 'alpha'); // 'preview' ou 'execute'

llxHeader('', 'Mise à jour des libellés de contrats', '');

$linkback = '<a href="setup.php">← Retour Configuration</a>';
print load_fiche_titre('Mise à jour des libellés de contrats', $linkback, 'generic');

print '<div style="background: #e8f4fd; border: 1px solid #b8d4f0; border-radius: 8px; padding: 15px; margin: 20px 0;">';
print '<h3 style="margin: 0 0 10px 0; color: #1e6ba8;">À propos de cet outil</h3>';
print '<p style="margin: 5px 0;">Cet outil synchronise les libellés des contrats avec ceux de leurs documents joints (factures clients ou fournisseurs).</p>';
print '<p style="margin: 5px 0;"><strong>Logique appliquée :</strong></p>';
print '<ul style="margin: 5px 0 0 20px;">';
print '<li>Seules les <strong>factures clients</strong> sont utilisées comme source</li>';
print '<li>Si le contrat n\'a pas de facture client : conserve le libellé actuel</li>';
print '</ul>';
print '</div>';

if ($action == 'analyze') {

    print '<h2>Analyse des contrats</h2>';

    // Requête pour identifier les contrats à mettre à jour (factures clients uniquement)
    $sql_analysis = "SELECT
        rc.rowid,
        rc.ref,
        rc.label as current_label,
        rc.fk_facture,
        rc.fk_collaborator,

        -- Libellé facture client (ref_client est le libellé des factures)
        f.ref as facture_client_ref,
        f.ref_client as facture_client_label,

        -- Collaborateur
        c.label as collaborator_name,

        -- Déterminer le nouveau libellé (factures clients uniquement)
        CASE
            WHEN f.ref_client IS NOT NULL AND f.ref_client != '' THEN f.ref_client
            ELSE rc.label
        END as new_label

    FROM ".MAIN_DB_PREFIX."revenuesharing_contract rc
    LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = rc.fk_collaborator
    LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = rc.fk_facture

    WHERE rc.status = 1  -- Seulement les contrats validés
    ORDER BY rc.date_creation DESC";

    $resql = $db->query($sql_analysis);

    if ($resql) {
        $num = $db->num_rows($resql);

        print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">';
        print '<strong>Résultat de l\'analyse : '.$num.' contrats trouvés</strong>';
        print '</div>';

        if ($num > 0) {

            // Statistiques
            $nb_changes_needed = 0;
            $nb_from_client = 0;
            $nb_no_change = 0;
            $nb_no_invoice = 0;

            // Première passe pour les statistiques
            $contracts_data = array();
            while ($obj = $db->fetch_object($resql)) {
                $contracts_data[] = $obj;

                if ($obj->current_label != $obj->new_label) {
                    $nb_changes_needed++;
                    if ($obj->facture_client_label) {
                        $nb_from_client++;
                    }
                } else {
                    if ($obj->fk_facture) {
                        $nb_no_change++;
                    } else {
                        $nb_no_invoice++;
                    }
                }
            }

            // Affichage des statistiques
            print '<div style="display: flex; gap: 15px; margin: 15px 0;">';

            print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; flex: 1;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #856404;">'.$nb_changes_needed.'</div>';
            print '<div style="color: #856404;">Contrats à modifier</div>';
            print '</div>';

            print '<div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 10px; flex: 1;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #0c5460;">'.$nb_from_client.'</div>';
            print '<div style="color: #0c5460;">Depuis factures clients</div>';
            print '</div>';

            print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; flex: 1;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #155724;">'.$nb_no_change.'</div>';
            print '<div style="color: #155724;">Déjà à jour</div>';
            print '</div>';

            print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; flex: 1;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #6c757d;">'.$nb_no_invoice.'</div>';
            print '<div style="color: #6c757d;">Sans facture client</div>';
            print '</div>';

            print '</div>';

            // Tableau détaillé
            print '<table class="tagtable liste" style="width: 100%;">';
            print '<tr class="liste_titre">';
            print '<th>Contrat</th>';
            print '<th>Collaborateur</th>';
            print '<th>Libellé actuel</th>';
            print '<th>Source</th>';
            print '<th>Nouveau libellé</th>';
            print '<th>Statut</th>';
            print '</tr>';

            foreach ($contracts_data as $obj) {
                $needs_change = ($obj->current_label != $obj->new_label);
                $row_class = $needs_change ? 'oddeven' : 'oddeven';
                $row_style = $needs_change ? 'background-color: #fff3cd;' : '';

                print '<tr class="'.$row_class.'" style="'.$row_style.'">';

                // Contrat
                print '<td>';
                print '<a href="../contract_card_complete.php?id='.$obj->rowid.'">';
                print dol_escape_htmltag($obj->ref);
                print '</a>';
                print '</td>';

                // Collaborateur
                print '<td>'.dol_escape_htmltag($obj->collaborator_name).'</td>';

                // Libellé actuel
                print '<td>';
                $current = dol_escape_htmltag($obj->current_label);
                if (strlen($current) > 40) {
                    print substr($current, 0, 37).'...';
                } else {
                    print $current;
                }
                print '</td>';

                // Source
                print '<td>';
                if ($obj->facture_client_label && $obj->facture_client_label == $obj->new_label) {
                    print '<span style="color: #0c5460;"> Facture client</span><br>';
                    print '<small>'.dol_escape_htmltag($obj->facture_client_ref).'</small>';
                } else {
                    print '<span style="color: #6c757d;"> Pas de facture client</span>';
                }
                print '</td>';

                // Nouveau libellé
                print '<td>';
                $new_label = dol_escape_htmltag($obj->new_label);
                if (strlen($new_label) > 40) {
                    print substr($new_label, 0, 37).'...';
                } else {
                    print $new_label;
                }
                print '</td>';

                // Statut
                print '<td>';
                if ($needs_change) {
                    print '<span style="color: #856404; font-weight: bold;">À modifier</span>';
                } else {
                    print '<span style="color: #155724;">OK</span>';
                }
                print '</td>';

                print '</tr>';
            }

            print '</table>';

            // Boutons d'action
            if ($nb_changes_needed > 0) {
                print '<div style="text-align: center; margin: 20px 0;">';
                print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
                print '<input type="hidden" name="token" value="'.newToken().'">';
                print '<input type="hidden" name="action" value="update">';
                print '<input type="hidden" name="mode" value="preview">';
                print '<button type="submit" class="butAction" style="background: #fd7e14; color: white;"> Prévisualiser les modifications</button>';
                print '</form>';

                print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display: inline-block; margin-left: 10px;">';
                print '<input type="hidden" name="token" value="'.newToken().'">';
                print '<input type="hidden" name="action" value="update">';
                print '<input type="hidden" name="mode" value="execute">';
                print '<button type="submit" class="butActionDelete" onclick="return confirm(\'Êtes-vous sûr de vouloir mettre à jour '.$nb_changes_needed.' contrats ?\')">Exécuter les modifications</button>';
                print '</form>';
                print '</div>';
            }

        } else {
            print '<div style="text-align: center; padding: 30px; color: #6c757d;">';
            print '<div style="font-size: 2em;"></div>';
            print '<h3>Aucun contrat trouvé</h3>';
            print '<p>Tous les libellés semblent déjà à jour.</p>';
            print '</div>';
        }

    } else {
        print '<div class="error">Erreur lors de l\'analyse: '.$db->lasterror().'</div>';
    }
}

if ($action == 'update') {

    print '<h2>'.($mode == 'preview' ? 'Prévisualisation' : 'Exécution').' des mises à jour</h2>';

    // Requête de mise à jour (factures clients uniquement)
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract rc
    LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = rc.fk_facture
    SET rc.label = f.ref_client
    WHERE rc.status = 1
    AND f.ref_client IS NOT NULL
    AND f.ref_client != ''
    AND BINARY f.ref_client != BINARY rc.label";

    if ($mode == 'preview') {
        print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin: 10px 0;">';
        print '<strong> Mode prévisualisation activé</strong> - Aucune modification ne sera effectuée en base.';
        print '</div>';

        print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        print '<h4>Requête SQL qui serait exécutée :</h4>';
        print '<pre style="background: #ffffff; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 0.9em;">';
        print htmlspecialchars($sql_update);
        print '</pre>';
        print '</div>';

        print '<div style="text-align: center; margin: 20px 0;">';
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="update">';
        print '<input type="hidden" name="mode" value="execute">';
        print '<button type="submit" class="butAction" style="background: #28a745; color: white;" onclick="return confirm(\'Confirmez-vous l\\\'exécution des mises à jour ?\')">Confirmer et exécuter</button>';
        print '</form>';
        print '</div>';

    } else {
        // Exécution réelle
        $resql_update = $db->query($sql_update);

        if ($resql_update) {
            $nb_updated = $db->affected_rows($resql_update);

            print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 10px 0;">';
            print '<h3 style="margin: 0 0 10px 0; color: #155724;">Mise à jour terminée avec succès !</h3>';
            print '<p style="margin: 0; color: #155724;"><strong>'.$nb_updated.' contrats</strong> ont été mis à jour.</p>';
            print '</div>';

            // Log de l'action
            dol_syslog("Revenue Sharing: Update contract labels - $nb_updated contracts updated by user ".$user->login, LOG_INFO);

        } else {
            print '<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; margin: 10px 0;">';
            print '<h3 style="margin: 0 0 10px 0; color: #721c24;">Erreur lors de la mise à jour</h3>';
            print '<p style="margin: 0; color: #721c24;">Erreur SQL : '.$db->lasterror().'</p>';
            print '</div>';
        }
    }
}

// Interface principale
if (empty($action)) {
    print '<div style="text-align: center; margin: 40px 0;">';
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="analyze">';
    print '<button type="submit" class="butAction" style="background: #007cba; color: white; font-size: 1.2em; padding: 15px 30px;">Analyser les contrats</button>';
    print '</form>';
    print '</div>';
}

print '<div style="text-align: center; margin: 30px 0;">';
print '<a href="setup.php" class="butAction">← Retour à la configuration</a>';
print '</div>';

llxFooter();
$db->close();
?>