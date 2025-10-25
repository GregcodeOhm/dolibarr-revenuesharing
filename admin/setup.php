<?php
// Chemin corrigé pour votre installation
$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';
require_once $dolibarr_main_document_root.'/core/lib/admin.lib.php';

// Load translation files
$langs->load("revenuesharing@revenuesharing");
$langs->load("admin");

// Security check
if (!$user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

if ($action == 'save') {
    $default_percentage = GETPOST('default_percentage', 'int');
    $auto_create = GETPOST('auto_create', 'int');
    $taux_charges = GETPOST('taux_charges', 'alpha');

    dolibarr_set_const($db, "REVENUESHARING_DEFAULT_PERCENTAGE", $default_percentage, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "REVENUESHARING_AUTO_CREATE_CONTRACT", $auto_create, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, "REVENUESHARING_TAUX_CHARGES", $taux_charges, 'chaine', 0, '', $conf->entity);

    setEventMessages("Configuration sauvegardée", null, 'mesgs');
}

llxHeader('', 'Revenue Sharing - Configuration', '');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">Retour à la liste des modules</a>';
print load_fiche_titre('Configuration Revenue Sharing', $linkback, 'generic');

// Configuration form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>Paramètre</td>';
print '<td>Valeur</td>';
print '</tr>';

// Default percentage
print '<tr class="oddeven">';
print '<td>Pourcentage par défaut (%)</td>';
print '<td>';
print '<input type="number" name="default_percentage" value="'.(empty($conf->global->REVENUESHARING_DEFAULT_PERCENTAGE) ? 60 : $conf->global->REVENUESHARING_DEFAULT_PERCENTAGE).'" min="0" max="100"> %';
print '</td>';
print '</tr>';

// Auto create contract
print '<tr class="oddeven">';
print '<td>Création automatique des contrats</td>';
print '<td>';
print '<input type="checkbox" name="auto_create" value="1"'.(!empty($conf->global->REVENUESHARING_AUTO_CREATE_CONTRACT) ? ' checked' : '').'>';
print '</td>';
print '</tr>';

// Taux de charges sociales
print '<tr class="oddeven">';
print '<td>Taux de charges sociales pour le calcul de la masse salariale (%)</td>';
print '<td>';
print '<input type="number" name="taux_charges" value="'.(empty($conf->global->REVENUESHARING_TAUX_CHARGES) ? 65 : $conf->global->REVENUESHARING_TAUX_CHARGES).'" min="0" max="100" step="0.01"> %';
print '<div style="font-size: 0.9em; color: #666; margin-top: 5px;">Ce taux sera utilisé pour calculer automatiquement la masse salariale employeur (salaire brut × (1 + taux/100))</div>';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="button" value="Sauvegarder">';
print '</div>';

print '</form>';

// Section informative
print '<br><br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">Informations du module</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Version</td>';
print '<td>1.0.0</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Statut</td>';
print '<td><span class="badge badge-status4 badge-status">Activé</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Chemin Dolibarr détecté</td>';
print '<td><code>'.$dolibarr_main_document_root.'</code></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Tables de base</td>';
print '<td>';
$tables = ['llx_revenuesharing_collaborator', 'llx_revenuesharing_contract'];
foreach ($tables as $table) {
    $sql = "SHOW TABLES LIKE '$table'";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        print "$table<br>";
    } else {
        print "$table (à créer)<br>";
    }
}
print '</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '<a href="../collaborator_list.php" class="butAction">Collaborateurs</a>';
print '<a href="edit_extrafields.php" class="butAction" style="background: #17a2b8; color: white;"> Éditer Extrafields</a>';
print '<a href="sync_contracts_to_accounts.php" class="butAction" style="background: #28a745; color: white;">Sync Contrats</a>';
print '<a href="update_contract_labels.php" class="butAction" style="background: #fd7e14; color: white;">MAJ Libellés</a>';
print '<a href="update_account_tables_supplier.php" class="butAction" style="background: #6f42c1; color: white;">MAJ Tables</a>';
print '</div>';

llxFooter();
$db->close();
?>
