<?php
/**
 * Diagnostic des tables de déclarations de salaires
 * Fichier: /htdocs/custom/revenuesharing/admin/diagnostic_salary_tables.php
 */

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Diagnostic des tables salaires');

print load_fiche_titre('Diagnostic des Tables de Déclarations de Salaires', '', 'generic');

print '<div style="margin: 20px 0;">';

// Vérifier l'existence de la table principale
print '<h3>Table principale (revenuesharing_salary_declaration)</h3>';
$sql_check_main = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."revenuesharing_salary_declaration'";
$resql_main = $db->query($sql_check_main);
if ($resql_main && $db->num_rows($resql_main) > 0) {
    print '<div style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;">Table principale existe</div>';
    
    // Vérifier la structure
    $sql_desc_main = "DESCRIBE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration";
    $resql_desc_main = $db->query($sql_desc_main);
    print '<h4>Structure de la table principale :</h4>';
    print '<table border="1" cellpadding="5">';
    print '<tr><th>Champ</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    while ($field = $db->fetch_object($resql_desc_main)) {
        print '<tr>';
        print '<td>'.$field->Field.'</td>';
        print '<td>'.$field->Type.'</td>';
        print '<td>'.$field->Null.'</td>';
        print '<td>'.$field->Key.'</td>';
        print '<td>'.$field->Default.'</td>';
        print '</tr>';
    }
    print '</table>';
    
    // Compter les enregistrements
    $sql_count_main = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration";
    $resql_count_main = $db->query($sql_count_main);
    $count_main = $db->fetch_object($resql_count_main);
    print '<p><strong>Nombre de déclarations :</strong> '.$count_main->total.'</p>';
    
} else {
    print '<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;">Table principale manquante</div>';
}

print '<hr>';

// Vérifier l'existence de la table de détails
print '<h3>Table de détails (revenuesharing_salary_declaration_detail)</h3>';
$sql_check_detail = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail'";
$resql_detail = $db->query($sql_check_detail);
if ($resql_detail && $db->num_rows($resql_detail) > 0) {
    print '<div style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;">Table de détails existe</div>';
    
    // Vérifier la structure
    $sql_desc_detail = "DESCRIBE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail";
    $resql_desc_detail = $db->query($sql_desc_detail);
    print '<h4>Structure de la table de détails :</h4>';
    print '<table border="1" cellpadding="5">';
    print '<tr><th>Champ</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    $has_metier_column = false;
    while ($field = $db->fetch_object($resql_desc_detail)) {
        if ($field->Field == 'metier_son') $has_metier_column = true;
        $style = ($field->Field == 'metier_son') ? 'background-color: #fff3cd;' : '';
        print '<tr style="'.$style.'">';
        print '<td>'.$field->Field.'</td>';
        print '<td>'.$field->Type.'</td>';
        print '<td>'.$field->Null.'</td>';
        print '<td>'.$field->Key.'</td>';
        print '<td>'.$field->Default.'</td>';
        print '</tr>';
    }
    print '</table>';
    
    if ($has_metier_column) {
        print '<div style="color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;">Colonne "metier_son" présente</div>';
    } else {
        print '<div style="color: orange; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">Colonne "metier_son" manquante - Exécuter le script de mise à jour</div>';
    }
    
    // Compter les enregistrements
    $sql_count_detail = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail";
    $resql_count_detail = $db->query($sql_count_detail);
    $count_detail = $db->fetch_object($resql_count_detail);
    print '<p><strong>Nombre de détails journaliers :</strong> '.$count_detail->total.'</p>';
    
} else {
    print '<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;">Table de détails manquante</div>';
}

print '<hr>';

// Test d'insertion
print '<h3>🧪 Test d\'insertion</h3>';
try {
    $test_sql = "SELECT 1";
    $test_resql = $db->query($test_sql);
    if ($test_resql) {
        print '<div style="color: green; background: #d4edda; padding: 10px; border-radius: 5px;">Connexion base de données OK</div>';
        $db->free($test_resql);
    } else {
        print '<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;">Problème connexion base : '.$db->lasterror().'</div>';
    }
} catch (Exception $e) {
    print '<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;">Exception : '.$e->getMessage().'</div>';
}

print '<hr>';

// Vérifier les collaborateurs
print '<h3> Collaborateurs disponibles</h3>';
$sql_collabs = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1";
$resql_collabs = $db->query($sql_collabs);
$count_collabs = $db->fetch_object($resql_collabs);
print '<p><strong>Collaborateurs actifs :</strong> '.$count_collabs->total.'</p>';

if ($count_collabs->total == 0) {
    print '<div style="color: orange; background: #fff3cd; padding: 10px; border-radius: 5px;">Aucun collaborateur actif - Créer des collaborateurs avant les déclarations</div>';
}

print '</div>';

// Actions recommandées
print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 20px; margin: 20px 0;">';
print '<h3 style="color: #2d7d2d; margin-top: 0;">Actions recommandées</h3>';
print '<ol>';

if (!$resql_main || $db->num_rows($resql_main) == 0) {
    print '<li><a href="create_salary_tables.php">Créer les tables de base</a></li>';
}

if (!$has_metier_column) {
    print '<li><a href="update_salary_tables_metiers.php">Ajouter la colonne métiers du son</a></li>';
}

if ($count_collabs->total == 0) {
    print '<li><a href="../collaborator_list.php">Créer des collaborateurs</a></li>';
}

print '<li><a href="../salary_declaration_form.php">Créer une déclaration de test</a></li>';
print '</ol>';
print '</div>';

print '<div class="tabsAction">';
print '<a href="create_salary_tables.php" class="butAction">Créer Tables</a>';
print '<a href="update_salary_tables_metiers.php" class="butAction"> Ajouter Métiers</a>';
print '<a href="../salary_declarations_list.php" class="butAction">Voir Déclarations</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>