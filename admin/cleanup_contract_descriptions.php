<?php
// Fichier: admin/cleanup_contract_descriptions.php
// Script pour nettoyer les descriptions des contrats en supprimant "Contrat auto-créé pour factu"

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Nettoyage des descriptions de contrats', '');

print load_fiche_titre('🧹 Nettoyage des descriptions de contrats', '', 'generic');

$action = GETPOST('action', 'alpha');

if ($action == 'cleanup') {
    
    // Vérification du token CSRF
    if (!newToken('check')) {
        setEventMessages('Token de sécurité invalide', null, 'errors');
        $action = '';
    } else {
        print '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        print '<h3 style="margin-top: 0; color: #0c5460;">Nettoyage en cours...</h3>';
        
        // Rechercher tous les contrats contenant le texte à supprimer
        $sql_find = "SELECT rowid, ref, label 
                     FROM ".MAIN_DB_PREFIX."revenuesharing_contract 
                     WHERE label LIKE '%Contrat auto-créé pour facture%' 
                     OR label LIKE '%Contrat auto-créé pour factu%'";
        
        $resql_find = $db->query($sql_find);
        
        if ($resql_find) {
            $contracts_found = array();
            $contracts_to_update = 0;
            
            while ($contract = $db->fetch_object($resql_find)) {
                $contracts_found[] = $contract;
                $contracts_to_update++;
            }
            $db->free($resql_find);
            
            print '<p><strong>'.$contracts_to_update.' contrat(s) trouvé(s)</strong> contenant le texte à supprimer.</p>';
            
            if ($contracts_to_update > 0) {
                print '<table class="noborder" style="margin: 10px 0;">';
                print '<tr class="liste_titre">';
                print '<th>Référence</th>';
                print '<th>Label actuel</th>';
                print '<th>Nouveau label</th>';
                print '<th>Résultat</th>';
                print '</tr>';
                
                $updated_count = 0;
                $error_count = 0;
                
                foreach ($contracts_found as $contract) {
                    // Supprimer le texte indésirable (variantes possibles)
                    $new_label = str_replace('Contrat auto-créé pour facture ', '', $contract->label);
                    $new_label = str_replace('Contrat auto-créé pour factu', '', $new_label);
                    $new_label = str_replace('    Contrat auto-créé pour factu', '', $new_label);
                    $new_label = trim($new_label); // Supprimer les espaces en trop
                    
                    print '<tr class="oddeven">';
                    print '<td><strong>'.$contract->ref.'</strong></td>';
                    print '<td style="color: #666;">'.dol_escape_htmltag($contract->label).'</td>';
                    print '<td style="color: #28a745;">'.dol_escape_htmltag($new_label).'</td>';
                    
                    // Mettre à jour en base
                    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract 
                                   SET label = '".$db->escape($new_label)."'
                                   WHERE rowid = ".((int) $contract->rowid);
                    
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
                print '<li><strong>'.$contracts_to_update.'</strong> contrat(s) analysé(s)</li>';
                print '<li><span style="color: #28a745;"><strong>'.$updated_count.'</strong> contrat(s) mis à jour avec succès</span></li>';
                if ($error_count > 0) {
                    print '<li><span style="color: #dc3545;"><strong>'.$error_count.'</strong> erreur(s) rencontrée(s)</span></li>';
                }
                print '</ul>';
                print '</div>';
                
                if ($updated_count > 0) {
                    setEventMessages($updated_count.' contrat(s) nettoyé(s) avec succès', null, 'mesgs');
                }
                if ($error_count > 0) {
                    setEventMessages($error_count.' erreur(s) lors du nettoyage', null, 'warnings');
                }
                
            } else {
                print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;">';
                print '<p style="margin: 0; color: #856404;"><strong>Aucun contrat à nettoyer</strong></p>';
                print '<p style="margin: 5px 0 0 0; color: #856404;">Tous les contrats ont déjà des descriptions propres.</p>';
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
    
    // D'abord, compter les contrats concernés
    $sql_count = "SELECT COUNT(*) as nb_contracts 
                  FROM ".MAIN_DB_PREFIX."revenuesharing_contract 
                  WHERE label LIKE '%Contrat auto-créé pour facture%' 
                  OR label LIKE '%Contrat auto-créé pour factu%'";
    
    $resql_count = $db->query($sql_count);
    $nb_contracts = 0;
    
    if ($resql_count) {
        $count_result = $db->fetch_object($resql_count);
        $nb_contracts = $count_result->nb_contracts;
        $db->free($resql_count);
    }
    
    print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    print '<h3 style="margin-top: 0; color: #495057;">Description de l\'opération</h3>';
    print '<p>Ce script va supprimer le texte <strong>"Contrat auto-créé pour facture"</strong> (et ses variantes) de tous les labels de contrats qui le contiennent.</p>';
    print '<p><strong>Contrats concernés :</strong> <span style="color: #dc3545; font-weight: bold;">'.$nb_contracts.' contrat(s)</span></p>';
    
    if ($nb_contracts > 0) {
        // Afficher un aperçu des contrats qui seront modifiés
        print '<h4>Aperçu des contrats qui seront modifiés :</h4>';
        
        $sql_preview = "SELECT rowid, ref, label 
                        FROM ".MAIN_DB_PREFIX."revenuesharing_contract 
                        WHERE label LIKE '%Contrat auto-créé pour facture%' 
                        OR label LIKE '%Contrat auto-créé pour factu%'
                        ORDER BY ref
                        LIMIT 10";
        
        $resql_preview = $db->query($sql_preview);
        
        if ($resql_preview) {
            print '<table class="noborder" style="margin: 10px 0;">';
            print '<tr class="liste_titre">';
            print '<th>Référence</th>';
            print '<th>Label actuel</th>';
            print '<th>Label après nettoyage</th>';
            print '</tr>';
            
            $count_preview = 0;
            while ($contract = $db->fetch_object($resql_preview) && $count_preview < 10) {
                // Supprimer toutes les variantes possibles
                $new_label = str_replace('Contrat auto-créé pour facture ', '', $contract->label);
                $new_label = str_replace('Contrat auto-créé pour factu', '', $new_label);
                $new_label = str_replace('    Contrat auto-créé pour factu', '', $new_label);
                $new_label = trim($new_label);
                
                print '<tr class="oddeven">';
                print '<td><strong>'.$contract->ref.'</strong></td>';
                print '<td style="color: #666;">'.dol_escape_htmltag($contract->label).'</td>';
                print '<td style="color: #28a745;">'.dol_escape_htmltag($new_label).'</td>';
                print '</tr>';
                
                $count_preview++;
            }
            
            if ($nb_contracts > 10) {
                print '<tr><td colspan="3" style="text-align: center; font-style: italic; color: #666;">... et '.($nb_contracts - 10).' autre(s) contrat(s)</td></tr>';
            }
            
            print '</table>';
            $db->free($resql_preview);
        }
        
        print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        print '<p style="margin: 0; color: #856404;"><strong>Attention</strong></p>';
        print '<p style="margin: 5px 0 0 0; color: #856404;">Cette opération est irréversible. Assurez-vous d\'avoir fait une sauvegarde de la base de données.</p>';
        print '</div>';
        
        // Formulaire de confirmation
        print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" onsubmit="return confirm(\'Êtes-vous sûr de vouloir nettoyer '.$nb_contracts.' contrat(s) ? Cette action est irréversible.\');">';
        print '<input type="hidden" name="action" value="cleanup">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<div style="text-align: center; margin: 20px 0;">';
        print '<input type="submit" class="button" value="🧹 Nettoyer '.$nb_contracts.' contrat(s)" style="background: #dc3545; color: white; font-weight: bold; padding: 12px 24px;">';
        print '</div>';
        print '</form>';
        
    } else {
        print '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;">';
        print '<p style="margin: 0; color: #155724;"><strong>Aucun nettoyage nécessaire</strong></p>';
        print '<p style="margin: 5px 0 0 0; color: #155724;">Tous les contrats ont déjà des descriptions propres.</p>';
        print '</div>';
    }
    
    print '</div>';
}

print '<div class="tabsAction">';
print '<a href="../contract_list.php" class="butAction">Retour aux contrats</a>';
print '<a href="setup.php" class="butAction">Configuration</a>';
print '</div>';

llxFooter();
$db->close();
?>