<?php
// Fichier: admin/update_account_tables_supplier.php
// Mise à jour des tables pour lier les factures fournisseurs aux débits

$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Mise à jour Tables - Factures Fournisseurs', '');

print load_fiche_titre('Mise à jour des Tables - Liaison Factures Fournisseurs', '', 'generic');

$success = 0;
$errors = 0;

// Ajouter la colonne pour les factures fournisseurs
print '<h3>Ajout colonne facture fournisseur</h3>';

$sql_add_column = "ALTER TABLE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
    ADD COLUMN fk_facture_fourn INT NULL AFTER fk_facture,
    ADD INDEX idx_facture_fourn (fk_facture_fourn)";

print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; font-family: monospace; font-size: 0.9em;">';
print nl2br(htmlentities($sql_add_column));
print '</div>';

$resql_add = $db->query($sql_add_column);
if ($resql_add) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">';
    print 'Colonne "fk_facture_fourn" ajoutée avec succès';
    print '</div>';
    $success++;
} else {
    $error_msg = $db->lasterror();
    if (strpos($error_msg, 'Duplicate column name') !== false) {
        print '<div style="color: orange; padding: 10px; background: #fff3cd; border-radius: 5px; margin: 10px 0;">';
        print 'La colonne "fk_facture_fourn" existe déjà - Pas de modification nécessaire';
        print '</div>';
        $success++;
    } else {
        print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">';
        print 'Erreur lors de l\'ajout de la colonne: '.$error_msg;
        print '</div>';
        $errors++;
    }
}

// Ajouter le type 'salary' aux types d'opération
print '<h3>Mise à jour des types d\'opération</h3>';

$sql_check_enum = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction WHERE Field = 'transaction_type'";
$resql_check = $db->query($sql_check_enum);

if ($resql_check) {
    $column_info = $db->fetch_object($resql_check);
    print '<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    print '<h4>Types d\'opération actuels :</h4>';
    print '<div style="font-family: monospace; background: white; padding: 10px; border-radius: 3px;">';
    print $column_info->Type;
    print '</div>';
    
    // Vérifier si 'salary' existe déjà
    if (strpos($column_info->Type, 'salary') === false) {
        print '<br><strong>Ajout du type "salary" pour les salaires...</strong>';
        
        $sql_add_salary = "ALTER TABLE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
            MODIFY COLUMN transaction_type ENUM('contract','commission','bonus','interest','advance','fee','refund','adjustment','salary','other_credit','other_debit') NOT NULL";
        
        $resql_add_salary = $db->query($sql_add_salary);
        if ($resql_add_salary) {
            print '<div style="color: green; margin-top: 10px;">Type "salary" ajouté avec succès</div>';
            $success++;
        } else {
            print '<div style="color: red; margin-top: 10px;">Erreur lors de l\'ajout du type "salary": '.$db->lasterror().'</div>';
            $errors++;
        }
    } else {
        print '<br><strong>Le type "salary" est déjà disponible.</strong>';
    }
    
    print '</div>';
    $db->free($resql_check);
}

// Test de la nouvelle fonctionnalité
print '<h3>🧪 Test de la nouvelle fonctionnalité</h3>';

$sql_test = "SELECT 
    COUNT(*) as nb_transactions,
    COUNT(fk_facture_fourn) as nb_with_supplier,
    COUNT(CASE WHEN transaction_type IN ('advance','fee','refund','adjustment','other_debit') THEN 1 END) as nb_debits
    FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction";

$resql_test = $db->query($sql_test);
if ($resql_test) {
    $test_result = $db->fetch_object($resql_test);
    
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    print '<h4>Statistiques actuelles :</h4>';
    print '<ul>';
    print '<li><strong>Total transactions :</strong> '.$test_result->nb_transactions.'</li>';
    print '<li><strong>Transactions avec facture fournisseur :</strong> '.$test_result->nb_with_supplier.'</li>';
    print '<li><strong>Transactions débit :</strong> '.$test_result->nb_debits.'</li>';
    print '</ul>';
    print '</div>';
    
    $db->free($resql_test);
}

// Résumé
print '<div style="background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0;">';
print '<h4>Résumé de la mise à jour</h4>';
print '<ul>';
print '<li><strong>Modifications réussies :</strong> '.$success.'</li>';
print '<li><strong>Erreurs rencontrées :</strong> '.$errors.'</li>';
print '</ul>';

if ($errors == 0) {
    print '<div style="color: green; font-weight: bold; margin-top: 15px;">';
    print '🎉 Mise à jour terminée ! Vous pouvez maintenant :';
    print '<ul style="margin-top: 10px;">';
    print '<li>Lier des factures fournisseurs aux opérations de débit</li>';
    print '<li>Récupérer automatiquement les montants HT des factures</li>';
    print '<li>Modifier les montants si nécessaire</li>';
    print '<li>Garder une traçabilité complète</li>';
    print '</ul>';
    print '</div>';
    
    print '<div style="text-align: center; margin-top: 20px;">';
    print '<a href="../account_transaction.php" class="button" style="background: #28a745; color: white;"> Tester nouvelle opération</a> ';
    print '<a href="../account_list.php" class="button" style="background: #17a2b8; color: white;">Voir les comptes</a>';
    print '</div>';
}

print '</div>';

print '<div class="tabsAction">';
print '<a href="setup.php" class="butAction">Configuration</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>