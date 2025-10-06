<?php
// Script de nettoyage des tables - suppression des colonnes supplémentaires

$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Admin only');
}

llxHeader('', 'Nettoyage Tables Revenue Sharing', '');

print load_fiche_titre('🧹 Nettoyage Tables Revenue Sharing', '', 'generic');

print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 15px 0;">';
print '<h3>Attention</h3>';
print '<p>Cette opération va supprimer les colonnes supplémentaires de la table des contrats.</p>';
print '<p><strong>Sauvegardez votre base de données avant de continuer !</strong></p>';
print '</div>';

$action = GETPOST('action', 'alpha');

if ($action == 'clean') {
    print '<h3>Nettoyage en cours...</h3>';
    
    $table_name = MAIN_DB_PREFIX.'revenuesharing_contract';
    $columns_to_remove = array(
        'date_contract',
        'date_prestation', 
        'type_prestation',
        'tva_rate',
        'payment_status',
        'date_payment',
        'fk_propal' // Si elle existe aussi
    );
    
    $results = array();
    
    // Vérifier d'abord quelles colonnes existent
    print '<h4>Vérification des colonnes existantes</h4>';
    $sql_check = "SHOW COLUMNS FROM ".$table_name;
    $resql = $db->query($sql_check);
    
    $existing_columns = array();
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $existing_columns[] = $obj->Field;
        }
    }
    
    print '<p>Colonnes actuelles : <code>'.implode(', ', $existing_columns).'</code></p>';
    
    // Supprimer les colonnes qui existent et qu'on ne veut pas
    print '<h4> Suppression des colonnes</h4>';
    
    foreach ($columns_to_remove as $column) {
        if (in_array($column, $existing_columns)) {
            $sql_drop = "ALTER TABLE ".$table_name." DROP COLUMN ".$column;
            
            if ($db->query($sql_drop)) {
                $results[] = "Colonne '$column' supprimée avec succès";
            } else {
                $results[] = "Erreur lors de la suppression de '$column': ".$db->lasterror();
            }
        } else {
            $results[] = "Colonne '$column' n'existe pas (OK)";
        }
    }
    
    // Afficher les résultats
    foreach ($results as $result) {
        print '<p>'.$result.'</p>';
    }
    
    // Vérifier le résultat final
    print '<h4>Vérification finale</h4>';
    $sql_final = "SHOW COLUMNS FROM ".$table_name;
    $resql_final = $db->query($sql_final);
    
    $final_columns = array();
    if ($resql_final) {
        while ($obj = $db->fetch_object($resql_final)) {
            $final_columns[] = $obj->Field;
        }
    }
    
    print '<p>Colonnes finales : <code>'.implode(', ', $final_columns).'</code></p>';
    
    // Ajouter la colonne fk_propal si elle n'existe pas
    if (!in_array('fk_propal', $final_columns)) {
        $sql_add = "ALTER TABLE ".$table_name." ADD COLUMN fk_propal INTEGER AFTER fk_facture";
        if ($db->query($sql_add)) {
            print '<p>Colonne fk_propal ajoutée</p>';
        } else {
            print '<p>Erreur ajout fk_propal: '.$db->lasterror().'</p>';
        }
    }
    
    print '<div class="center" style="margin: 20px;">';
    print '<a href="'.$_SERVER['PHP_SELF'].'" class="butAction">Rafraîchir</a>';
    print '<a href="../diagnostic_table.php" class="butAction">Voir diagnostic</a>';
    print '</div>';
    
} else {
    // Formulaire de confirmation
    print '<form method="POST">';
    print '<input type="hidden" name="action" value="clean">';
    print '<div class="center">';
    print '<input type="submit" class="button" value="🧹 Nettoyer les tables" onclick="return confirm(\'Êtes-vous sûr ? Sauvegardez d\\\'abord !\');">';
    print '</div>';
    print '</form>';
    
    print '<div class="center" style="margin: 20px;">';
    print '<a href="../diagnostic_table.php" class="butAction">Voir diagnostic actuel</a>';
    print '</div>';
}

llxFooter();
$db->close();
?>