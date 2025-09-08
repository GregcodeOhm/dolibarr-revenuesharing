<?php
// Chemin corrigé pour votre installation
$dolibarr_main_document_root = '/home/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

// Load translation files
$langs->load("revenuesharing@revenuesharing");

// Security check modifiée - accepter les admins
if (!$user->id) {
    accessforbidden();
}

// Vérification alternative des permissions
$has_permission = false;
if ($user->admin) {
    $has_permission = true; // Admin a tous les droits
} elseif (isset($user->rights->revenuesharing) && $user->rights->revenuesharing->read) {
    $has_permission = true; // Permission spécifique
}

if (!$has_permission) {
    accessforbidden('Accès au module Revenue Sharing non autorisé');
}

// Parameters
$year = GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y');

llxHeader('', 'Revenue Sharing', '');

print load_fiche_titre('🏠 Revenue Sharing Dashboard', '', 'generic');

// Message de bienvenue
print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 15px 0;">';
print '<h3 style="margin: 0; color: #155724;">✅ Module Revenue Sharing Opérationnel</h3>';
print '<p style="margin: 5px 0 0 0;">Bienvenue '.$user->getFullName($langs).' ! Le module fonctionne correctement.</p>';
print '</div>';

// Statistiques générales
$sql = "SELECT COUNT(*) as nb_collaborators FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1";
$resql = $db->query($sql);
$nb_collaborators = 0;
if ($resql) {
    $obj = $db->fetch_object($resql);
    $nb_collaborators = $obj->nb_collaborators;
}

$sql = "SELECT COUNT(*) as nb_contracts,
               COALESCE(SUM(amount_ht), 0) as total_ht,
               COALESCE(SUM(net_collaborator_amount), 0) as total_collaborator";
$sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract";
$sql .= " WHERE status >= 1 AND YEAR(date_creation) = ".$year;
$resql = $db->query($sql);
$stats = array('nb_contracts' => 0, 'total_ht' => 0, 'total_collaborator' => 0);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $stats = array(
        'nb_contracts' => $obj->nb_contracts ? $obj->nb_contracts : 0,
        'total_ht' => $obj->total_ht ? $obj->total_ht : 0,
        'total_collaborator' => $obj->total_collaborator ? $obj->total_collaborator : 0
    );
}

print '<div class="fichecenter">';

// Boxes statistiques
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre">';
print '<th class="center">👥 Collaborateurs</th>';
print '<th class="center">📄 Contrats '.$year.'</th>';
print '<th class="center">💰 CA Total '.$year.'</th>';
print '<th class="center">🤝 Part Collaborateurs '.$year.'</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="center">';
print '<a href="collaborator_list.php" style="text-decoration: none;">';
print '<div style="font-size: 2em; color: #007cba;">👥</div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #007cba;">'.$nb_collaborators.'</div>';
print '<div>Collaborateurs actifs</div>';
print '</a>';
print '</td>';

print '<td class="center">';
print '<a href="contract_list.php?year='.$year.'" style="text-decoration: none;">';
print '<div style="font-size: 2em; color: #28a745;">📄</div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #28a745;">'.$stats['nb_contracts'].'</div>';
print '<div>Contrats validés</div>';
print '</a>';
print '</td>';

print '<td class="center">';
print '<div style="font-size: 2em; color: #dc3545;">💰</div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #dc3545;">'.price($stats['total_ht']).'</div>';
print '<div>Chiffre d\'affaires</div>';
print '</td>';

print '<td class="center">';
print '<div style="font-size: 2em; color: #ffc107;">🤝</div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #ffc107;">'.price($stats['total_collaborator']).'</div>';
print '<div>Revenus collaborateurs</div>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

// Actions rapides
print '<br>';
print '<div class="center">';
print '<a href="collaborator_list.php" class="butAction">👥 Voir les collaborateurs</a> ';
print '<a href="contract_list.php" class="butAction">📄 Voir les contrats</a> ';
print '<a href="admin/setup.php" class="butAction">⚙️ Configuration</a>';
print '</div>';

// Informations système
print '<br><br>';
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px;">';
print '<h4>ℹ️ Informations Système</h4>';
print '<table class="noborder">';
print '<tr><td><strong>Utilisateur :</strong></td><td>'.$user->login.' ('.$user->getFullName($langs).')</td></tr>';
print '<tr><td><strong>Droits admin :</strong></td><td>'.($user->admin ? '✅ Oui' : '❌ Non').'</td></tr>';
print '<tr><td><strong>Entité :</strong></td><td>'.$conf->entity.'</td></tr>';
print '<tr><td><strong>Version Dolibarr :</strong></td><td>'.DOL_VERSION.'</td></tr>';
print '<tr><td><strong>Module activé :</strong></td><td>'.(!empty($conf->revenuesharing->enabled) ? '✅ Oui' : '❌ Non').'</td></tr>';
print '</table>';
print '</div>';

print '</div>';

llxFooter();
$db->close();
?>
