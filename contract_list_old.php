<?php
// Fichier: /htdocs/custom/revenuesharing/contract_list.php
// Liste des contrats de partage de revenus

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';

// Load translation files
$langs->load("revenuesharing@revenuesharing");

// Security check modifiée
if (!$user->id) {
    accessforbidden();
}

// Vérification des permissions
$has_permission = false;
$can_write = false;
$can_delete = false;

if ($user->admin) {
    $has_permission = true;
    $can_write = true;
    $can_delete = true;
} elseif (isset($user->rights->revenuesharing)) {
    if ($user->rights->revenuesharing->read) $has_permission = true;
    if ($user->rights->revenuesharing->write) $can_write = true;
    if ($user->rights->revenuesharing->delete) $can_delete = true;
}

if (!$has_permission) {
    accessforbidden('Accès au module Revenue Sharing non autorisé');
}

// Parameters
$action = GETPOST('action', 'alpha');
$search_ref = GETPOST('search_ref', 'alpha');
$search_collaborator = GETPOST('search_collaborator', 'int');
$search_status = GETPOST('search_status', 'alpha');
$search_type = GETPOST('search_type', 'alpha');

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : (isset($conf->liste_limit) ? $conf->liste_limit : 25);
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');

if (empty($page) || $page == -1) {
    $page = 0;
}
$offset = $limit * $page;

if (!$sortfield) $sortfield = "rc.date_creation";
if (!$sortorder) $sortorder = "DESC";

llxHeader('', 'Liste des Contrats', '');

print load_fiche_titre('📄 Liste des Contrats de Partage', '', 'generic');

// Actions
if ($action == 'validate_bulk' && $can_write) {
    $contract_ids = GETPOST('contract_ids', 'array');
    $validated_count = 0;
    $errors = array();
    
    if (!empty($contract_ids)) {
        foreach ($contract_ids as $contract_id) {
            $contract_id = (int) $contract_id;
            if ($contract_id > 0) {
                // Vérifier que le contrat existe et est en statut brouillon
                $sql_check = "SELECT status, ref FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE rowid = ".$contract_id;
                $resql_check = $db->query($sql_check);
                
                if ($resql_check) {
                    $obj_check = $db->fetch_object($resql_check);
                    if ($obj_check && $obj_check->status == 0) {
                        // Valider le contrat
                        $sql_validate = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET status = 1 WHERE rowid = ".$contract_id;
                        $resql_validate = $db->query($sql_validate);
                        
                        if ($resql_validate) {
                            $validated_count++;
                        } else {
                            $errors[] = "Erreur validation contrat ".$obj_check->ref." : ".$db->lasterror();
                        }
                    } elseif ($obj_check && $obj_check->status != 0) {
                        $errors[] = "Le contrat ".$obj_check->ref." n'est pas en statut brouillon";
                    }
                    $db->free($resql_check);
                }
            }
        }
        
        if ($validated_count > 0) {
            setEventMessages($validated_count." contrat(s) validé(s) avec succès", null, 'mesgs');
        }
        if (!empty($errors)) {
            setEventMessages(implode('<br>', $errors), null, 'warnings');
        }
    } else {
        setEventMessages("Aucun contrat sélectionné", null, 'warnings');
    }
}

if ($action == 'delete' && $can_delete) {
    $id = GETPOST('id', 'int');
    if ($id > 0) {
        // Vérification du statut avant suppression
        $sql_check = "SELECT status FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE rowid = ".((int) $id);
        $resql_check = $db->query($sql_check);
        
        if ($resql_check) {
            $obj_check = $db->fetch_object($resql_check);
            if ($obj_check->status == 0) {  // Seulement brouillons
                $sql = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE rowid = ".((int) $id);
                $resql = $db->query($sql);
                if ($resql) {
                    setEventMessages("Contrat supprimé avec succès", null, 'mesgs');
                } else {
                    setEventMessages("Erreur lors de la suppression: ".$db->lasterror(), null, 'errors');
                }
            } else {
                setEventMessages("Impossible de supprimer un contrat validé", null, 'errors');
            }
            $db->free($resql_check);
        } else {
            setEventMessages("Erreur lors de la vérification: ".$db->lasterror(), null, 'errors');
        }
    }
}

// Formulaire de recherche
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="fichecenter">';
print '<table class="noborder nohover centpercent">';
print '<tr class="liste_titre">';
print '<th class="liste_titre">🔍 Référence</th>';
print '<th class="liste_titre">👤 Collaborateur</th>';
print '<th class="liste_titre">🔮 Type</th>';
print '<th class="liste_titre">📊 Statut</th>';
print '<th class="liste_titre">&nbsp;</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><input type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" placeholder="Référence du contrat" style="width: 100%;"></td>';
print '<td>';
print '<select name="search_collaborator" style="width: 100%;">';
print '<option value="">Tous les collaborateurs</option>';

// Liste des collaborateurs pour le filtre
$sql_collab = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
$resql_collab = $db->query($sql_collab);
if ($resql_collab) {
    while ($obj_collab = $db->fetch_object($resql_collab)) {
        $selected = ($obj_collab->rowid == $search_collaborator) ? ' selected' : '';
        print '<option value="'.$obj_collab->rowid.'"'.$selected.'>'.dol_escape_htmltag($obj_collab->label).'</option>';
    }
}

print '</select>';
print '</td>';
print '<td>';
$search_type = GETPOST('search_type', 'alpha');
print '<select name="search_type" style="width: 100%;">';
print '<option value="">Tous les types</option>';
print '<option value="reel"'.($search_type === 'reel' ? ' selected' : '').'>💼 Réel</option>';
print '<option value="previsionnel"'.($search_type === 'previsionnel' ? ' selected' : '').'>🔮 Prévisionnel</option>';
print '</select>';
print '</td>';
print '<td>';
print '<select name="search_status" style="width: 100%;">';
print '<option value="">Tous les statuts</option>';
print '<option value="0"'.($search_status === '0' ? ' selected' : '').'>Brouillon</option>';
print '<option value="1"'.($search_status === '1' ? ' selected' : '').'>Validé</option>';
print '</select>';
print '</td>';
print '<td><input type="submit" class="button" value="Rechercher"></td>';
print '</tr>';

print '</table>';
print '</div>';
print '</form>';

// Boutons d'actions
if ($can_write) {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="contract_card_complete.php?action=create">➕ Nouveau Contrat</a>';
    if ($user->admin) {
        print '<a class="butAction" href="auto_create_contracts.php" style="background: #fd7e14; color: white;">🤖 Auto-création</a>';
    }
    print '</div>';
}

// Formulaire pour actions groupées
print '<form id="bulk_form" method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display: none;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="validate_bulk">';
print '<div id="bulk_contract_ids"></div>';
print '</form>';

// Boutons d'actions groupées
print '<div id="bulk_actions" class="tabsAction" style="display: none; background: #e8f4fd; padding: 10px; border: 1px solid #4CAF50; border-radius: 5px; margin: 10px 0;">';
print '<span style="margin-right: 10px;">📋 <strong id="selected_count">0</strong> contrat(s) sélectionné(s)</span>';
if ($can_write) {
    print '<a href="#" onclick="validateSelected()" class="butAction" style="background: #28a745;">✅ Valider les contrats sélectionnés</a>';
}
print '<a href="#" onclick="clearSelection()" class="butActionDelete">❌ Annuler sélection</a>';
print '</div>';

// Requête principale
$sql = "SELECT rc.rowid, rc.ref, rc.type_contrat, rc.label, rc.amount_ht, rc.amount_ttc, rc.collaborator_percentage,";
$sql .= " rc.collaborator_amount_ht, rc.net_collaborator_amount, rc.status, rc.date_creation, rc.fk_collaborator,";
$sql .= " c.label as collaborator_label,";
$sql .= " u.firstname, u.lastname,";
$sql .= " p.ref as project_ref, p.title as project_title,";
$sql .= " f.ref as facture_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract rc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = rc.fk_collaborator";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = rc.fk_project";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = rc.fk_facture";
$sql .= " WHERE 1=1";

if ($search_ref) {
    $sql .= " AND rc.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_collaborator > 0) {
    $sql .= " AND rc.fk_collaborator = ".((int) $search_collaborator);
}
if ($search_status !== '') {
    $sql .= " AND rc.status = ".((int) $search_status);
}
if ($search_type !== '') {
    $sql .= " AND rc.type_contrat = '".$db->escape($search_type)."'";
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);

    print '<div class="div-table-responsive">';
    print '<table class="noborder centpercent" id="contracts_table">';
    print '<tr class="liste_titre">';
    print '<th><input type="checkbox" id="select_all" onchange="toggleSelectAll()"> <span style="font-size: 0.9em;">Tout</span></th>';
    print '<th>📋 Référence</th>';
    print '<th>📝 Réf. client</th>';
    print '<th>👤 Collaborateur</th>';
    print '<th class="center">💰 Montant HT</th>';
    print '<th class="center">📊 % Collab.</th>';
    print '<th class="center">🤝 Part Collab.</th>';
    print '<th class="center">💵 Montant Net</th>';
    print '<th class="center">🏷️ Statut</th>';
    print '<th class="center">📅 Date</th>';
    print '<th class="center">⚙️ Actions</th>';
    print '</tr>';

    if ($num > 0) {
        $i = 0;
        while ($i < min($num, $limit)) {
            $obj = $db->fetch_object($resql);

            print '<tr class="oddeven" data-contract-id="'.$obj->rowid.'" data-contract-status="'.$obj->status.'">';

            // Case à cocher
            print '<td class="center">';
            if ($obj->status == 0 && $can_write) { // Seulement pour les brouillons
                print '<input type="checkbox" class="contract_checkbox" value="'.$obj->rowid.'" onchange="updateSelection()">';
            } else {
                print '<span style="color: #ccc;">➖</span>'; // Indicateur pour contrats non sélectionnables
            }
            print '</td>';

            // Référence avec indicateur de type
            print '<td>';
            print '<a href="contract_card_complete.php?id='.$obj->rowid.'" style="font-weight: bold;">';
            
            // Indicateur du type de contrat
            if (isset($obj->type_contrat) && $obj->type_contrat == 'previsionnel') {
                print '<span style="background: #007cba; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin-right: 5px;">🔮 PREV</span>';
            } else {
                print '<span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin-right: 5px;">💼 RÉEL</span>';
            }
            
            print dol_escape_htmltag($obj->ref);
            print '</a>';
            print '</td>';

            // Libellé
            print '<td>';
            $libelle = $obj->label ? dol_escape_htmltag($obj->label) : '';
            if (strlen($libelle) > 50) {
                print substr($libelle, 0, 47).'...';
            } else {
                print $libelle;
            }

            // Afficher les références liées ou indication prévisionnel
            if (isset($obj->type_contrat) && $obj->type_contrat == 'previsionnel') {
                print '<br><small style="color: #007cba; font-style: italic;">🔮 Contrat prévisionnel</small>';
            } elseif ($obj->project_ref || $obj->facture_ref) {
                print '<br><small style="color: #666;">';
                if ($obj->project_ref) {
                    print '📁 '.$obj->project_ref.' ';
                }
                if ($obj->facture_ref) {
                    print '🧾 '.$obj->facture_ref;
                }
                print '</small>';
            }
            print '</td>';

            // Collaborateur
            print '<td>';
            print '<a href="collaborator_card.php?id='.$obj->fk_collaborator.'">';
            print ($obj->collaborator_label ? dol_escape_htmltag($obj->collaborator_label) : dol_escape_htmltag($obj->firstname.' '.$obj->lastname));
            print '</a>';
            print '</td>';

            // Montant HT
            print '<td class="center">'.price($obj->amount_ht).'</td>';

            // Pourcentage
            print '<td class="center">';
            print '<span style="background: #e3f2fd; padding: 2px 6px; border-radius: 3px;">';
            print $obj->collaborator_percentage.'%';
            print '</span>';
            print '</td>';

            // Part collaborateur
            print '<td class="center">'.price($obj->collaborator_amount_ht).'</td>';

            // Montant net
            print '<td class="center"><strong>'.price($obj->net_collaborator_amount).'</strong></td>';

            // Statut
            print '<td class="center">';
            if ($obj->status == 0) {
                print '<span class="badge badge-status1 badge-status">📝 Brouillon</span>';
            } elseif ($obj->status == 1) {
                print '<span class="badge badge-status4 badge-status">✅ Validé</span>';
            } else {
                print '<span class="badge badge-status8 badge-status">❓ Inconnu</span>';
            }
            print '</td>';

            // Date
            print '<td class="center">';
            if ($obj->date_creation) {
                print dol_print_date($db->jdate($obj->date_creation), 'day');
            } else {
                print '-';
            }
            print '</td>';

            // Actions - seulement consulter
            print '<td class="center">';
            print '<a href="contract_card_complete.php?id='.$obj->rowid.'" title="Consulter" style="margin: 2px; font-size: 1.2em;">';
            print '👁️';
            print '</a>';
            print '</td>';

            print '</tr>';
            $i++;
        }
    } else {
        print '<tr><td colspan="11" class="center">';
        print '<div style="padding: 20px;">';
        print '<div style="font-size: 3em;">📄</div>';
        print '<h3>Aucun contrat trouvé</h3>';
        if ($can_write) {
            print '<a href="contract_card_complete.php?action=create" class="butAction">➕ Créer le premier contrat</a>';
        }
        print '</div>';
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';

    // Pagination
    if ($num > $limit) {
        print '<div class="center" style="margin: 20px 0;">';
        if ($page > 0) {
            $url_params = '';
            if ($search_ref) $url_params .= '&search_ref='.urlencode($search_ref);
            if ($search_collaborator) $url_params .= '&search_collaborator='.$search_collaborator;
            if ($search_status !== '') $url_params .= '&search_status='.$search_status;

            print '<a href="'.$_SERVER["PHP_SELF"].'?page='.($page-1).$url_params.'" class="button">← Précédent</a> ';
        }
        if ($num > $limit) {
            $url_params = '';
            if ($search_ref) $url_params .= '&search_ref='.urlencode($search_ref);
            if ($search_collaborator) $url_params .= '&search_collaborator='.$search_collaborator;
            if ($search_status !== '') $url_params .= '&search_status='.$search_status;

            print '<a href="'.$_SERVER["PHP_SELF"].'?page='.($page+1).$url_params.'" class="button">Suivant →</a>';
        }
        print '</div>';
    }

    // Statistiques en bas
    print '<br>';
    print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px;">';
    print '<h4>📊 Statistiques</h4>';

    // Sélecteur d'année et filtre collaborateur pour statistiques
    $year = GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y');
    $filter_stats_collaborator = GETPOST('filter_stats_collaborator', 'int'); // Filtre séparé pour stats
    
    print '<div class="center" style="margin: 15px 0;">';
    print '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; display: inline-block;">';
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: inline-block;">';
    
    // Conserver les paramètres de recherche de la liste
    if ($search_ref) print '<input type="hidden" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
    if ($search_collaborator) print '<input type="hidden" name="search_collaborator" value="'.$search_collaborator.'">';
    if ($search_status !== '') print '<input type="hidden" name="search_status" value="'.$search_status.'">';
    
    // Filtre collaborateur pour statistiques
    print '👤 <strong>Statistiques par :</strong> ';
    print '<select name="filter_stats_collaborator" onchange="this.form.submit()" style="font-size: 1em; padding: 5px; margin-right: 15px;">';
    print '<option value="">Tous les collaborateurs</option>';
    
    $sql_stats_collabs = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
    $resql_stats_collabs = $db->query($sql_stats_collabs);
    if ($resql_stats_collabs) {
        while ($obj_stats_collab = $db->fetch_object($resql_stats_collabs)) {
            $selected = ($obj_stats_collab->rowid == $filter_stats_collaborator) ? ' selected' : '';
            print '<option value="'.$obj_stats_collab->rowid.'"'.$selected.'>'.dol_escape_htmltag($obj_stats_collab->label).'</option>';
        }
        $db->free($resql_stats_collabs);
    }
    print '</select>';
    
    print '📅 <strong>Année :</strong> ';
    print '<select name="year" onchange="this.form.submit()" style="font-size: 1em; padding: 5px;">';
    print '<option value="">Toutes les années</option>';
    for ($y = date('Y'); $y >= date('Y') - 5; $y--) {
        $selected = ($y == $year) ? ' selected' : '';
        print '<option value="'.$y.'"'.$selected.'>'.$y.'</option>';
    }
    print '</select>';
    print '</form>';
    print '</div>';
    print '</div>';

    // Calculer les totaux avec filtre par année
    $sql_total = "SELECT COUNT(*) as nb_total,";
    $sql_total .= " SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as nb_brouillon,";
    $sql_total .= " SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as nb_valide,";
    $sql_total .= " COALESCE(SUM(CASE WHEN status = 1 THEN amount_ht ELSE 0 END), 0) as total_ca,";
    $sql_total .= " COALESCE(SUM(CASE WHEN status = 1 THEN net_collaborator_amount ELSE 0 END), 0) as total_collaborateur";
    $sql_total .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract";
    $sql_total .= " WHERE 1=1";

    if ($search_collaborator > 0) {
        $sql_total .= " AND fk_collaborator = ".((int) $search_collaborator);
    }
    if ($filter_stats_collaborator > 0) {
        $sql_total .= " AND fk_collaborator = ".((int) $filter_stats_collaborator);
    }
    if ($year) {
        $sql_total .= " AND YEAR(date_creation) = ".((int) $year);
    }

    $resql_total = $db->query($sql_total);
    if ($resql_total) {
        $obj_total = $db->fetch_object($resql_total);
        $part_studio = $obj_total->total_ca - $obj_total->total_collaborateur;

        print '<h4>📊 Statistiques '.($year ? $year : 'toutes années').'</h4>';
        print '<table class="noborder">';
        print '<tr>';
        print '<td><strong>Total contrats :</strong></td><td>'.$obj_total->nb_total.'</td>';
        print '<td style="padding-left: 20px;"><strong>Brouillons :</strong></td><td>'.$obj_total->nb_brouillon.'</td>';
        print '<td style="padding-left: 20px;"><strong>Validés :</strong></td><td>'.$obj_total->nb_valide.'</td>';
        print '</tr>';
        print '<tr>';
        print '<td><strong>CA validé :</strong></td><td><strong>'.price($obj_total->total_ca).'</strong></td>';
        print '<td style="padding-left: 20px;"><strong>Part collaborateurs :</strong></td><td>'.price($obj_total->total_collaborateur).'</td>';
        print '<td style="padding-left: 20px;"><strong>Part studio :</strong></td><td><strong style="color: #28a745;">'.price($part_studio).'</strong></td>';
        print '</tr>';
        if ($obj_total->total_ca > 0) {
            $pct_collab = round(($obj_total->total_collaborateur / $obj_total->total_ca) * 100, 1);
            $pct_studio = round(($part_studio / $obj_total->total_ca) * 100, 1);
            print '<tr>';
            print '<td colspan="2"></td>';
            print '<td style="padding-left: 20px;"><em>('.$pct_collab.'%)</em></td>';
            print '<td style="padding-left: 20px;"><em>('.$pct_studio.'%)</em></td>';
            print '</tr>';
        }
        print '</table>';
    }

    print '</div>';

} else {
    print '<div style="color: red; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;">';
    print '<h3>❌ Erreur de base de données</h3>';
    print '<p>Erreur : '.$db->lasterror().'</p>';
    print '</div>';
}

print '<br>';
print '<div class="tabsAction">';
print '<a href="index.php" class="butAction">🏠 Retour au Dashboard</a>';
print '<a href="collaborator_list.php" class="butAction">👥 Collaborateurs</a>';
print '<a href="admin/setup.php" class="butAction">⚙️ Configuration</a>';
print '</div>';

// JavaScript pour gestion sélection multiple
print '<script type="text/javascript">';
print '
function toggleSelectAll() {
    var selectAll = document.getElementById("select_all");
    var checkboxes = document.querySelectorAll(".contract_checkbox");
    
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelection();
}

function updateSelection() {
    var checkboxes = document.querySelectorAll(".contract_checkbox:checked");
    var count = checkboxes.length;
    
    document.getElementById("selected_count").textContent = count;
    
    if (count > 0) {
        document.getElementById("bulk_actions").style.display = "block";
    } else {
        document.getElementById("bulk_actions").style.display = "none";
        document.getElementById("select_all").checked = false;
    }
}

function validateSelected() {
    var checkboxes = document.querySelectorAll(".contract_checkbox:checked");
    
    if (checkboxes.length === 0) {
        alert("Veuillez sélectionner au moins un contrat à valider.");
        return;
    }
    
    if (!confirm("Êtes-vous sûr de vouloir valider " + checkboxes.length + " contrat(s) ? Cette action est irréversible.")) {
        return;
    }
    
    // Créer les champs hidden pour chaque contrat sélectionné
    var bulkContainer = document.getElementById("bulk_contract_ids");
    bulkContainer.innerHTML = "";
    
    checkboxes.forEach(function(checkbox, index) {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = "contract_ids[]";
        input.value = checkbox.value;
        bulkContainer.appendChild(input);
    });
    
    // Soumettre le formulaire
    document.getElementById("bulk_form").submit();
}

function clearSelection() {
    var checkboxes = document.querySelectorAll(".contract_checkbox");
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
    document.getElementById("select_all").checked = false;
    updateSelection();
}

// Initialiser l\'état au chargement
document.addEventListener("DOMContentLoaded", function() {
    updateSelection();
});
';
print '</script>';

llxFooter();
$db->close();
?>
