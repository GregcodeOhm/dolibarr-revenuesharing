<?php
// Fichier: account_list.php
// Liste des comptes collaborateurs avec soldes

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';
require_once __DIR__.'/class/repositories/BalanceRepository.php';
require_once __DIR__.'/class/CacheManager.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Initialiser repositories et cache
$cache = new CacheManager(null, 300, true);
$collaboratorRepo = new CollaboratorRepository($db);
$balanceRepo = new BalanceRepository($db, $cache);

// Parameters
$filter_collaborator = GETPOST('filter_collaborator', 'int');
$filter_year = GETPOST('filter_year', 'int');

// Si aucune année n'est spécifiée, utiliser l'année en cours par défaut
if (!$filter_year && !isset($_GET['filter_year'])) {
    $filter_year = (int)date('Y');
}

llxHeader('', 'Comptes Collaborateurs', '');

print load_fiche_titre('Comptes Collaborateurs', '', 'generic');

// Vérifier si les tables existent
$sql_check = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."revenuesharing_account_balance'";
$resql_check = $db->query($sql_check);

if (!$resql_check || $db->num_rows($resql_check) == 0) {
    print '<div style="background: var(--colorbacktabcard1); padding: 20px; border-radius: 5px; color: var(--colortext); text-align: center;">';
    print '<h3>Tables non créées</h3>';
    print '<p>Le système de comptes collaborateurs n\'est pas encore initialisé.</p>';
    print '<p>Contactez votre administrateur pour initialiser le système.</p>';
    print '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Note: Les soldes sont maintenant calculés par le BalanceRepository
// qui prend en compte les transactions ET les salaires déclarés (status=3)
// Le recalcul automatique a été supprimé pour éviter les incohérences

// Formulaire de filtrage
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 20px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: var(--colortextbackhmenu);">Filtres</h4>';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; gap: 15px; align-items: end;">';

// Filtre par année
print '<div>';
print '<label for="filter_year" style="display: block; margin-bottom: 5px; font-weight: bold;">Année :</label>';
print '<select name="filter_year" id="filter_year" class="flat" style="padding: 5px;">';
print '<option value="">Toutes les années</option>';

// Générer les options d'années (5 dernières années + année courante)
$current_year = date('Y');
for ($year = $current_year; $year >= $current_year - 5; $year--) {
    $selected = ($filter_year == $year) ? ' selected' : '';
    print '<option value="'.$year.'"'.$selected.'>'.$year.'</option>';
}
print '</select>';
print '</div>';

print '<div>';
print '<button type="submit" class="button" style="background: #007cba; color: white; padding: 8px 15px; border: none; border-radius: 4px;">Filtrer</button>';
if ($filter_year) {
    print ' <a href="'.$_SERVER["PHP_SELF"].'" class="button" style="background: #6c757d; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin-left: 5px;">Réinitialiser</a>';
}
print '</div>';

print '</form>';
print '</div>';

// Récupérer les comptes via repository
try {
    $accounts = $collaboratorRepo->findAllWithBalances([
        'active' => 1,
        'year' => $filter_year,
        'collaborator' => $filter_collaborator
    ]);

    $num = count($accounts);
    
    // Filtres
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: inline-flex; gap: 15px; align-items: center;">';

    print '<label style="font-weight: bold;"> Collaborateur :</label>';
    print '<select name="filter_collaborator" onchange="this.form.submit()">';
    print '<option value="">Tous les collaborateurs</option>';

    $collabs_list = $collaboratorRepo->findAll(['active' => 1]);
    foreach ($collabs_list as $obj_collab) {
        $selected = ($obj_collab->rowid == $filter_collaborator) ? ' selected' : '';
        print '<option value="'.$obj_collab->rowid.'"'.$selected.'>'.dol_escape_htmltag($obj_collab->label).'</option>';
    }
    print '</select>';

    print '</form>';
    print '</div>';
    
    // Statistiques générales
    if (!$filter_collaborator) {
        // Utiliser BalanceRepository avec cache
        $cacheKey = "account_list_stats_{$filter_year}";
        $stats = $cache->remember($cacheKey, function() use ($balanceRepo, $filter_year) {
            return $balanceRepo->getGlobalStats(['year' => $filter_year]);
        }, 300);

        if ($stats) {
            
            print '<div class="fichecenter">';
            print '<div class="div-table-responsive-no-min">';
            print '<table class="noborder nohover centpercent">';
            print '<tr class="liste_titre">';
            print '<th class="center">Collaborateurs</th>';
            if ($filter_year > 0) {
                print '<th class="center">Crédits '.$filter_year.'</th>';
                print '<th class="center">Débits '.$filter_year.'</th>';
                print '<th class="center"> Solde '.$filter_year.'</th>';
            } else {
                print '<th class="center">Total Crédits</th>';
                print '<th class="center">Total Débits</th>';
                print '<th class="center"> Solde Global</th>';
            }
            print '</tr>';
            
            print '<tr class="oddeven">';
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold;">'.$stats->nb_collaborators.'</div></td>';
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold;" class="badge badge-success">'.price($stats->total_all_credits).'</div></td>';
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold;" class="badge badge-danger">'.price($stats->total_all_debits).'</div></td>';

            $badge_class = ($stats->total_balance >= 0) ? 'badge-success' : 'badge-danger';
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold;" class="badge '.$badge_class.'">'.price($stats->total_balance).'</div></td>';
            print '</tr>';
            
            print '</table>';
            print '</div>';
            print '</div>';
        }
    }
    
    print '<br>';
    
    // Table des comptes collaborateurs
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Collaborateur</th>';
    if ($filter_year > 0) {
        print '<th class="center">Crédits '.($filter_year - 1).'</th>';
        print '<th class="center">Solde '.($filter_year - 1).'</th>';
        print '<th class="center">Crédits '.$filter_year.'</th>';
        print '<th class="center">Débits '.$filter_year.'</th>';
        print '<th class="center">Solde '.$filter_year.'</th>';
        print '<th class="center">Solde Total</th>';
        print '<th class="center">Transactions '.$filter_year.'</th>';
    } else {
        print '<th class="center">Total Crédits</th>';
        print '<th class="center">Total Débits</th>';
        print '<th class="center">Solde Actuel</th>';
        print '<th class="center">Transactions</th>';
    }
    print '<th class="center">Dernière Op.</th>';
    print '<th class="center">Actions</th>';
    print '</tr>';

    $i = 0;
    foreach ($accounts as $obj) {
        $i++;
        
        print '<tr class="oddeven">';
        
        // Collaborateur
        print '<td>';
        print '<strong>'.$obj->label.'</strong>';
        if ($obj->firstname && $obj->lastname) {
            print '<br><small class="opacitymedium">'.$obj->firstname.' '.$obj->lastname.'</small>';
        }
        print '</td>';

        // Si filtre année actif, afficher d'abord les données N-1
        if ($filter_year > 0) {
            // Crédits N-1
            print '<td class="center">';
            if ($obj->prev_year_credits > 0) {
                print '<span class="badge badge-success" style="padding: 3px 6px; opacity: 0.7;">'.price($obj->prev_year_credits).'</span>';
            } else {
                print '<span class="opacitymedium">0,00 €</span>';
            }
            print '</td>';

            // Solde N-1
            print '<td class="center">';
            $prev_balance_badge = ($obj->prev_year_balance >= 0) ? 'badge-success' : 'badge-danger';
            print '<span class="badge '.$prev_balance_badge.'" style="padding: 4px 8px; font-size: 0.95em; opacity: 0.7;">';
            print price($obj->prev_year_balance);
            print '</span>';
            print '</td>';
        }

        // Crédits (avec filtre année)
        print '<td class="center">';
        $credits_value = ($filter_year > 0) ? $obj->year_credits : $obj->total_credits;
        if ($credits_value > 0) {
            print '<span class="badge badge-success" style="padding: 3px 6px;">'.price($credits_value).'</span>';
        } else {
            print '<span class="opacitymedium">0,00 €</span>';
        }
        print '</td>';

        // Débits (avec filtre année)
        print '<td class="center">';
        $debits_value = ($filter_year > 0) ? $obj->year_debits : $obj->total_debits;
        if ($debits_value > 0) {
            print '<span class="badge badge-danger" style="padding: 3px 6px;">'.price($debits_value).'</span>';
        } else {
            print '<span class="opacitymedium">0,00 €</span>';
        }
        print '</td>';

        // Solde (avec filtre année)
        print '<td class="center">';
        $balance_value = ($filter_year > 0) ? $obj->year_balance : $obj->current_balance;
        $badge_class = ($balance_value >= 0) ? 'badge-success' : 'badge-danger';
        print '<span class="badge '.$badge_class.'" style="padding: 4px 8px; font-size: 1em;">';
        print price($balance_value);
        print '</span>';
        print '</td>';

        // Solde Total (uniquement si filtre année actif)
        if ($filter_year > 0) {
            print '<td class="center">';
            $total_balance_badge = ($obj->current_balance >= 0) ? 'badge-success' : 'badge-danger';
            print '<span class="badge '.$total_balance_badge.'" style="padding: 4px 8px; font-size: 1em;">';
            print price($obj->current_balance);
            print '</span>';
            print '</td>';
        }

        // Nb transactions (avec filtre année)
        print '<td class="center">';
        if ($obj->nb_transactions > 0) {
            print '<span class="badge badge-info">'.$obj->nb_transactions.'</span>';
        } else {
            print '<span class="opacitymedium">0</span>';
        }
        print '</td>';

        // Dernière opération
        print '<td class="center">';
        if ($obj->last_transaction_date) {
            print dol_print_date($db->jdate($obj->last_transaction_date), 'day');
        } else {
            print '<span class="opacitymedium">-</span>';
        }
        print '</td>';

        // Actions
        print '<td class="center">';
        print '<a href="account_detail.php?id='.$obj->rowid.'" class="button" style="margin: 2px;"> Voir</a>';
        print '<a href="account_transaction.php?collaborator_id='.$obj->rowid.'" class="butActionDelete" style="margin: 2px;"> Opération</a>';
        print '</td>';
        
        print '</tr>';
        $i++;
    }
    
    if ($num == 0) {
        print '<tr><td colspan="7" class="center" style="padding: 20px; color: var(--colortextbackhmenu);">';
        print '<div style="font-size: 3em;"></div>';
        print '<h3>Aucun collaborateur trouvé</h3>';
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';

} catch (Exception $e) {
    print '<div style="background: var(--colorbacktabcard1); border: 1px solid #dc3545; color: var(--colortext); padding: 15px; margin: 20px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Section Export Global
print '<div style="background: #f0f8ff; border: 1px solid #b8d4f0; border-radius: 8px; padding: 15px; margin: 20px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #1e6ba8;">Export global des comptes</h4>';
print '<p style="margin: 5px 0; color: var(--colortextbackhmenu);">Exportez la liste complète des comptes collaborateurs avec leurs soldes</p>';

print '<form method="GET" action="export_all_accounts.php" style="margin-top: 10px;">';
print '<input type="hidden" name="action" value="export">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($filter_year > 0) {
    print '<input type="hidden" name="filter_year" value="'.$filter_year.'">';
}
print '<div style="display: flex; gap: 10px;">';
print '<button type="submit" name="format" value="csv" class="butAction" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Export Excel (CSV)</button>';
print '<button type="submit" name="format" value="pdf" class="butAction" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;"> Export PDF</button>';
print '</div>';
if ($filter_year > 0) {
    print '<p style="margin: 5px 0; color: var(--colortextbackhmenu); font-size: 0.9em;"><em>Export filtré pour l\'année '.$filter_year.'</em></p>';
}
print '</form>';
print '</div>';

print '<div class="tabsAction">';
print '<a href="account_transaction.php" class="butAction" style="background: #28a745; color: white;"> Nouvelle Opération</a>';
if ($user->admin) {
    print '<a href="admin/sync_contracts_to_accounts.php" class="butAction" style="background: #fd7e14; color: white;">Sync Contrats</a>';
}
print '<a href="collaborator_list.php" class="butAction">Collaborateurs</a>';
print '<a href="contract_list.php" class="butAction">Contrats</a>';
print '<a href="index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>