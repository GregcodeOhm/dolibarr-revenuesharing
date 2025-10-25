<?php
// Script de diagnostic pour examiner les descriptions de transactions
require_once '../../../main.inc.php';

if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Diagnostic Descriptions Transactions', '');
print load_fiche_titre('Diagnostic des Descriptions de Transactions', '', 'generic');

// Afficher les transactions avec leurs descriptions
$sql_all = "SELECT t.rowid, t.description, t.amount, t.transaction_date, c.label as collaborator_name 
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = t.fk_collaborator
            WHERE t.status = 1 
            ORDER BY t.rowid DESC LIMIT 100";

$resql_all = $db->query($sql_all);

if ($resql_all) {
    $nb_transactions = $db->num_rows($resql_all);
    
    print '<h3>Dernières '.$nb_transactions.' transactions (maximum 100)</h3>';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>ID</th>';
    print '<th>Collaborateur</th>';
    print '<th>Description complète</th>';
    print '<th>Montant</th>';
    print '<th>Contient "auto-créé"?</th>';
    print '</tr>';
    
    while ($transaction = $db->fetch_object($resql_all)) {
        $contains_auto = (strpos($transaction->description, 'auto-créé') !== false || 
                         strpos($transaction->description, 'auto-cree') !== false ||
                         strpos($transaction->description, 'Contrat auto') !== false);
        
        print '<tr class="oddeven">';
        print '<td>'.$transaction->rowid.'</td>';
        print '<td>'.dol_escape_htmltag($transaction->collaborator_name).'</td>';
        print '<td style="max-width: 400px; word-wrap: break-word;">'.dol_escape_htmltag($transaction->description).'</td>';
        print '<td style="text-align: right;">'.price($transaction->amount).'</td>';
        print '<td style="text-align: center;">';
        if ($contains_auto) {
            print '<span style="background: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 4px;">OUI</span>';
        } else {
            print '<span style="background: #d4edda; color: #155724; padding: 2px 6px; border-radius: 4px;">NON</span>';
        }
        print '</td>';
        print '</tr>';
    }
    print '</table>';
    
    $db->free($resql_all);
    
    // Recherches spécifiques dans les descriptions de transactions
    print '<h3>Recherches spécifiques dans les descriptions</h3>';
    
    $searches = array(
        'auto-créé' => "description LIKE '%auto-créé%'",
        'auto-cree' => "description LIKE '%auto-cree%'", 
        'Contrat auto' => "description LIKE '%Contrat auto%'",
        'facture' => "description LIKE '%facture%'",
        'factu' => "description LIKE '%factu%'",
        'Contrat auto-créé pour factu' => "description LIKE '%Contrat auto-créé pour factu%'",
        'Contrat auto-créé pour facture' => "description LIKE '%Contrat auto-créé pour facture%'"
    );
    
    print '<table class="noborder">';
    print '<tr class="liste_titre"><th>Recherche</th><th>Nombre trouvé</th><th>Exemples</th></tr>';
    
    foreach ($searches as $search_name => $condition) {
        $sql_search = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction WHERE status = 1 AND ".$condition;
        $resql_search = $db->query($sql_search);
        
        if ($resql_search) {
            $result = $db->fetch_object($resql_search);
            $count = $result->nb;
            
            print '<tr class="oddeven">';
            print '<td><strong>'.$search_name.'</strong></td>';
            print '<td style="text-align: center;">';
            if ($count > 0) {
                print '<span style="color: #dc3545; font-weight: bold;">'.$count.'</span>';
            } else {
                print '<span style="color: #28a745;">'.$count.'</span>';
            }
            print '</td>';
            print '<td>';
            
            if ($count > 0) {
                $sql_examples = "SELECT description, amount FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction WHERE status = 1 AND ".$condition." LIMIT 3";
                $resql_examples = $db->query($sql_examples);
                if ($resql_examples) {
                    $examples = array();
                    while ($example = $db->fetch_object($resql_examples)) {
                        $desc = substr($example->description, 0, 80).(strlen($example->description) > 80 ? '...' : '');
                        $examples[] = $desc.' ('.price($example->amount).')';
                    }
                    print '<small>'.implode('<br>', array_map('dol_escape_htmltag', $examples)).'</small>';
                    $db->free($resql_examples);
                }
            } else {
                print '<small style="color: #666;">Aucun</small>';
            }
            
            print '</td>';
            print '</tr>';
            
            $db->free($resql_search);
        }
    }
    print '</table>';
    
} else {
    print '<div style="background: #f8d7da; padding: 15px; border-radius: 5px;">';
    print '<p>Erreur lors de la récupération des transactions: '.$db->lasterror().'</p>';
    print '</div>';
}

print '<div class="tabsAction">';
print '<a href="../account_list.php" class="butAction">Retour aux comptes</a>';
print '<a href="cleanup_contract_descriptions.php" class="butAction">🧹 Script de nettoyage (contrats)</a>';
print '</div>';

llxFooter();
$db->close();
?>