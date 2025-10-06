<?php
// Fichier: admin/cleanup_transaction_descriptions.php
// Script pour nettoyer les descriptions des TRANSACTIONS en supprimant "Contrat auto-créé pour factu(re)"

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Nettoyage des descriptions de transactions', '');

print load_fiche_titre('🧹 Nettoyage des descriptions de transactions', '', 'generic');

$action = GETPOST('action', 'alpha');

if ($action == 'cleanup') {
    
    // Vérification du token CSRF
    if (!newToken('check')) {
        setEventMessages('Token de sécurité invalide', null, 'errors');
        $action = '';
    } else {
        print '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        print '<h3 style="margin-top: 0; color: #0c5460;">Nettoyage en cours...</h3>';
        
        // Rechercher toutes les transactions contenant le texte à supprimer
        $sql_find = "SELECT rowid, description, amount, fk_collaborator 
                     FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                     WHERE status = 1 AND (
                         description LIKE '%Contrat auto-créé pour facture%' 
                         OR description LIKE '%Contrat auto-créé pour factu%'
                         OR description LIKE '%Contrat auto-cree pour factu%'
                     )";
        
        $resql_find = $db->query($sql_find);
        
        if ($resql_find) {
            $transactions_found = array();
            $transactions_to_update = 0;
            
            while ($transaction = $db->fetch_object($resql_find)) {
                $transactions_found[] = $transaction;
                $transactions_to_update++;
            }
            $db->free($resql_find);
            
            print '<p><strong>'.$transactions_to_update.' transaction(s) trouvée(s)</strong> contenant le texte à supprimer.</p>';
            
            if ($transactions_to_update > 0) {
                print '<table class="noborder" style="margin: 10px 0;">';
                print '<tr class="liste_titre">';
                print '<th>ID Transaction</th>';
                print '<th>Description actuelle</th>';
                print '<th>Nouvelle description</th>';
                print '<th>Montant</th>';
                print '<th>Résultat</th>';
                print '</tr>';
                
                $updated_count = 0;
                $error_count = 0;
                
                foreach ($transactions_found as $transaction) {
                    // Supprimer le texte indésirable (toutes variantes)
                    $new_description = $transaction->description;
                    $new_description = str_replace('Contrat auto-créé pour facture ', '', $new_description);
                    $new_description = str_replace('Contrat auto-créé pour factu', '', $new_description);
                    $new_description = str_replace('Contrat auto-cree pour factu', '', $new_description);
                    $new_description = str_replace('    Contrat auto-créé pour factu', '', $new_description);
                    $new_description = trim($new_description); // Supprimer les espaces en trop
                    
                    print '<tr class="oddeven">';
                    print '<td><strong>'.$transaction->rowid.'</strong></td>';
                    print '<td style="color: #666; max-width: 300px; word-wrap: break-word;">'.dol_escape_htmltag($transaction->description).'</td>';
                    print '<td style="color: #28a745; max-width: 300px; word-wrap: break-word;">'.dol_escape_htmltag($new_description).'</td>';
                    print '<td style="text-align: right;">'.price($transaction->amount).'</td>';
                    
                    // Mettre à jour en base
                    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                                   SET description = '".$db->escape($new_description)."'
                                   WHERE rowid = ".((int) $transaction->rowid);
                    
                    $resql_update = $db->query($sql_update);
                    
                    if ($resql_update) {
                        print '<td><span style="color: #28a745; font-weight: bold;">Mis à jour</span></td>';
                        $updated_count++;
                    } else {
                        print '<td><span style="color: #dc3545; font-weight: bold;">Erreur</span></td>';
                        $error_count++;
                    }
                    
                    print '</tr>';
                }
                
                print '</table>';
                
                print '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0;">';
                print '<h4 style="margin-top: 0; color: #155724;">Résumé du nettoyage</h4>';
                print '<ul>';
                print '<li><strong>'.$transactions_to_update.'</strong> transaction(s) analysée(s)</li>';
                print '<li><span style="color: #28a745;"><strong>'.$updated_count.'</strong> transaction(s) mise(s) à jour avec succès</span></li>';
                if ($error_count > 0) {
                    print '<li><span style="color: #dc3545;"><strong>'.$error_count.'</strong> erreur(s) rencontrée(s)</span></li>';
                }
                print '</ul>';
                print '</div>';
                
                if ($updated_count > 0) {
                    setEventMessages($updated_count.' transaction(s) nettoyée(s) avec succès', null, 'mesgs');
                }
                if ($error_count > 0) {
                    setEventMessages($error_count.' erreur(s) lors du nettoyage', null, 'warnings');
                }
                
            } else {
                print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;">';
                print '<p style="margin: 0; color: #856404;"><strong>Aucune transaction à nettoyer</strong></p>';
                print '<p style="margin: 5px 0 0 0; color: #856404;">Toutes les transactions ont déjà des descriptions propres.</p>';
                print '</div>';
            }
            
        } else {
            print '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;">';
            print '<p style="margin: 0; color: #721c24;"><strong>Erreur de requête</strong></p>';
            print '<p style="margin: 5px 0 0 0; color: #721c24;">'.dol_escape_htmltag($db->lasterror()).'</p>';
            print '</div>';
        }
        
        print '</div>';
    }
}

// Affichage du formulaire de confirmation
if ($action != 'cleanup') {
    
    // D'abord, compter les transactions concernées
    $sql_count = "SELECT COUNT(*) as nb_transactions 
                  FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                  WHERE status = 1 AND (
                      description LIKE '%Contrat auto-créé pour facture%' 
                      OR description LIKE '%Contrat auto-créé pour factu%'
                      OR description LIKE '%Contrat auto-cree pour factu%'
                  )";
    
    $resql_count = $db->query($sql_count);
    $nb_transactions = 0;
    
    if ($resql_count) {
        $count_result = $db->fetch_object($resql_count);
        $nb_transactions = $count_result->nb_transactions;
        $db->free($resql_count);
    }
    
    print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    print '<h3 style="margin-top: 0; color: #495057;">Description de l\'opération</h3>';
    print '<p>Ce script va supprimer le texte <strong>"Contrat auto-créé pour facture"</strong> (et ses variantes) de toutes les descriptions de transactions qui le contiennent.</p>';
    print '<p><strong>Transactions concernées :</strong> <span style="color: #dc3545; font-weight: bold;">'.$nb_transactions.' transaction(s)</span></p>';
    
    if ($nb_transactions > 0) {
        // Afficher un aperçu des transactions qui seront modifiées
        print '<h4>Aperçu des transactions qui seront modifiées :</h4>';
        
        $sql_preview = "SELECT rowid, description, amount, fk_collaborator 
                        FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                        WHERE status = 1 AND (
                            description LIKE '%Contrat auto-créé pour facture%' 
                            OR description LIKE '%Contrat auto-créé pour factu%'
                            OR description LIKE '%Contrat auto-cree pour factu%'
                        )
                        ORDER BY rowid DESC
                        LIMIT 10";
        
        $resql_preview = $db->query($sql_preview);
        
        if ($resql_preview) {
            print '<table class="noborder" style="margin: 10px 0;">';
            print '<tr class="liste_titre">';
            print '<th>ID Transaction</th>';
            print '<th>Description actuelle</th>';
            print '<th>Description après nettoyage</th>';
            print '<th>Montant</th>';
            print '</tr>';
            
            $count_preview = 0;
            while ($transaction = $db->fetch_object($resql_preview) && $count_preview < 10) {
                // Supprimer toutes les variantes possibles
                $new_description = $transaction->description;
                $new_description = str_replace('Contrat auto-créé pour facture ', '', $new_description);
                $new_description = str_replace('Contrat auto-créé pour factu', '', $new_description);
                $new_description = str_replace('Contrat auto-cree pour factu', '', $new_description);
                $new_description = str_replace('    Contrat auto-créé pour factu', '', $new_description);
                $new_description = trim($new_description);
                
                print '<tr class="oddeven">';
                print '<td><strong>'.$transaction->rowid.'</strong></td>';
                print '<td style="color: #666; max-width: 300px; word-wrap: break-word;">'.dol_escape_htmltag($transaction->description).'</td>';
                print '<td style="color: #28a745; max-width: 300px; word-wrap: break-word;">'.dol_escape_htmltag($new_description).'</td>';
                print '<td style="text-align: right;">'.price($transaction->amount).'</td>';
                print '</tr>';
                
                $count_preview++;
            }
            
            if ($nb_transactions > 10) {
                print '<tr><td colspan="4" style="text-align: center; font-style: italic; color: #666;">... et '.($nb_transactions - 10).' autre(s) transaction(s)</td></tr>';
            }
            
            print '</table>';
            $db->free($resql_preview);
        }
        
        print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        print '<p style="margin: 0; color: #856404;"><strong>Attention</strong></p>';
        print '<p style="margin: 5px 0 0 0; color: #856404;">Cette opération est irréversible. Assurez-vous d\'avoir fait une sauvegarde de la base de données.</p>';
        print '</div>';
        
        // Formulaire de confirmation
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" onsubmit="return confirm(\'Êtes-vous sûr de vouloir nettoyer '.$nb_transactions.' transaction(s) ? Cette action est irréversible.\');">';
        print '<input type="hidden" name="action" value="cleanup">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<div style="text-align: center; margin: 20px 0;">';
        print '<input type="submit" class="button" value="🧹 Nettoyer '.$nb_transactions.' transaction(s)" style="background: #dc3545; color: white; font-weight: bold; padding: 12px 24px;">';
        print '</div>';
        print '</form>';
        
    } else {
        print '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;">';
        print '<p style="margin: 0; color: #155724;"><strong>Aucun nettoyage nécessaire</strong></p>';
        print '<p style="margin: 5px 0 0 0; color: #155724;">Toutes les transactions ont déjà des descriptions propres.</p>';
        print '</div>';
    }
    
    print '</div>';
}

print '<div class="tabsAction">';
print '<a href="../account_list.php" class="butAction">Retour aux comptes</a>';
print '<a href="diagnostic_transaction_descriptions.php" class="butAction">Diagnostic transactions</a>';
print '<a href="setup.php" class="butAction">Configuration</a>';
print '</div>';

llxFooter();
$db->close();
?>