<?php
/**
 * Script de mise à jour pour ajouter le champ nb_heures
 * Fichier: /htdocs/custom/revenuesharing/admin/update_salary_tables_heures.php
 */

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Mise à jour tables - Ajout heures');

print load_fiche_titre('⏰ Mise à jour des tables - Ajout du nombre d\'heures', '', 'generic');

print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #856404;">⏱️ Nombre d\'heures par déclaration</h4>';
print '<p style="margin: 0;">Ajout du champ <strong>nb_heures</strong> dans la table des détails pour une meilleure précision des déclarations.</p>';
print '<p style="margin: 5px 0 0 0;"><strong>Défaut :</strong> 8 heures par jour (journée standard intermittent).</p>';
print '</div>';

// Ajouter le champ nb_heures à la table des détails
$sql_add_heures = "ALTER TABLE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail 
                   ADD COLUMN nb_heures DECIMAL(5,2) DEFAULT 8.00 AFTER nb_cachets";

print '<div style="margin: 20px 0;">';

print '<h3>Ajout du champ nb_heures dans la table des détails</h3>';
$resql_add = $db->query($sql_add_heures);
if ($resql_add) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">Champ "nb_heures" ajouté avec succès (défaut: 8.00 heures)</div>';
} else {
    $error = $db->lasterror();
    if (strpos($error, 'Duplicate column name') !== false || strpos($error, 'already exists') !== false) {
        print '<div style="color: orange; padding: 10px; background: #fff3cd; border-radius: 5px; margin: 10px 0;">Le champ "nb_heures" existe déjà dans la table</div>';
    } else {
        print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">Erreur lors de l\'ajout du champ: ' . $error . '</div>';
    }
}

print '<h3>Exemples de durées de travail courantes</h3>';
print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 15px;">';
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">';

$durees_exemples = array(
    '4.00' => '🌅 Demi-journée (4h)',
    '8.00' => 'Journée standard (8h)',
    '10.00' => 'Journée longue (10h)',
    '12.00' => '🌙 Journée très longue (12h)',
    '6.00' => '⏰ Matinée/Soirée (6h)',
    '2.00' => '⚡ Prestation courte (2h)'
);

foreach ($durees_exemples as $heures => $description) {
    print '<div style="background: white; padding: 8px; border-radius: 4px; border-left: 3px solid #2d7d2d;">' . $description . '</div>';
}

print '</div>';
print '</div>';

print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 20px; margin: 20px 0;">';
print '<h3 style="color: #2d7d2d; margin-top: 0;">🎉 Mise à jour terminée !</h3>';
print '<p><strong>Nouveautés :</strong></p>';
print '<ul>';
print '<li>Champ "nb_heures" ajouté (DECIMAL 5,2 - ex: 8.50)</li>';
print '<li>Valeur par défaut : 8.00 heures (journée standard)</li>';
print '<li>Calcul automatique de la masse salariale selon heures × jours</li>';
print '<li>Export PDF avec indication des heures travaillées</li>';
print '</ul>';
print '<p><strong>Avantages :</strong></p>';
print '<ul>';
print '<li>Précision des déclarations (demi-journées, heures sup.)</li>';
print '<li>Calculs automatiques plus justes</li>';
print '<li>Conformité avec les durées réelles de travail</li>';
print '<li>Meilleur suivi pour le gestionnaire de paie</li>';
print '</ul>';
print '</div>';

print '</div>';

print '<div class="tabsAction">';
print '<a href="../salary_declarations_list.php" class="butAction">Voir les déclarations</a>';
print '<a href="../salary_declaration_form.php" class="butAction"> Nouvelle déclaration</a>';
print '<a href="diagnostic_salary_tables.php" class="butAction">Diagnostic</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>