<?php
// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';
require_once __DIR__.'/lib/revenuesharing.lib.php';
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';
require_once __DIR__.'/class/repositories/ContractRepository.php';
require_once __DIR__.'/class/CacheManager.php';

// Load translation files
$langs->load("revenuesharing@revenuesharing");

// Initialiser repositories et cache
$cache = new CacheManager(null, 600, true); // 10min pour dashboard
$collaboratorRepo = new CollaboratorRepository($db);
$contractRepo = new ContractRepository($db);

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
$filter_collaborator = GETPOST('filter_collaborator', 'int');

llxHeader('', 'Revenue Sharing', '');

// Load custom CSS
print '<link rel="stylesheet" type="text/css" href="'.dol_buildpath('/revenuesharing/css/revenuesharing.css', 1).'">';

print load_fiche_titre('Revenue Sharing Dashboard', '', 'generic');

// Message de bienvenue
print '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 15px 0;">';
print '<h3 style="margin: 0; color: #155724;">Module Revenue Sharing Opérationnel</h3>';
print '<p style="margin: 5px 0 0 0;">Bienvenue '.$user->getFullName($langs).' ! Le module fonctionne correctement.</p>';
print '</div>';

// Statistiques générales (via repositories avec cache)
try {
    // 1. Nombre de collaborateurs
    $nb_collaborators = $collaboratorRepo->count(['active' => 1]);

    // 2. Statistiques des contrats pour l'année avec cache
    $cacheKey = "dashboard_stats_{$year}_{$filter_collaborator}";
    $stats = $cache->remember($cacheKey, function() use ($contractRepo, $year, $filter_collaborator) {
        return $contractRepo->getYearStats($year, $filter_collaborator);
    }, 600);

} catch (Exception $e) {
    print '<div style="background: var(--colorbacktabcard1); border: 1px solid #dc3545; color: var(--colortext); padding: 15px; margin: 20px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur Dashboard:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
    $nb_collaborators = 0;
    $stats = array(
        'nb_contracts' => 0,
        'nb_draft' => 0,
        'nb_valid' => 0,
        'total_ht' => 0,
        'total_collaborator' => 0,
        'total_studio' => 0
    );
}

// 3. Contrôles de cohérence des tables
$table_status = array();
$tables_to_check = array(
    'revenuesharing_collaborator' => 'Collaborateurs',
    'revenuesharing_contract' => 'Contrats'
);

foreach ($tables_to_check as $table => $desc) {
    $table_name = MAIN_DB_PREFIX.$table;
    $sql_check = "SHOW TABLES LIKE '$table_name'";
    $resql_check = $db->query($sql_check);

    if ($resql_check && $db->num_rows($resql_check) > 0) {
        $sql_count = "SELECT COUNT(*) as nb FROM $table_name";
        $resql_count = $db->query($sql_count);
        if ($resql_count) {
            $obj_count = $db->fetch_object($resql_count);
            $table_status[$table] = array('exists' => true, 'count' => $obj_count->nb);
            $db->free($resql_count);
        } else {
            $table_status[$table] = array('exists' => true, 'count' => 0);
        }
        $db->free($resql_check);
    } else {
        $table_status[$table] = array('exists' => false, 'count' => 0);
    }
}

print '<div class="fichecenter">';

// Sélecteur d'année et collaborateur
print '<div class="center" style="margin: 15px 0;">';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: inline-block; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';

// Filtre collaborateur
print ' <strong>Collaborateur :</strong> ';
print '<select name="filter_collaborator" onchange="this.form.submit()" style="font-size: 1em; padding: 5px; margin-right: 15px;">';
print '<option value="">Tous les collaborateurs</option>';

$sql_collabs = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
$resql_collabs = $db->query($sql_collabs);
if ($resql_collabs) {
    while ($obj_collab = $db->fetch_object($resql_collabs)) {
        $selected = ($obj_collab->rowid == $filter_collaborator) ? ' selected' : '';
        print '<option value="'.$obj_collab->rowid.'"'.$selected.'>'.dol_escape_htmltag($obj_collab->label).'</option>';
    }
    $db->free($resql_collabs);
}
print '</select>';

// Filtre année
print '<strong>Année :</strong> ';
print '<select name="year" onchange="this.form.submit()" style="font-size: 1em; padding: 5px;">';
for ($y = date('Y'); $y >= date('Y') - 5; $y--) {
    $selected = ($y == $year) ? ' selected' : '';
    print '<option value="'.$y.'"'.$selected.'>'.$y.'</option>';
}
print '</select>';

print '</form>';
print '</div>';

// Boxes statistiques principales
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre">';
print '<th class="center">'.img_picto('', 'user', 'class="pictofixedwidth"').' Collaborateurs</th>';
print '<th class="center">'.img_picto('', 'contract', 'class="pictofixedwidth"').' Contrats '.$year.'</th>';
print '<th class="center">'.img_picto('', 'bill', 'class="pictofixedwidth"').' CA Total '.$year.'</th>';
print '<th class="center">'.img_picto('', 'bank', 'class="pictofixedwidth"').' Part Collaborateurs</th>';
print '</tr>';

print '<tr class="oddeven">';

// Collaborateurs
print '<td class="center">';
print '<a href="collaborator_list.php" style="text-decoration: none;">';
print '<div style="font-size: 2em; color: #007cba;">'.img_picto('', 'user', 'class="pictofixedwidth"').'</div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #007cba;">'.$nb_collaborators.'</div>';
print '<div>Collaborateurs actifs</div>';
print '</a>';
print '</td>';

// Contrats
print '<td class="center">';
print '<a href="contract_list.php" style="text-decoration: none;">';
print '<div style="font-size: 2em; color: #28a745;"></div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #28a745;">'.$stats['nb_valid'].'</div>';
print '<div>Contrats validés</div>';
if ($stats['nb_draft'] > 0) {
    print '<div style="font-size: 0.9em; color: #ffc107;">('.$stats['nb_draft'].' brouillons)</div>';
}
print '</a>';
print '</td>';

// CA Total
print '<td class="center">';
print '<div style="font-size: 2em; color: #dc3545;">'.img_picto('', 'bill', 'class="pictofixedwidth"').'</div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #dc3545;">'.price($stats['total_ht']).'</div>';
print '<div>Chiffre d\'affaires</div>';
print '</td>';

// Part Collaborateurs
print '<td class="center">';
print '<div style="font-size: 2em; color: #ffc107;"></div>';
print '<div style="font-size: 1.5em; font-weight: bold; color: #ffc107;">'.price($stats['total_collaborator']).'</div>';
print '<div>Revenus collaborateurs</div>';
if ($stats['total_studio'] > 0) {
    $percentage = $stats['total_ht'] > 0 ? round(($stats['total_collaborator'] / $stats['total_ht']) * 100, 1) : 0;
    print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu);">('.$percentage.'% du CA)</div>';
}
print '</td>';

print '</tr>';
print '</table>';
print '</div>';

// Répartition détaillée avec historique par année
print '<br>';
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px;">';
print '<h4>Répartition des revenus '.$year.'</h4>';

if ($stats['total_ht'] > 0) {
    $studio_amount = $stats['total_studio'];
    $collab_percentage = round(($stats['total_collaborator'] / $stats['total_ht']) * 100, 1);
    $studio_percentage = round(($studio_amount / $stats['total_ht']) * 100, 1);

    print '<div style="display: flex; justify-content: space-around; text-align: center; margin: 15px 0;">';

    // Part Collaborateurs
    print '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; min-width: 140px;">';
    print '<div style="font-size: 0.9em; color: #856404;">Collaborateurs</div>';
    print '<div style="font-size: 1.3em; font-weight: bold; color: #856404;">'.price($stats['total_collaborator']).'</div>';
    print '<div style="font-size: 0.9em; color: #856404;">'.$collab_percentage.'%</div>';
    print '</div>';

    // Part Studio
    print '<div style="background: #d4edda; padding: 15px; border-radius: 5px; min-width: 140px;">';
    print '<div style="font-size: 0.9em; color: #155724;">Studio</div>';
    print '<div style="font-size: 1.3em; font-weight: bold; color: #155724;">'.price($studio_amount).'</div>';
    print '<div style="font-size: 0.9em; color: #155724;">'.$studio_percentage.'%</div>';
    print '</div>';

    print '</div>';
} else {
    print '<div class="center" style="padding: 15px; color: var(--colortextbackhmenu);">Aucun contrat validé pour '.$year.'</div>';
}

// Historique des 5 dernières années
print '<h5>Évolution sur 5 ans</h5>';
print '<table class="noborder" style="width: 100%;">';
print '<tr class="liste_titre">';
print '<th class="center">Année</th>';
print '<th class="center">Contrats</th>';
print '<th class="center">CA Total</th>';
print '<th class="center">Part Collaborateurs</th>';
print '<th class="center">Part Studio</th>';
print '</tr>';

for ($y = date('Y'); $y >= date('Y') - 4; $y--) {
    $sql_year = "SELECT COUNT(CASE WHEN status >= 1 THEN 1 END) as nb_valid,";
    $sql_year .= " COALESCE(SUM(CASE WHEN status >= 1 THEN amount_ht ELSE 0 END), 0) as total_ht,";
    $sql_year .= " COALESCE(SUM(CASE WHEN status >= 1 THEN net_collaborator_amount ELSE 0 END), 0) as total_collaborator";
    $sql_year .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract";
    $sql_year .= " WHERE YEAR(date_creation) = ".$y;
    if ($filter_collaborator > 0) {
        $sql_year .= " AND fk_collaborator = ".((int) $filter_collaborator);
    }
    
    $resql_year = $db->query($sql_year);
    if ($resql_year) {
        $obj_year = $db->fetch_object($resql_year);
        $year_total_ht = $obj_year->total_ht ? $obj_year->total_ht : 0;
        $year_total_collab = $obj_year->total_collaborator ? $obj_year->total_collaborator : 0;
        $year_total_studio = $year_total_ht - $year_total_collab;
        
        $row_style = ($y == $year) ? ' style="background-color: #e3f2fd; font-weight: bold;"' : '';
        
        print '<tr class="oddeven"'.$row_style.'>';
        print '<td class="center">'.$y.($y == $year ? ' (actuelle)' : '').'</td>';
        print '<td class="center">'.$obj_year->nb_valid.'</td>';
        print '<td class="center">'.price($year_total_ht).'</td>';
        print '<td class="center">'.price($year_total_collab).'</td>';
        print '<td class="center">'.price($year_total_studio).'</td>';
        print '</tr>';
        
        $db->free($resql_year);
    } else {
        print '<tr class="oddeven"><td class="center">'.$y.'</td><td colspan="4" class="center">Erreur</td></tr>';
    }
}

print '</table>';
print '</div>';

// Top 5 collaborateurs de l'année (seulement si pas de filtre collaborateur)
if ($stats['nb_valid'] > 0 && !$filter_collaborator) {
    print '<br>';
    print '<h4>Top Collaborateurs '.$year.'</h4>';

    $sql_top = "SELECT c.rowid, c.label, u.firstname, u.lastname,";
    $sql_top .= " COUNT(rc.rowid) as nb_contracts,";
    $sql_top .= " COALESCE(SUM(rc.net_collaborator_amount), 0) as total_revenue";
    $sql_top .= " FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
    $sql_top .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
    $sql_top .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract rc ON rc.fk_collaborator = c.rowid";
    $sql_top .= " WHERE rc.status >= 1 AND YEAR(rc.date_creation) = ".$year;
    $sql_top .= " GROUP BY c.rowid";
    $sql_top .= " HAVING total_revenue > 0";
    $sql_top .= " ORDER BY total_revenue DESC";
    $sql_top .= " LIMIT 5";

    $resql_top = $db->query($sql_top);
    if ($resql_top && $db->num_rows($resql_top) > 0) {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>Collaborateur</th>';
        print '<th class="center">Contrats</th>';
        print '<th class="center">Revenus nets</th>';
        print '<th class="center">Part du CA</th>';
        print '</tr>';

        while ($obj_top = $db->fetch_object($resql_top)) {
            $percentage_of_total = $stats['total_collaborator'] > 0 ? round(($obj_top->total_revenue / $stats['total_collaborator']) * 100, 1) : 0;

            print '<tr class="oddeven">';
            print '<td><a href="collaborator_card.php?id='.$obj_top->rowid.'">';
            print ($obj_top->label ? $obj_top->label : $obj_top->firstname.' '.$obj_top->lastname);
            print '</a></td>';
            print '<td class="center">'.$obj_top->nb_contracts.'</td>';
            print '<td class="center"><strong>'.price($obj_top->total_revenue).'</strong></td>';
            print '<td class="center">'.$percentage_of_total.'%</td>';
            print '</tr>';
        }

        print '</table>';
        $db->free($resql_top);
    }
}

// Actions rapides
print '<br>';
print '<div class="center">';
print '<a href="collaborator_list.php" class="butAction">'.img_picto('', 'user', 'class="pictofixedwidth"').' Voir les collaborateurs</a> ';
print '<a href="contract_list.php" class="butAction">'.img_picto('', 'contract', 'class="pictofixedwidth"').' Voir les contrats</a> ';
print '<a href="account_list.php" class="butAction">'.img_picto('', 'bank', 'class="pictofixedwidth"').' Comptes collaborateurs</a> ';
if ($has_permission) {
    print '<a href="contract_card_complete.php?action=create" class="butAction">'.img_picto('', 'add', 'class="pictofixedwidth"').' Nouveau contrat</a> ';
}
if ($user->admin) {
    print '<a href="auto_create_contracts.php" class="butAction" style="background: #fd7e14; color: white;"> Auto-création contrats</a> ';
    print '<a href="account_transaction.php" class="butAction" style="background: #17a2b8; color: white;">'.img_picto('', 'bank', 'class="pictofixedwidth"').' Nouvelle opération</a> ';
    print '<a href="payroll_import.php" class="butAction" style="background: #6c757d; color: white;">'.img_picto('', 'technic', 'class="pictofixedwidth"').' Import Paie</a> ';
    print '<a href="admin/setup.php" class="butAction">'.img_picto('', 'setup', 'class="pictofixedwidth"').' Configuration</a>';
}
print '</div>';

// État des tables
print '<br>';
print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 5px; padding: 15px;">';
print '<h4>État des données</h4>';
print '<table class="noborder">';
foreach ($tables_to_check as $table => $desc) {
    print '<tr>';
    print '<td><strong>'.$desc.' :</strong></td>';
    if ($table_status[$table]['exists']) {
        print '<td>'.$table_status[$table]['count'].' enregistrements</td>';
    } else {
        print '<td>Table manquante - Contactez l\'administrateur</td>';
    }
    print '</tr>';
}
print '</table>';
print '</div>';

// Informations système
print '<br>';
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px;">';
print '<h4>Informations Système</h4>';
print '<table class="noborder">';
print '<tr><td><strong>Utilisateur :</strong></td><td>'.$user->login.' ('.$user->getFullName($langs).')</td></tr>';
print '<tr><td><strong>Droits admin :</strong></td><td>'.($user->admin ? 'Oui' : 'Non').'</td></tr>';
print '<tr><td><strong>Entité :</strong></td><td>'.$conf->entity.'</td></tr>';
print '<tr><td><strong>Version Dolibarr :</strong></td><td>'.DOL_VERSION.'</td></tr>';
print '<tr><td><strong>Module activé :</strong></td><td>'.(!empty($conf->revenuesharing->enabled) ? 'Oui' : 'Non').'</td></tr>';
print '<tr><td><strong>Base de données :</strong></td><td>'.$db->type.' (Préfixe: '.MAIN_DB_PREFIX.')</td></tr>';
print '</table>';
print '</div>';

print '</div>';

llxFooter();
$db->close();
?>
