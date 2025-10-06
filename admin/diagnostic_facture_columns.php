<?php
/**
 * Script de diagnostic pour identifier les colonnes des factures
 */

// Chemin corrigé pour votre installation
$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Diagnostic Colonnes Factures', '');

print '<h1>Diagnostic des colonnes de factures</h1>';

// Récupérer un échantillon de factures pour voir les colonnes disponibles
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."facture LIMIT 1";
$resql = $db->query($sql);

if ($resql) {
    print '<h2>Colonnes disponibles dans la table facture :</h2>';
    print '<table class="tagtable liste">';
    print '<tr class="liste_titre"><th>Nom de colonne</th><th>Type</th></tr>';

    $num_fields = $db->num_fields($resql);
    for ($i = 0; $i < $num_fields; $i++) {
        $field_name = $db->field_name($resql, $i);
        $field_type = $db->field_type($resql, $i);
        print '<tr class="oddeven">';
        print '<td><strong>'.$field_name.'</strong></td>';
        print '<td>'.$field_type.'</td>';
        print '</tr>';
    }
    print '</table>';

    $db->free($resql);
} else {
    print '<div class="error">Erreur: '.$db->lasterror().'</div>';
}

// Tester quelques factures avec les colonnes possibles
$sql_test = "SELECT ref, note_public, note_private, ref_client, facnumber FROM ".MAIN_DB_PREFIX."facture WHERE ref IS NOT NULL LIMIT 5";
$resql_test = $db->query($sql_test);

if ($resql_test) {
    print '<h2>Échantillon de factures :</h2>';
    print '<table class="tagtable liste">';
    print '<tr class="liste_titre">';
    print '<th>Référence</th>';
    print '<th>Note publique</th>';
    print '<th>Note privée</th>';
    print '<th>Réf client</th>';
    print '<th>Numéro facture</th>';
    print '</tr>';

    while ($obj = $db->fetch_object($resql_test)) {
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($obj->ref).'</td>';
        print '<td>'.dol_escape_htmltag($obj->note_public).'</td>';
        print '<td>'.dol_escape_htmltag($obj->note_private).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ref_client).'</td>';
        print '<td>'.dol_escape_htmltag($obj->facnumber).'</td>';
        print '</tr>';
    }
    print '</table>';

    $db->free($resql_test);
}

print '<div style="margin-top: 20px;">';
print '<a href="update_contract_labels.php" class="butAction">← Retour au script de mise à jour</a>';
print '</div>';

llxFooter();
$db->close();
?>