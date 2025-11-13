<?php
// Fichier: auto_create_contracts_final.php  
// Version finale - Auto-création de contrats avec gestion complète du matching en PHP

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$selected_invoices = GETPOST('selected_invoices', 'array');

// Filtres
$filter_collaborator = GETPOST('filter_collaborator', 'int');
$filter_year = GETPOST('filter_year', 'int') ? GETPOST('filter_year', 'int') : date('Y');
$filter_status = GETPOST('filter_status', 'int');
$filter_intervenant = GETPOST('filter_intervenant', 'alpha');

llxHeader('', 'Auto-création Contrats', '');

print load_fiche_titre(' Auto-création de Contrats depuis Factures (Version Finale)', '', 'generic');

// Fonction pour trouver le collaborateur correspondant - VERSION COMPLETE
function findMatchingCollaborator($db, $intervenant_name) {
    // Requête simple sans aucun LIKE
    $sql_collabs = "SELECT rowid, label, default_percentage FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1";
    $resql_collabs = $db->query($sql_collabs);
    
    if ($resql_collabs) {
        $intervenant_clean = strtolower(trim($intervenant_name));
        
        $best_match = null;
        $best_match_score = 0;
        
        while ($obj_collab = $db->fetch_object($resql_collabs)) {
            $label_clean = strtolower(trim($obj_collab->label));
            
            // Test 1: Correspondance exacte (score 100)
            if ($label_clean == $intervenant_clean) {
                $db->free($resql_collabs);
                return $obj_collab;
            }
            
            // Test 2: Le label contient l'intervenant (score 80)
            if (strpos($label_clean, $intervenant_clean) !== false) {
                if (80 > $best_match_score) {
                    $best_match = $obj_collab;
                    $best_match_score = 80;
                }
            }
            
            // Test 3: L'intervenant contient le label (score 60)
            if (strpos($intervenant_clean, $label_clean) !== false) {
                if (60 > $best_match_score) {
                    $best_match = $obj_collab;
                    $best_match_score = 60;
                }
            }
            
            // Test 4: Correspondance partielle (mots communs) (score 40)
            $intervenant_words = explode(' ', $intervenant_clean);
            $label_words = explode(' ', $label_clean);
            
            $common_words = 0;
            foreach ($intervenant_words as $word1) {
                if (strlen($word1) > 2) { // Ignorer les mots trop courts
                    foreach ($label_words as $word2) {
                        if (strlen($word2) > 2 && $word1 == $word2) {
                            $common_words++;
                            break;
                        }
                    }
                }
            }
            
            if ($common_words > 0) {
                $partial_score = min(40, $common_words * 15);
                if ($partial_score > $best_match_score) {
                    $best_match = $obj_collab;
                    $best_match_score = $partial_score;
                }
            }
        }
        
        $db->free($resql_collabs);
        return $best_match;
    }
    return null;
}

// Traitement de la création automatique
if ($action == 'create_contracts' && $confirm == 'yes' && !empty($selected_invoices)) {
    print '<h3>Création des contrats en cours...</h3>';
    
    $created_contracts = 0;
    $errors = 0;
    $details = array();
    
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    
    foreach ($selected_invoices as $invoice_id) {
        $invoice_id = (int) $invoice_id;
        
        // Récupérer les infos de la facture avec colonnes compatibles
        $sql_invoice = "SELECT f.rowid, f.ref, f.ref_client, f.datef, f.total_ht, f.total_ttc, fe.intervenant, fe.analytique";
        $sql_invoice .= " FROM ".MAIN_DB_PREFIX."facture f";
        $sql_invoice .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid";
        $sql_invoice .= " WHERE f.rowid = ".((int) $invoice_id);
        
        $resql_invoice = $db->query($sql_invoice);
        if ($resql_invoice && $db->num_rows($resql_invoice) > 0) {
            $obj_invoice = $db->fetch_object($resql_invoice);
            
            // Rechercher le collaborateur correspondant avec fonction PHP
            $matching_collab = findMatchingCollaborator($db, $obj_invoice->intervenant);
            
            if ($matching_collab) {
                // Vérifier qu'un contrat n'existe pas déjà pour cette facture
                $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE fk_facture = ".$invoice_id;
                $resql_check = $db->query($sql_check);
                
                if ($resql_check && $db->num_rows($resql_check) == 0) {
                    // Créer une référence avec la date de la facture
                    $facture_date = date('Ymd', strtotime($obj_invoice->datef));
                    
                    // Compter les contrats créés le même jour pour avoir une séquence
                    $sql_count = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE DATE(date_creation) = DATE('".$obj_invoice->datef."')";
                    $resql_count = $db->query($sql_count);
                    $sequence = 1;
                    if ($resql_count) {
                        $obj_count = $db->fetch_object($resql_count);
                        $sequence = ($obj_count->nb ?? 0) + 1;
                        $db->free($resql_count);
                    }
                    
                    $ref = 'CONT-'.$facture_date.'-'.str_pad($sequence, 2, '0', STR_PAD_LEFT);
                    
                    // Créer le libellé avec ref_client si disponible, sinon format par défaut
                    if ($obj_invoice->ref_client && trim($obj_invoice->ref_client)) {
                        $label = trim($obj_invoice->ref_client).' - '.$obj_invoice->ref.' - '.$obj_invoice->intervenant;
                    } else {
                        $label = $obj_invoice->ref.' - '.$obj_invoice->intervenant;
                    }
                    
                    // Note privée simplifiée avec source facture
                    $note_private = 'Facture source: '.$obj_invoice->ref;
                    
                    $percentage = $matching_collab->default_percentage ? $matching_collab->default_percentage : 20;
                    $collaborator_amount_ht = ($obj_invoice->total_ht * $percentage) / 100;
                    $studio_amount_ht = $obj_invoice->total_ht - $collaborator_amount_ht;
                    $net_collaborator_amount = $collaborator_amount_ht;
                    
                    // Utiliser la date de la facture pour la date de création du contrat
                    $contract_date = $obj_invoice->datef ? "'".$obj_invoice->datef."'" : "NOW()";
                    
                    $sql_create = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_contract (";
                    $sql_create .= "ref, fk_collaborator, fk_facture, label, ";
                    $sql_create .= "amount_ht, amount_ttc, collaborator_percentage, ";
                    $sql_create .= "collaborator_amount_ht, studio_amount_ht, net_collaborator_amount, ";
                    $sql_create .= "nb_sessions, cost_per_session, total_costs, ";
                    $sql_create .= "note_private, status, date_creation, fk_user_creat";
                    $sql_create .= ") VALUES (";
                    $sql_create .= "'".$db->escape($ref)."', ";
                    $sql_create .= ((int) $matching_collab->rowid).", ";
                    $sql_create .= ((int) $invoice_id).", ";
                    $sql_create .= "'".$db->escape($label)."', ";
                    $sql_create .= $obj_invoice->total_ht.", ";
                    $sql_create .= $obj_invoice->total_ttc.", ";
                    $sql_create .= $percentage.", ";
                    $sql_create .= $collaborator_amount_ht.", ";
                    $sql_create .= $studio_amount_ht.", ";
                    $sql_create .= $net_collaborator_amount.", ";
                    $sql_create .= "0, 0, 0, ";
                    $sql_create .= "'".$db->escape($note_private)."', ";
                    $sql_create .= "0, ".$contract_date.", ";
                    $sql_create .= $user->id;
                    $sql_create .= ")";
                    
                    $resql_create = $db->query($sql_create);
                    if ($resql_create) {
                        print '<div style="color: green; margin: 5px 0;"><strong>'.$ref.'</strong> - '.$obj_invoice->ref.' → '.$matching_collab->label.' ('.$percentage.'% = '.price($net_collaborator_amount).')</div>';
                        $created_contracts++;
                    } else {
                        print '<div style="color: red; margin: 5px 0;">Erreur création '.$obj_invoice->ref.' : '.$db->lasterror().'</div>';
                        $errors++;
                    }
                } else {
                    print '<div style="color: orange; margin: 5px 0;"><strong>'.$obj_invoice->ref.'</strong> - Contrat déjà existant</div>';
                }
                
                if ($resql_check) $db->free($resql_check);
            } else {
                print '<div style="color: red; margin: 5px 0;"><strong>'.$obj_invoice->ref.'</strong> - Collaborateur "'.$obj_invoice->intervenant.'" non trouvé</div>';
                $errors++;
            }
            
            if ($resql_invoice) $db->free($resql_invoice);
        }
    }
    
    print '</div>';
    
    print '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 20px 0;">';
    print '<h4>Résumé de l\'auto-création :</h4>';
    print '<ul>';
    print '<li><strong>Contrats créés :</strong> '.$created_contracts.'</li>';
    print '<li><strong>Erreurs :</strong> '.$errors.'</li>';
    print '</ul>';
    
    if ($created_contracts > 0) {
        print '<p><a href="contract_list.php" class="button">Voir les nouveaux contrats</a></p>';
    }
    print '</div>';
    
} else {
    
    // Mode sélection - VERSION COMPLETEMENT SECURISEE
    print '<h3>Sélection des factures pour auto-création</h3>';

    // Information sur le filtre Analytique
    print '<div style="background: #e8f5e8; border: 1px solid #4caf50; border-radius: 8px; padding: 15px; margin: 15px 0;">';
    print '<h4> Filtre Analytique Actif</h4>';
    print '<p><strong>Seules les factures avec Analytique = "STU"</strong> (studio d\'enregistrement, prise de son, mixage, mastering) sont affichées dans cette liste.</p>';
    print '</div>';
    
    // === SECTION FILTRES ===
    print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 15px 0;">';
    print '<h4>Filtres de sélection</h4>';
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">';
    
    // Filtre par collaborateur trouvé
    print '<div>';
    print '<label style="display: block; font-weight: bold; margin-bottom: 5px;"> Collaborateur :</label>';
    print '<select name="filter_collaborator" style="padding: 5px; min-width: 150px;">';
    print '<option value="">Tous les collaborateurs</option>';
    
    $sql_collabs_filter = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
    $resql_collabs_filter = $db->query($sql_collabs_filter);
    if ($resql_collabs_filter) {
        while ($obj_collab = $db->fetch_object($resql_collabs_filter)) {
            $selected = ($obj_collab->rowid == $filter_collaborator) ? ' selected' : '';
            print '<option value="'.$obj_collab->rowid.'"'.$selected.'>'.dol_escape_htmltag($obj_collab->label).'</option>';
        }
        $db->free($resql_collabs_filter);
    }
    print '</select>';
    print '</div>';
    
    // Filtre par année
    print '<div>';
    print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Année :</label>';
    print '<select name="filter_year" style="padding: 5px;">';
    for ($y = date('Y'); $y >= date('Y') - 5; $y--) {
        $selected = ($y == $filter_year) ? ' selected' : '';
        print '<option value="'.$y.'"'.$selected.'>'.$y.'</option>';
    }
    print '</select>';
    print '</div>';
    
    // Filtre par statut facture
    print '<div>';
    print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Statut :</label>';
    print '<select name="filter_status" style="padding: 5px;">';
    print '<option value="">Tous statuts</option>';
    $selected1 = ($filter_status == 1) ? ' selected' : '';
    $selected2 = ($filter_status == 2) ? ' selected' : '';
    print '<option value="1"'.$selected1.'>Validées</option>';
    print '<option value="2"'.$selected2.'>Payées</option>';
    print '</select>';
    print '</div>';
    
    // Filtre par intervenant (recherche)
    print '<div>';
    print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Intervenant :</label>';
    print '<input type="text" name="filter_intervenant" value="'.dol_escape_htmltag($filter_intervenant).'" placeholder="Rechercher..." style="padding: 5px; min-width: 150px;">';
    print '</div>';
    
    // Boutons
    print '<div style="align-self: end;">';
    print '<input type="submit" value="Filtrer" class="button" style="margin-right: 5px;">';
    print '<a href="'.$_SERVER["PHP_SELF"].'" class="button">Reset</a>';
    print '</div>';
    
    print '</form>';
    print '</div>';
    
    // Afficher les filtres actifs
    $active_filters = array();
    if ($filter_collaborator > 0) {
        $sql_collab_name = "SELECT label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE rowid = ".((int) $filter_collaborator);
        $resql_collab_name = $db->query($sql_collab_name);
        if ($resql_collab_name && $db->num_rows($resql_collab_name) > 0) {
            $obj_collab_name = $db->fetch_object($resql_collab_name);
            $active_filters[] = " Collaborateur : ".$obj_collab_name->label;
            $db->free($resql_collab_name);
        }
    }
    if ($filter_year && $filter_year != date('Y')) {
        $active_filters[] = "Année : ".$filter_year;
    }
    if ($filter_status > 0) {
        $status_label = ($filter_status == 1) ? "Validées" : "Payées";
        $active_filters[] = "Statut : ".$status_label;
    }
    if ($filter_intervenant) {
        $active_filters[] = "Intervenant : \"".$filter_intervenant."\"";
    }
    
    if (count($active_filters) > 0) {
        print '<div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">';
        print '<strong>Filtres actifs :</strong> '.implode(" | ", $active_filters);
        print '</div>';
    }
    
    // Récupérer les factures avec colonnes compatibles
    $sql_invoices = "SELECT f.rowid, f.ref, f.ref_client, f.datef, f.total_ht, f.total_ttc, f.fk_statut, fe.intervenant, fe.analytique";
    $sql_invoices .= " FROM ".MAIN_DB_PREFIX."facture f";
    $sql_invoices .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid";
    $sql_invoices .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract rc ON rc.fk_facture = f.rowid";
    $sql_invoices .= " WHERE fe.intervenant IS NOT NULL AND fe.intervenant != ''";
    $sql_invoices .= " AND fe.analytique = 'STU'";
    $sql_invoices .= " AND f.fk_statut >= 1";
    $sql_invoices .= " AND rc.rowid IS NULL";
    
    // Filtres appliqués côté SQL quand possible
    if ($filter_year) {
        $sql_invoices .= " AND YEAR(f.datef) = ".((int) $filter_year);
    }
    if ($filter_status > 0) {
        $sql_invoices .= " AND f.fk_statut = ".((int) $filter_status);
    }
    if ($filter_intervenant) {
        $sql_invoices .= " AND fe.intervenant LIKE '%".$db->escape($filter_intervenant)."%'";
    }
    
    $sql_invoices .= " ORDER BY f.datef DESC";
    $sql_invoices .= " LIMIT 200"; // Augmenté pour les filtres
    
    $resql_invoices = $db->query($sql_invoices);
    
    if ($resql_invoices) {
        $candidates = array();
        
        // Traitement des factures une par une en PHP
        while ($obj_invoice = $db->fetch_object($resql_invoices)) {
            $matching_collab = findMatchingCollaborator($db, $obj_invoice->intervenant);
            
            if ($matching_collab) {
                // Filtre par collaborateur trouvé
                if ($filter_collaborator > 0 && $matching_collab->rowid != $filter_collaborator) {
                    continue; // Skip cette facture si le collaborateur ne correspond pas au filtre
                }
                
                $obj_invoice->matching_collab = $matching_collab;
                $candidates[] = $obj_invoice;
            }
        }
        
        $db->free($resql_invoices);
        
        if (count($candidates) > 0) {
            print '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">';
            print '<h4>'.count($candidates).' facture(s) candidate(s) trouvée(s)</h4>';
            print '</div>';
            
            print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="action" value="create_contracts">';
            print '<input type="hidden" name="confirm" value="yes">';
            
            print '<table class="noborder centpercent">';
            print '<tr class="liste_titre">';
            print '<th class="center"><input type="checkbox" id="checkall" onclick="checkAll()"></th>';
            print '<th>Facture</th>';
            print '<th>Date</th>';
            print '<th>Réf. Client</th>';
            print '<th>Intervenant</th>';
            print '<th>Analytique</th>';
            print '<th>Collaborateur trouvé</th>';
            print '<th class="center">Total HT</th>';
            print '<th class="center">% Défaut</th>';
            print '<th class="center">Part Collab.</th>';
            print '<th class="center">Statut</th>';
            print '</tr>';
            
            $total_ht = 0;
            $total_collab = 0;
            
            foreach ($candidates as $obj_candidate) {
                $percentage = $obj_candidate->matching_collab->default_percentage ? $obj_candidate->matching_collab->default_percentage : 20;
                $collab_amount = ($obj_candidate->total_ht * $percentage) / 100;
                
                $statut_libelle = '';
                switch ($obj_candidate->fk_statut) {
                    case 1: $statut_libelle = 'Validée'; break;
                    case 2: $statut_libelle = 'Payée'; break;
                    default: $statut_libelle = '❓ Statut '.$obj_candidate->fk_statut;
                }
                
                print '<tr class="oddeven">';
                print '<td class="center"><input type="checkbox" name="selected_invoices[]" value="'.$obj_candidate->rowid.'" class="invoice_checkbox"></td>';
                print '<td><strong>'.$obj_candidate->ref.'</strong></td>';
                print '<td>'.dol_print_date($db->jdate($obj_candidate->datef), 'day').'</td>';
                print '<td>'.($obj_candidate->ref_client ? dol_trunc($obj_candidate->ref_client, 30) : '-').'</td>';
                print '<td><strong>'.$obj_candidate->intervenant.'</strong></td>';
                print '<td><span style="background: #4caf50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.9em;"> '.$obj_candidate->analytique.'</span></td>';
                print '<td style="color: green;">'.$obj_candidate->matching_collab->label.'</td>';
                print '<td class="center">'.price($obj_candidate->total_ht).'</td>';
                print '<td class="center">'.$percentage.'%</td>';
                print '<td class="center"><strong>'.price($collab_amount).'</strong></td>';
                print '<td class="center">'.$statut_libelle.'</td>';
                print '</tr>';
                
                $total_ht += $obj_candidate->total_ht;
                $total_collab += $collab_amount;
            }
            
            print '<tr style="background: #e3f2fd; font-weight: bold;">';
            print '<td colspan="7"><strong>TOTAUX SÉLECTION</strong></td>';
            print '<td class="center"><strong>'.price($total_ht).'</strong></td>';
            print '<td class="center">-</td>';
            print '<td class="center"><strong>'.price($total_collab).'</strong></td>';
            print '<td class="center">-</td>';
            print '</tr>';
            
            print '</table>';
            
            print '<div class="center" style="margin: 20px 0;">';
            print '<input type="submit" class="button" value=" Créer les contrats sélectionnés" onclick="return confirm(\'Confirmer la création automatique des contrats pour les factures sélectionnées ?\')\" style="background: #28a745; color: white; padding: 10px 20px; font-size: 1.1em;">';
            print '</div>';
            
            print '</form>';
            
            // JavaScript pour la sélection
            print '<script>
            function checkAll() {
                var checkboxes = document.getElementsByClassName("invoice_checkbox");
                var checkall = document.getElementById("checkall");
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = checkall.checked;
                }
            }
            </script>';
            
        } else {
            print '<div style="background: var(--colorbacktabcard1); padding: 15px; border-radius: 5px; color: var(--colortext);">';
            print '<h4>Aucune facture candidate trouvée</h4>';
            print '<p>Aucun intervenant ne correspond aux collaborateurs actifs.</p>';
            print '<p><a href="analyze_extrafields.php" class="button">Retour à l\'analyse</a></p>';
            print '</div>';
        }
    } else {
        print '<div style="color: red;">Erreur SQL : '.$db->lasterror().'</div>';
    }
}

print '<div class="tabsAction">';
print '<a href="analyze_extrafields.php" class="butAction">Analyse Extrafields</a>';
print '<a href="contract_list.php" class="butAction">Contrats</a>';
print '<a href="index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>