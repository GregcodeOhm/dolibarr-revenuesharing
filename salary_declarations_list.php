<?php
/**
 * Liste des déclarations de salaires
 * Fichier: /htdocs/custom/revenuesharing/salary_declarations_list.php
 */

require_once '../../main.inc.php';
require_once __DIR__.'/class/repositories/SalaryDeclarationRepository.php';
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Initialiser les repositories
$salaryDeclRepo = new SalaryDeclarationRepository($db);
$collaboratorRepo = new CollaboratorRepository($db);

// Parameters
$action = GETPOST('action', 'alpha');
$collaborator_filter = GETPOST('collaborator_filter', 'int');
$year_filter = GETPOST('year_filter', 'int') ? GETPOST('year_filter', 'int') : date('Y');
$month_filter = GETPOST('month_filter', 'int');
$status_filter = GETPOST('status_filter', 'int');
$page = GETPOST('page', 'int') ? GETPOST('page', 'int') : 1;
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : 50;

llxHeader('', 'Déclarations de Salaires', '');

print load_fiche_titre('Déclarations de Salaires - Intermittents du Spectacle', '', 'generic');

// Définir les mois
$months = array(
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
);

// Statuts
$status_options = array(
    1 => 'Brouillon',
    2 => 'Validée',
    3 => 'Payée'
);

try {
    // Récupérer la liste des collaborateurs actifs pour le filtre
    $collaborators = $collaboratorRepo->findAll(['active' => 1]);

    // Filtres
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">';

    // Filtre collaborateur
    print '<label style="font-weight: bold;">👤 Collaborateur :</label>';
    print '<select name="collaborator_filter">';
    print '<option value="">Tous les collaborateurs</option>';
    if ($collaborators) {
        foreach ($collaborators as $collab) {
            $selected = ($collab->rowid == $collaborator_filter) ? ' selected' : '';
            print '<option value="'.$collab->rowid.'"'.$selected.'>'.$collab->label.'</option>';
        }
    }
    print '</select>';

    // Filtre année
    print '<label style="font-weight: bold;">📅 Année :</label>';
    print '<select name="year_filter">';
    for ($y = date('Y'); $y >= date('Y') - 3; $y--) {
        $selected = ($y == $year_filter) ? ' selected' : '';
        print '<option value="'.$y.'"'.$selected.'>'.$y.'</option>';
    }
    print '</select>';

    // Filtre mois
    print '<label style="font-weight: bold;">🗓️ Mois :</label>';
    print '<select name="month_filter">';
    print '<option value="">Tous les mois</option>';
    foreach ($months as $num => $name) {
        $selected = ($num == $month_filter) ? ' selected' : '';
        print '<option value="'.$num.'"'.$selected.'>'.$name.'</option>';
    }
    print '</select>';

    // Filtre statut
    print '<label style="font-weight: bold;">📊 Statut :</label>';
    print '<select name="status_filter">';
    print '<option value="">Tous les statuts</option>';
    foreach ($status_options as $num => $name) {
        $selected = ($num == $status_filter) ? ' selected' : '';
        print '<option value="'.$num.'"'.$selected.'>'.$name.'</option>';
    }
    print '</select>';

    print '<input type="submit" value="Filtrer" class="button">';
    print '<a href="'.$_SERVER["PHP_SELF"].'" class="button">Reset</a>';
    print '</form>';
    print '</div>';

    // Boutons d'action
    print '<div class="tabsAction">';
    print '<a href="salary_declaration_form.php" class="butAction">'.img_picto('', 'add', 'class="pictofixedwidth"').' Nouvelle Déclaration</a>';
    print '<a href="admin/create_salary_tables.php" class="butAction">Créer/Vérifier Tables</a>';
    print '</div>';

    // Récupérer les déclarations via le repository
    $result = $salaryDeclRepo->findAllWithDetails([
        'collaborator' => $collaborator_filter,
        'year' => $year_filter,
        'month' => $month_filter,
        'status' => $status_filter,
        'page' => $page,
        'limit' => $limit
    ]);

    $declarations = $result['declarations'];
    $total = $result['total'];
    $pages = $result['pages'];

    if (count($declarations) > 0) {
        print '<div class="div-table-responsive-no-min">';
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>👤 Collaborateur</th>';
        print '<th class="center">📅 Période</th>';
        print '<th class="center">📊 Jours travaillés</th>';
        print '<th class="center">⏰ Total Heures</th>';
        print '<th class="center">💰 Total Cachets</th>';
        print '<th class="center">💼 Masse Salariale</th>';
        print '<th class="center">💸 Solde Utilisé</th>';
        print '<th class="center">🏷️ Statut</th>';
        print '<th class="center">Actions</th>';
        print '</tr>';

        foreach ($declarations as $obj) {
            print '<tr class="oddeven">';

            // Collaborateur
            print '<td>';
            print '<strong style="color: #007cba;">'.$obj->collaborator_name.'</strong>';
            print '</td>';

            // Période
            print '<td class="center">';
            print '<span style="background: #e3f2fd; padding: 4px 8px; border-radius: 4px;">';
            print $months[$obj->declaration_month].' '.$obj->declaration_year;
            print '</span>';
            print '</td>';

            // Jours travaillés
            print '<td class="center">';
            print '<span style="font-weight: bold; color: #2d7d2d;">'.$obj->nb_days_worked.'</span>';
            print '</td>';

            // Total heures
            print '<td class="center">';
            if (isset($obj->total_heures) && $obj->total_heures > 0) {
                print '<span style="font-weight: bold; color: #007cba;">'.number_format($obj->total_heures, 1, ',', ' ').' h</span>';
            } else {
                print '<span style="color: #999;">-</span>';
            }
            print '</td>';

            // Total cachets
            print '<td class="center">';
            if ($obj->total_cachets_bruts > 0) {
                print '<span style="color: #28a745; font-weight: bold;">'.price($obj->total_cachets_bruts).'</span>';
            } else {
                print '<span style="color: #999;">-</span>';
            }
            print '</td>';

            // Masse salariale
            print '<td class="center">';
            if ($obj->masse_salariale > 0) {
                print '<span style="color: #fd7e14; font-weight: bold;">'.price($obj->masse_salariale).'</span>';
            } else {
                print '<span style="color: #999;">-</span>';
            }
            print '</td>';

            // Solde utilisé
            print '<td class="center">';
            if (isset($obj->solde_utilise) && $obj->solde_utilise !== null && $obj->solde_utilise != 0) {
                print '<span style="color: #dc3545; font-weight: bold;">'.price($obj->solde_utilise).'</span>';
            } else {
                print '<span style="color: #999;">0,00 €</span>';
            }
            print '</td>';

            // Statut
            print '<td class="center">';
            switch ($obj->status) {
                case 1:
                    print '<span style="background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 12px; font-size: 0.9em;">📝 Brouillon</span>';
                    break;
                case 2:
                    print '<span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 12px; font-size: 0.9em;">✅ Validée</span>';
                    break;
                case 3:
                    print '<span style="background: #cce5f0; color: #007cba; padding: 3px 8px; border-radius: 12px; font-size: 0.9em;">💸 Payée</span>';
                    break;
                default:
                    print '<span style="background: var(--colorbacktabcard1); color: var(--colortext); padding: 3px 8px; border-radius: 12px; font-size: 0.9em;">❌ Inactive</span>';
            }
            print '</td>';

            // Actions
            print '<td class="center">';
            print '<div style="display: flex; gap: 3px; justify-content: center; flex-wrap: wrap;">';

            // Actions selon le statut
            if ($obj->status == 1) { // Brouillon
                print '<a href="salary_declaration_form.php?id='.$obj->rowid.'" class="button buttonxs" title="Modifier">'.img_picto('', 'edit').'</a>';
                print '<a href="salary_declaration_detail.php?id='.$obj->rowid.'&action=validate" class="button buttonxs" title="Valider" style="background: #28a745; color: white;">'.img_picto('', 'check').'</a>';
            } elseif ($obj->status == 2) { // Validée
                print '<a href="salary_declaration_detail.php?id='.$obj->rowid.'&action=setpaid" class="button buttonxs" title="Marquer Payée" style="background: #007cba; color: white;">'.img_picto('', 'bank').'</a>';
                print '<a href="salary_declaration_detail.php?id='.$obj->rowid.'&action=reopen" class="button buttonxs" title="Remettre en Brouillon" style="background: #ffc107; color: black;">'.img_picto('', 'edit').'</a>';
            } elseif ($obj->status == 3) { // Payée
                print '<span class="button buttonxs" style="background: #e9ecef; color: #6c757d; cursor: not-allowed;" title="Payée - Non modifiable">'.img_picto('', 'tick').'</span>';
            }

            // Actions communes
            print '<a href="salary_declaration_detail.php?id='.$obj->rowid.'" class="button buttonxs" title="Voir détails">'.img_picto('', 'eye').'</a>';
            print '<a href="salary_declaration_export.php?id='.$obj->rowid.'&format=pdf" class="button buttonxs" title="Export PDF">'.img_picto('', 'pdf').'</a>';

            print '</div>';
            print '</td>';

            print '</tr>';
        }

        print '</table>';
        print '</div>';

        // Pagination
        if ($pages > 1) {
            print '<div style="text-align: center; margin: 20px 0;">';

            $base_url = $_SERVER["PHP_SELF"].'?';
            if ($collaborator_filter) $base_url .= 'collaborator_filter='.$collaborator_filter.'&';
            if ($year_filter) $base_url .= 'year_filter='.$year_filter.'&';
            if ($month_filter) $base_url .= 'month_filter='.$month_filter.'&';
            if ($status_filter) $base_url .= 'status_filter='.$status_filter.'&';
            $base_url .= 'limit='.$limit.'&';

            if ($page > 1) {
                print '<a href="'.$base_url.'page=1" class="button">« Première</a> ';
                print '<a href="'.$base_url.'page='.($page-1).'" class="button">‹ Précédente</a> ';
            }

            print '<span style="padding: 0 15px;">Page <strong>'.$page.'</strong> sur <strong>'.$pages.'</strong> ('.$total.' déclarations)</span>';

            if ($page < $pages) {
                print ' <a href="'.$base_url.'page='.($page+1).'" class="button">Suivante ›</a>';
                print ' <a href="'.$base_url.'page='.$pages.'" class="button">Dernière »</a>';
            }

            print '</div>';
        }

        // Récupérer et afficher les statistiques
        $stats = $salaryDeclRepo->getAggregatedStats([
            'collaborator' => $collaborator_filter,
            'year' => $year_filter,
            'month' => $month_filter,
            'status' => $status_filter
        ]);

        if ($stats) {
            print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 15px; margin: 20px 0;">';
            print '<h4 style="margin: 0 0 10px 0; color: #2d7d2d;">📊 Statistiques de la période</h4>';
            print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';

            print '<div style="text-align: center;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #2d7d2d;">'.$stats->total_declarations.'</div>';
            print '<div class="opacitymedium">📋 Déclarations</div>';
            print '</div>';

            print '<div style="text-align: center;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #28a745;">'.price($stats->total_cachets).'</div>';
            print '<div class="opacitymedium">💰 Total Cachets</div>';
            print '</div>';

            print '<div style="text-align: center;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #fd7e14;">'.price($stats->total_masse).'</div>';
            print '<div class="opacitymedium">💼 Masse Salariale</div>';
            print '</div>';

            print '<div style="text-align: center;">';
            print '<div style="font-size: 1.5em; font-weight: bold; color: #dc3545;">'.price($stats->total_solde).'</div>';
            print '<div class="opacitymedium">💸 Soldes Utilisés</div>';
            print '</div>';

            print '</div>';
            print '</div>';
        }

    } else {
        print '<div style="text-align: center; padding: 40px; color: var(--colortextbackhmenu);">';
        print '<div style="font-size: 3em;">📝</div>';
        print '<h3>Aucune déclaration trouvée</h3>';
        print '<p>Commencez par créer votre première déclaration de salaires</p>';
        print '<a href="salary_declaration_form.php" class="button">'.img_picto('', 'add', 'class="pictofixedwidth"').' Créer une déclaration</a>';
        print '</div>';
    }

} catch (Exception $e) {
    print '<div style="background: var(--colorbacktabcard1); border: 1px solid #dc3545; color: var(--colortext); padding: 15px; margin: 20px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
}

print '<div class="tabsAction">';
print '<a href="index.php" class="butAction">🏠 Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>
