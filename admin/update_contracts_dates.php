<?php
/**
 * Script pour ajouter les champs de dates prévisionnelles
 * Fichier: /htdocs/custom/revenuesharing/admin/update_contracts_dates.php
 */

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Mise à jour contrats - Dates prévisionnelles');

print load_fiche_titre('Mise à jour des contrats - Dates prévisionnelles', '', 'generic');

print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #2d7d2d;">Dates prévisionnelles</h4>';
print '<p style="margin: 0;">Ajout des champs pour gérer les dates de prestation et facturation prévues.</p>';
print '<p style="margin: 5px 0 0 0;"><strong>Avantage :</strong> Transformation facile des prévisionnels en contrats réels</p>';
print '</div>';

// Ajouter les champs de dates
$updates = array(
    array(
        'field' => 'date_prestation_prevue',
        'sql' => "ALTER TABLE ".MAIN_DB_PREFIX."revenuesharing_contract 
                  ADD COLUMN date_prestation_prevue DATE NULL AFTER date_valid",
        'description' => 'Date de prestation prévue'
    ),
    array(
        'field' => 'date_facturation_prevue', 
        'sql' => "ALTER TABLE ".MAIN_DB_PREFIX."revenuesharing_contract 
                  ADD COLUMN date_facturation_prevue DATE NULL AFTER date_prestation_prevue",
        'description' => 'Date de facturation prévue'
    )
);

print '<div style="margin: 20px 0;">';

foreach ($updates as $update) {
    print '<h3>Ajout du champ '.$update['field'].'</h3>';
    $resql = $db->query($update['sql']);
    if ($resql) {
        print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">Champ "'.$update['field'].'" ajouté avec succès</div>';
    } else {
        $error = $db->lasterror();
        if (strpos($error, 'Duplicate column name') !== false || strpos($error, 'already exists') !== false) {
            print '<div style="color: orange; padding: 10px; background: #fff3cd; border-radius: 5px; margin: 10px 0;">Le champ "'.$update['field'].'" existe déjà</div>';
        } else {
            print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">Erreur: ' . $error . '</div>';
        }
    }
}

print '<h3>Utilisation des dates prévisionnelles</h3>';
print '<div style="background: #f0f8f0; border: 1px solid #c3e6c3; border-radius: 8px; padding: 15px;">';
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';

print '<div style="background: white; padding: 12px; border-radius: 6px; border-left: 4px solid #007cba;">';
print '<h4 style="margin: 0 0 8px 0; color: #007cba;">Date de prestation</h4>';
print '<ul style="margin: 0; padding-left: 20px; color: #666;">';
print '<li>Planification du calendrier</li>';
print '<li>Suivi des échéances</li>';
print '<li>Organisation des équipes</li>';
print '</ul>';
print '</div>';

print '<div style="background: white; padding: 12px; border-radius: 6px; border-left: 4px solid #28a745;">';
print '<h4 style="margin: 0 0 8px 0; color: #28a745;">Date de facturation</h4>';
print '<ul style="margin: 0; padding-left: 20px; color: #666;">';
print '<li>Planification de trésorerie</li>';
print '<li>Rappels automatiques</li>';
print '<li>Suivi des échéances client</li>';
print '</ul>';
print '</div>';

print '</div>';
print '</div>';

print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 20px; margin: 20px 0;">';
print '<h3 style="color: #2d7d2d; margin-top: 0;">🎉 Mise à jour terminée !</h3>';
print '<p><strong>Nouveaux champs :</strong></p>';
print '<ul>';
print '<li>date_prestation_prevue (DATE, nullable)</li>';
print '<li>date_facturation_prevue (DATE, nullable)</li>';
print '</ul>';
print '<p><strong>Workflow contrats :</strong></p>';
print '<ol>';
print '<li><strong>Création prévisionnel</strong> : Avec dates estimées</li>';
print '<li><strong>Association facture</strong> : Transformation en contrat réel</li>';
print '<li><strong>Validation</strong> : Dates définitives enregistrées</li>';
print '</ol>';
print '</div>';

print '</div>';

print '<div class="tabsAction">';
print '<a href="contract_previsionnel_form.php" class="butAction"> Nouveau contrat prévisionnel</a>';
print '<a href="../contract_list.php" class="butAction">Voir les contrats</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>