<?php
/**
 * Test d'insertion pour diagnostiquer l'erreur SQL
 * Fichier: /htdocs/custom/revenuesharing/admin/test_insertion_salary.php
 */

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Test insertion déclaration');

print load_fiche_titre('🧪 Test d\'insertion - Diagnostic SQL', '', 'generic');

print '<div style="margin: 20px 0;">';

// Test avec données factices
$test_collaborator_id = 1;
$test_month = 12;
$test_year = 2024;
$test_days = 5;
$test_cachet = 150.00;
$test_total = $test_cachet * $test_days;
$test_masse = $test_total * 1.55;
$test_solde = $test_total;
$test_note = 'Test insertion';

print '<h3>Test d\'insertion avec données factices</h3>';
print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">';
print '<strong>Données de test :</strong><br>';
print 'Collaborateur ID: '.$test_collaborator_id.'<br>';
print 'Mois: '.$test_month.'<br>';
print 'Année: '.$test_year.'<br>';
print 'Jours: '.$test_days.'<br>';
print 'Cachet unitaire: '.$test_cachet.' €<br>';
print 'Total cachets: '.$test_total.' €<br>';
print 'Masse salariale: '.$test_masse.' €<br>';
print 'Solde utilisé: '.$test_solde.' €<br>';
print 'Note: '.$test_note.'<br>';
print '</div>';

// Construction de la requête
$sql_test = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
        (fk_collaborator, declaration_month, declaration_year, total_days, total_cachets, 
         cachet_brut_unitaire, masse_salariale, solde_utilise, note_private, fk_user_creat)
        VALUES (".(int)$test_collaborator_id.", ".(int)$test_month.", ".(int)$test_year.", ".$test_days.", 
               ".(float)$test_total.", ".(float)$test_cachet.", 
               ".(float)$test_masse.", ".(float)$test_solde.", '".$db->escape($test_note)."', ".(int)$user->id.")";

print '<h4>Requête SQL générée :</h4>';
print '<div style="background: #f1f1f1; padding: 10px; font-family: monospace; border-radius: 3px; white-space: pre-wrap;">';
print htmlspecialchars($sql_test);
print '</div>';

// Test de la requête (sans l'exécuter vraiment)
print '<h4>Validation syntaxe :</h4>';

// Vérifier que la table existe
$sql_check_table = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."revenuesharing_salary_declaration'";
$resql_table = $db->query($sql_check_table);
if ($resql_table && $db->num_rows($resql_table) > 0) {
    print '<div style="color: green;">Table existe</div>';
    
    // Vérifier la structure de la table
    $sql_desc = "DESCRIBE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration";
    $resql_desc = $db->query($sql_desc);
    
    print '<h4>🏗️ Structure de la table :</h4>';
    print '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    print '<tr style="background: #f5f5f5;"><th>Champ</th><th>Type</th><th>Null</th><th>Default</th></tr>';
    
    $required_fields = array(
        'fk_collaborator', 'declaration_month', 'declaration_year', 'total_days', 
        'total_cachets', 'cachet_brut_unitaire', 'masse_salariale', 'solde_utilise', 
        'note_private', 'fk_user_creat'
    );
    $existing_fields = array();
    
    while ($field = $db->fetch_object($resql_desc)) {
        $existing_fields[] = $field->Field;
        $is_required = in_array($field->Field, $required_fields);
        $style = $is_required ? 'background-color: #d4edda;' : '';
        
        print '<tr style="'.$style.'">';
        print '<td><strong>'.$field->Field.'</strong></td>';
        print '<td>'.$field->Type.'</td>';
        print '<td>'.$field->Null.'</td>';
        print '<td>'.$field->Default.'</td>';
        print '</tr>';
    }
    print '</table>';
    
    // Vérifier les champs manquants
    $missing_fields = array_diff($required_fields, $existing_fields);
    if (!empty($missing_fields)) {
        print '<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;">';
        print 'Champs manquants : '.implode(', ', $missing_fields);
        print '</div>';
    } else {
        print '<div style="color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;">';
        print 'Tous les champs requis sont présents';
        print '</div>';
    }
    
} else {
    print '<div style="color: red;">Table n\'existe pas</div>';
}

// Test de la requête avec EXPLAIN (sans insertion)
print '<h4>🔬 Test syntaxe SQL :</h4>';
try {
    // Remplacer INSERT par SELECT pour tester la syntaxe
    $test_syntax = str_replace('INSERT INTO', 'SELECT * FROM', $sql_test);
    $test_syntax = preg_replace('/\([^)]+\)\s*VALUES\s*\([^)]+\)/', 'WHERE 1=0', $test_syntax);
    
    $resql_syntax = $db->query($test_syntax);
    if ($resql_syntax) {
        print '<div style="color: green;">Syntaxe SQL valide</div>';
        $db->free($resql_syntax);
    } else {
        print '<div style="color: red;">Erreur syntaxe : '.$db->lasterror().'</div>';
    }
} catch (Exception $e) {
    print '<div style="color: red;">Exception : '.$e->getMessage().'</div>';
}

print '</div>';

print '<div class="tabsAction">';
print '<a href="create_salary_tables.php" class="butAction">Créer Tables</a>';
print '<a href="diagnostic_salary_tables.php" class="butAction">Diagnostic</a>';
print '<a href="../salary_declaration_form.php" class="butAction">Formulaire</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>