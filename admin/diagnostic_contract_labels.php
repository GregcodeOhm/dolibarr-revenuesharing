<?php
// Script de diagnostic pour examiner les labels de contrats
require_once '../../../main.inc.php';

if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Diagnostic Labels Contrats', '');
print load_fiche_titre('Diagnostic des Labels de Contrats', '', 'generic');

// Afficher TOUS les contrats pour voir leurs labels
$sql_all = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."revenuesharing_contract ORDER BY rowid DESC LIMIT 50";
$resql_all = $db->query($sql_all);

if ($resql_all) {
    $nb_contracts = $db->num_rows($resql_all);
    
    print '<h3>Derniers '.$nb_contracts.' contrats (maximum 50)</h3>';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>ID</th>';
    print '<th>Référence</th>';
    print '<th>Label complet</th>';
    print '<th>Contient "auto-créé"?</th>';
    print '</tr>';
    
    while ($contract = $db->fetch_object($resql_all)) {
        $contains_auto = (strpos($contract->label, 'auto-créé') !== false || strpos($contract->label, 'auto-cree') !== false);
        
        print '<tr class="oddeven">';
        print '<td>'.$contract->rowid.'</td>';
        print '<td><strong>'.$contract->ref.'</strong></td>';
        print '<td style="max-width: 400px; word-wrap: break-word;">'.dol_escape_htmltag($contract->label).'</td>';
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
    
    // Recherches spécifiques
    print '<h3>Recherches spécifiques</h3>';
    
    $searches = array(
        'auto-créé' => "label LIKE '%auto-créé%'",
        'auto-cree' => "label LIKE '%auto-cree%'", 
        'Contrat auto' => "label LIKE '%Contrat auto%'",
        'facture' => "label LIKE '%facture%'",
        'factu' => "label LIKE '%factu%'"
    );
    
    print '<table class="noborder">';
    print '<tr class="liste_titre"><th>Recherche</th><th>Nombre trouvé</th><th>Exemples</th></tr>';
    
    foreach ($searches as $search_name => $condition) {
        $sql_search = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE ".$condition;
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
                $sql_examples = "SELECT label FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE ".$condition." LIMIT 3";
                $resql_examples = $db->query($sql_examples);
                if ($resql_examples) {
                    $examples = array();
                    while ($example = $db->fetch_object($resql_examples)) {
                        $examples[] = substr($example->label, 0, 50).(strlen($example->label) > 50 ? '...' : '');
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
    print '<p>Erreur lors de la récupération des contrats: '.$db->lasterror().'</p>';
    print '</div>';
}

print '<div class="tabsAction">';
print '<a href="../contract_list.php" class="butAction">Retour aux contrats</a>';
print '<a href="cleanup_contract_descriptions.php" class="butAction">🧹 Script de nettoyage</a>';
print '</div>';

llxFooter();
$db->close();
?>