<?php
/**
 * Script de mise à jour des tables pour ajouter les métiers du son IDCC 2642
 * Fichier: /htdocs/custom/revenuesharing/admin/update_salary_tables_metiers.php
 */

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Mise à jour tables - Métiers du son');

print load_fiche_titre(' Mise à jour des tables - Ajout des métiers du son IDCC 2642', '', 'generic');

print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #856404;">Convention Collective IDCC 2642</h4>';
print '<p style="margin: 0;"><strong>Production audiovisuelle :</strong> Convention collective nationale de la production audiovisuelle.</p>';
print '<p style="margin: 5px 0 0 0;">Ajout des métiers spécifiques au son pour les déclarations d\'intermittents.</p>';
print '</div>';

// Ajouter le champ métier à la table des détails
$sql_add_metier = "ALTER TABLE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail 
                   ADD COLUMN metier_son VARCHAR(100) DEFAULT NULL AFTER type_contrat";

print '<div style="margin: 20px 0;">';

print '<h3>Ajout du champ métier dans la table des détails</h3>';
$resql_add = $db->query($sql_add_metier);
if ($resql_add) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">Champ "metier_son" ajouté avec succès à la table revenuesharing_salary_declaration_detail</div>';
} else {
    $error = $db->lasterror();
    if (strpos($error, 'Duplicate column name') !== false || strpos($error, 'already exists') !== false) {
        print '<div style="color: orange; padding: 10px; background: #fff3cd; border-radius: 5px; margin: 10px 0;">Le champ "metier_son" existe déjà dans la table</div>';
    } else {
        print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">Erreur lors de l\'ajout du champ: ' . $error . '</div>';
    }
}

print '<h3>Liste des métiers du son disponibles (IDCC 2642)</h3>';
print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 15px;">';
print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">';

$metiers_son = array(
    'Assistant son',
    'Chef opérateur du son',
    'Ingénieur du son',
    'Mixeur',
    'Opérateur de prise de son',
    'Perchman',
    'Preneur de son',
    'Régisseur son',
    'Sound designer',
    'Technicien antenne',
    'Technicien audionumérique',
    'Technicien son',
    'Technicien sonorisation'
);

foreach ($metiers_son as $metier) {
    print '<div style="background: white; padding: 8px; border-radius: 4px; border-left: 3px solid #2d7d2d;"> ' . $metier . '</div>';
}

print '</div>';
print '</div>';

print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 20px; margin: 20px 0;">';
print '<h3 style="color: #2d7d2d; margin-top: 0;">🎉 Mise à jour terminée !</h3>';
print '<p><strong>Nouveautés :</strong></p>';
print '<ul>';
print '<li>Champ "métier du son" ajouté dans les déclarations journalières</li>';
print '<li>13 métiers du son selon la convention IDCC 2642</li>';
print '<li>Menu déroulant disponible dans le formulaire de déclaration</li>';
print '<li>Export PDF avec indication du métier</li>';
print '</ul>';
print '<p><strong>Utilisation :</strong></p>';
print '<ul>';
print '<li>Créer une nouvelle déclaration de salaires</li>';
print '<li>Sélectionner le métier pour chaque jour de travail</li>';
print '<li>Le métier apparaît dans l\'export PDF pour le gestionnaire de paie</li>';
print '</ul>';
print '</div>';

print '</div>';

print '<div class="tabsAction">';
print '<a href="../salary_declarations_list.php" class="butAction">Voir les déclarations</a>';
print '<a href="../salary_declaration_form.php" class="butAction"> Nouvelle déclaration</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>