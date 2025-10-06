<?php
// Fichier: account_list.php
// Liste des comptes collaborateurs avec soldes

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$filter_collaborator = GETPOST('filter_collaborator', 'int');
$filter_year = GETPOST('filter_year', 'int');

llxHeader('', 'Comptes Collaborateurs', '');

print load_fiche_titre('Comptes Collaborateurs', '', 'generic');

// Vérifier si les tables existent
$sql_check = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."revenuesharing_account_balance'";
$resql_check = $db->query($sql_check);

if (!$resql_check || $db->num_rows($resql_check) == 0) {
    print '<div style="background: #f8d7da; padding: 20px; border-radius: 5px; color: #721c24; text-align: center;">';
    print '<h3>Tables non créées</h3>';
    print '<p>Le système de comptes collaborateurs n\'est pas encore initialisé.</p>';
    print '<p>Contactez votre administrateur pour initialiser le système.</p>';
    print '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Recalcul des soldes depuis les transactions
$sql_recalc = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_balance ab 
SET 
    total_credits = COALESCE((
        SELECT SUM(amount) 
        FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
        WHERE t.fk_collaborator = ab.fk_collaborator 
        AND t.amount > 0 AND t.status = 1
    ), 0),
    total_debits = COALESCE((
        SELECT ABS(SUM(amount)) 
        FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
        WHERE t.fk_collaborator = ab.fk_collaborator 
        AND t.amount < 0 AND t.status = 1
    ), 0),
    current_balance = COALESCE((
        SELECT SUM(amount) 
        FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
        WHERE t.fk_collaborator = ab.fk_collaborator AND t.status = 1
    ), 0)";

$db->query($sql_recalc);

// Formulaire de filtrage
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 20px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #495057;">Filtres</h4>';
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

// Requête principale pour les comptes
$year_filter_sql = "";
if ($filter_year > 0) {
    $year_filter_sql = " AND YEAR(t.transaction_date) = ".(int)$filter_year;
}

$sql = "SELECT c.rowid, c.label, c.fk_user, u.firstname, u.lastname,
        ab.total_credits, ab.total_debits, ab.current_balance, ab.last_transaction_date,
        (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
         WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as nb_transactions,
        (SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) 
         FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
         WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as year_credits,
        (SELECT COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0) 
         FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
         WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as year_debits,
        (SELECT COALESCE(SUM(t.amount), 0) 
         FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
         WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as year_balance";
$sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_balance ab ON ab.fk_collaborator = c.rowid";
$sql .= " WHERE c.active = 1";

if ($filter_collaborator > 0) {
    $sql .= " AND c.rowid = ".((int) $filter_collaborator);
}

$sql .= " ORDER BY c.label";

$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    
    // Filtres
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: inline-flex; gap: 15px; align-items: center;">';
    
    print '<label style="font-weight: bold;"> Collaborateur :</label>';
    print '<select name="filter_collaborator" onchange="this.form.submit()">';
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
    
    print '</form>';
    print '</div>';
    
    // Statistiques générales
    if (!$filter_collaborator) {
        if ($filter_year > 0) {
            // Statistiques filtrées par année
            $sql_stats = "SELECT 
                COUNT(DISTINCT c.rowid) as nb_collaborators,
                COALESCE(SUM((SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) 
                             FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                             WHERE t.fk_collaborator = c.rowid AND t.status = 1 AND YEAR(t.transaction_date) = ".(int)$filter_year.")), 0) as total_all_credits,
                COALESCE(SUM((SELECT COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0) 
                             FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                             WHERE t.fk_collaborator = c.rowid AND t.status = 1 AND YEAR(t.transaction_date) = ".(int)$filter_year.")), 0) as total_all_debits,
                COALESCE(SUM((SELECT COALESCE(SUM(t.amount), 0) 
                             FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t 
                             WHERE t.fk_collaborator = c.rowid AND t.status = 1 AND YEAR(t.transaction_date) = ".(int)$filter_year.")), 0) as total_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                WHERE c.active = 1";
        } else {
            // Statistiques globales (non filtrées)
            $sql_stats = "SELECT 
                COUNT(DISTINCT c.rowid) as nb_collaborators,
                COALESCE(SUM(ab.total_credits), 0) as total_all_credits,
                COALESCE(SUM(ab.total_debits), 0) as total_all_debits,
                COALESCE(SUM(ab.current_balance), 0) as total_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_balance ab ON ab.fk_collaborator = c.rowid
                WHERE c.active = 1";
        }
        
        $resql_stats = $db->query($sql_stats);
        if ($resql_stats) {
            $stats = $db->fetch_object($resql_stats);
            
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
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold; color: #007cba;">'.$stats->nb_collaborators.'</div></td>';
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold; color: #28a745;">'.price($stats->total_all_credits).'</div></td>';
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold; color: #dc3545;">'.price($stats->total_all_debits).'</div></td>';
            
            $balance_color = ($stats->total_balance >= 0) ? '#28a745' : '#dc3545';
            print '<td class="center"><div style="font-size: 1.5em; font-weight: bold; color: '.$balance_color.';">'.price($stats->total_balance).'</div></td>';
            print '</tr>';
            
            print '</table>';
            print '</div>';
            print '</div>';
            
            $db->free($resql_stats);
        }
    }
    
    print '<br>';
    
    // Table des comptes collaborateurs
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Collaborateur</th>';
    if ($filter_year > 0) {
        print '<th class="center">Crédits '.$filter_year.'</th>';
        print '<th class="center">Débits '.$filter_year.'</th>';
        print '<th class="center"> Solde '.$filter_year.'</th>';
        print '<th class="center">Transactions '.$filter_year.'</th>';
    } else {
        print '<th class="center">Total Crédits</th>';
        print '<th class="center">Total Débits</th>';
        print '<th class="center"> Solde Actuel</th>';
        print '<th class="center">Transactions</th>';
    }
    print '<th class="center">Dernière Op.</th>';
    print '<th class="center">Actions</th>';
    print '</tr>';
    
    $i = 0;
    while ($obj = $db->fetch_object($resql)) {
        $i++;
        
        print '<tr class="oddeven">';
        
        // Collaborateur
        print '<td>';
        print '<strong>'.$obj->label.'</strong>';
        if ($obj->firstname && $obj->lastname) {
            print '<br><small style="color: #666;">'.$obj->firstname.' '.$obj->lastname.'</small>';
        }
        print '</td>';
        
        // Crédits (avec filtre année)
        print '<td class="center">';
        $credits_value = ($filter_year > 0) ? $obj->year_credits : $obj->total_credits;
        if ($credits_value > 0) {
            print '<span style="color: #28a745; background: #d4edda; padding: 3px 6px; border-radius: 6px; font-weight: bold; font-size: 0.9em;">'.price($credits_value).'</span>';
        } else {
            print '<span style="color: #ccc;">0,00 €</span>';
        }
        print '</td>';
        
        // Débits (avec filtre année)
        print '<td class="center">';
        $debits_value = ($filter_year > 0) ? $obj->year_debits : $obj->total_debits;
        if ($debits_value > 0) {
            print '<span style="color: #dc3545; background: #f8d7da; padding: 3px 6px; border-radius: 6px; font-weight: bold; font-size: 0.9em;">'.price($debits_value).'</span>';
        } else {
            print '<span style="color: #ccc;">0,00 €</span>';
        }
        print '</td>';
        
        // Solde (avec filtre année)  
        print '<td class="center">';
        $balance_value = ($filter_year > 0) ? $obj->year_balance : $obj->current_balance;
        if ($balance_value >= 0) {
            $balance_color = '#28a745';
            $balance_bg = '#d4edda';
            $balance_icon = '';
        } else {
            $balance_color = '#dc3545';
            $balance_bg = '#f8d7da';
            $balance_icon = '';
        }
        print '<span style="color: '.$balance_color.'; background: '.$balance_bg.'; padding: 4px 8px; border-radius: 8px; font-weight: bold; font-size: 1em;">';
        print $balance_icon.' '.price($balance_value);
        print '</span>';
        print '</td>';
        
        // Nb transactions (avec filtre année)
        print '<td class="center">';
        if ($obj->nb_transactions > 0) {
            print '<span class="badge" style="background: #007cba; color: white; padding: 2px 6px; border-radius: 3px;">'.$obj->nb_transactions.'</span>';
        } else {
            print '<span style="color: #ccc;">0</span>';
        }
        print '</td>';
        
        // Dernière opération
        print '<td class="center">';
        if ($obj->last_transaction_date) {
            print dol_print_date($db->jdate($obj->last_transaction_date), 'day');
        } else {
            print '<span style="color: #ccc;">-</span>';
        }
        print '</td>';
        
        // Actions
        print '<td class="center">';
        print '<a href="account_detail.php?id='.$obj->rowid.'" class="button" style="margin: 2px;"> Voir</a>';
        print '<a href="account_transaction.php?collaborator_id='.$obj->rowid.'" class="button" style="margin: 2px; background: #fd7e14; color: white;"> Opération</a>';
        print '</td>';
        
        print '</tr>';
        $i++;
    }
    
    if ($num == 0) {
        print '<tr><td colspan="7" class="center" style="padding: 20px; color: #666;">';
        print '<div style="font-size: 3em;"></div>';
        print '<h3>Aucun collaborateur trouvé</h3>';
        print '</td></tr>';
    }
    
    print '</table>';
    print '</div>';
    
    $db->free($resql);
} else {
    print '<div style="color: red;">Erreur SQL : '.$db->lasterror().'</div>';
}

// Section Export Global
print '<div style="background: #f0f8ff; border: 1px solid #b8d4f0; border-radius: 8px; padding: 15px; margin: 20px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #1e6ba8;">Export global des comptes</h4>';
print '<p style="margin: 5px 0; color: #666;">Exportez la liste complète des comptes collaborateurs avec leurs soldes</p>';

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
    print '<p style="margin: 5px 0; color: #666; font-size: 0.9em;"><em>Export filtré pour l\'année '.$filter_year.'</em></p>';
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