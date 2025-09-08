<?php
// Fichier: /htdocs/custom/revenuesharing/contract_list.php
// Liste des contrats de partage de revenus

$dolibarr_main_document_root = '/home/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

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
if ($action == 'delete' && $can_delete) {
    $id = GETPOST('id', 'int');
    if ($id > 0) {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE rowid = ".((int) $id);
        $resql = $db->query($sql);
        if ($resql) {
            setEventMessages("Contrat supprimé", null, 'mesgs');
        } else {
            setEventMessages("Erreur lors de la suppression: ".$db->lasterror(), null, 'errors');
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

// Bouton Nouveau
if ($can_write) {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="contract_card.php?action=create">➕ Nouveau Contrat</a>';
    print '</div>';
}

// Requête principale
$sql = "SELECT rc.rowid, rc.ref, rc.label, rc.amount_ht, rc.amount_ttc, rc.collaborator_percentage,";
$sql .= " rc.collaborator_amount_ht, rc.net_collaborator_amount, rc.status, rc.date_creation,";
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

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);

    print '<div class="div-table-responsive">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>📋 Référence</th>';
    print '<th>📝 Libellé</th>';
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

            print '<tr class="oddeven">';

            // Référence
            print '<td>';
            print '<a href="contract_card.php?id='.$obj->rowid.'" style="font-weight: bold;">';
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

            // Afficher les références liées
            if ($obj->project_ref || $obj->facture_ref) {
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

            // Actions
            print '<td class="center">';
            print '<a href="contract_card.php?id='.$obj->rowid.'" title="Voir" style="margin: 2px;">';
            print '👁️';
            print '</a>';

            if ($can_write) {
                print '<a href="contract_card.php?id='.$obj->rowid.'&action=edit" title="Modifier" style="margin: 2px;">';
                print '✏️';
                print '</a>';
            }

            if ($can_delete) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'" title="Supprimer" onclick="return confirm(\'Confirmer la suppression ?\')" style="margin: 2px;">';
                print '🗑️';
                print '</a>';
            }
            print '</td>';

            print '</tr>';
            $i++;
        }
    } else {
        print '<tr><td colspan="10" class="center">';
        print '<div style="padding: 20px;">';
        print '<div style="font-size: 3em;">📄</div>';
        print '<h3>Aucun contrat trouvé</h3>';
        if ($can_write) {
            print '<a href="contract_card.php?action=create" class="butAction">➕ Créer le premier contrat</a>';
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

    // Calculer les totaux
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

    $resql_total = $db->query($sql_total);
    if ($resql_total) {
        $obj_total = $db->fetch_object($resql_total);

        print '<table class="noborder">';
        print '<tr>';
        print '<td><strong>Total contrats :</strong></td><td>'.$obj_total->nb_total.'</td>';
        print '<td style="padding-left: 20px;"><strong>Brouillons :</strong></td><td>'.$obj_total->nb_brouillon.'</td>';
        print '<td style="padding-left: 20px;"><strong>Validés :</strong></td><td>'.$obj_total->nb_valide.'</td>';
        print '</tr>';
        print '<tr>';
        print '<td><strong>CA validé :</strong></td><td>'.price($obj_total->total_ca).'</td>';
        print '<td style="padding-left: 20px;"><strong>Part collaborateurs :</strong></td><td>'.price($obj_total->total_collaborateur).'</td>';
        print '<td style="padding-left: 20px;"><strong>Part studio :</strong></td><td>'.price($obj_total->total_ca - $obj_total->total_collaborateur).'</td>';
        print '</tr>';
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

llxFooter();
$db->close();
?>
