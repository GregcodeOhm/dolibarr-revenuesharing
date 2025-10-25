<?php
// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';
require_once __DIR__.'/class/CacheManager.php';

// Load translation files
$langs->load("revenuesharing@revenuesharing");

// Initialiser repositories et cache
$cache = new CacheManager(null, 300, true);
$collaboratorRepo = new CollaboratorRepository($db);

// Security check modifiée - accepter les admins
if (!$user->id) {
    accessforbidden();
}

// Vérification alternative des permissions
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
$search_user = GETPOST('search_user', 'alpha');
$search_active = GETPOST('search_active', 'alpha');

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : (isset($conf->liste_limit) ? $conf->liste_limit : 25);
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOST('page', 'int');

if (empty($page) || $page == -1) {
    $page = 0;
}
$offset = $limit * $page;

if (!$sortfield) $sortfield = "c.label";
if (!$sortorder) $sortorder = "ASC";

llxHeader('', 'Liste des Collaborateurs', '');

print load_fiche_titre('Liste des Collaborateurs', '', 'generic');

// Actions
if ($action == 'delete' && $can_delete) {
    $id = GETPOST('id', 'int');
    if ($id > 0) {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE rowid = ".((int) $id);
        $resql = $db->query($sql);
        if ($resql) {
            setEventMessages("Collaborateur supprimé", null, 'mesgs');
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
print '<th class="liste_titre">Recherche</th>';
print '<th class="liste_titre">Statut</th>';
print '<th class="liste_titre">&nbsp;</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><input type="text" name="search_user" value="'.dol_escape_htmltag($search_user).'" placeholder="Nom du collaborateur" style="width: 100%;"></td>';
print '<td>';
print '<select name="search_active" style="width: 100%;">';
print '<option value="">Tous</option>';
print '<option value="1"'.($search_active == '1' ? ' selected' : '').'>Actif</option>';
print '<option value="0"'.($search_active == '0' ? ' selected' : '').'>Inactif</option>';
print '</select>';
print '</td>';
print '<td><input type="submit" class="button" value="Rechercher"></td>';
print '</tr>';

print '</table>';
print '</div>';
print '</form>';

// Bouton Nouveau - Supprimé car fonction disponible directement dans la liste

// Liste des collaborateurs (via repository)
try {
    $collaborators = $collaboratorRepo->findAllWithContractStats([
        'search' => $search_user,
        'active' => $search_active,
        'limit' => $limit,
        'offset' => $offset,
        'sortfield' => $sortfield,
        'sortorder' => $sortorder
    ]);

    $num = count($collaborators);

    print '<div class="div-table-responsive">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th> Collaborateur</th>';
    print '<th>Utilisateur</th>';
    print '<th class="center">% Défaut</th>';
    print '<th class="center"> Contrats</th>';
    print '<th class="center">CA Total</th>';
    print '<th class="center">Créé le</th>';
    print '<th class="center">Statut</th>';
    print '<th class="center">Actions</th>';
    print '</tr>';

    if ($num > 0) {
        $i = 0;
        foreach ($collaborators as $obj) {
            if ($i >= $limit) break;

            print '<tr class="oddeven">';

            // Collaborateur
            print '<td>';
            print '<a href="collaborator_card.php?id='.$obj->rowid.'" style="font-weight: bold;">';
            print ($obj->label ? dol_escape_htmltag($obj->label) : dol_escape_htmltag($obj->firstname.' '.$obj->lastname));
            print '</a>';
            print '</td>';

            // Utilisateur
            print '<td>'.dol_escape_htmltag($obj->firstname.' '.$obj->lastname).'</td>';

            // Pourcentage
            print '<td class="center"><span style="background: #e3f2fd; padding: 3px 8px; border-radius: 3px;">'.$obj->default_percentage.'%</span></td>';

            // Contrats
            print '<td class="center">'.((int)$obj->nb_contracts).'</td>';

            // Revenue
            print '<td class="center">'.price($obj->total_revenue).'</td>';

            // Date création
            print '<td class="center">';
            if ($obj->date_creation) {
                print dol_print_date($db->jdate($obj->date_creation), 'day');
            } else {
                print '-';
            }
            print '</td>';

            // Statut
            print '<td class="center">';
            if ($obj->active) {
                print '<span class="badge badge-status4 badge-status">Actif</span>';
            } else {
                print '<span class="badge badge-status8 badge-status">Inactif</span>';
            }
            print '</td>';

            // Actions
            print '<td class="center">';
            print '<a href="collaborator_card.php?id='.$obj->rowid.'" title="Voir" style="margin: 2px;">';
            print img_picto('', 'eye', 'class="pictofixedwidth"');
            print '</a>';

            if ($can_write) {
                print '<a href="collaborator_card.php?id='.$obj->rowid.'&action=edit" title="Modifier" style="margin: 2px;">';
                print img_picto('', 'edit', 'class="pictofixedwidth"');
                print '</a>';
            }

            if ($can_delete) {
                print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'" title="Supprimer" onclick="return confirm(\'Confirmer la suppression ?\')" style="margin: 2px;">';
                print img_picto('', 'delete', 'class="pictofixedwidth"');
                print '</a>';
            }
            print '</td>';

            print '</tr>';
            $i++;
        }
    } else {
        print '<tr><td colspan="8" class="center">';
        print '<div style="padding: 20px;">';
        print '<div style="font-size: 3em;">📭</div>';
        print '<h3>Aucun collaborateur trouvé</h3>';
        print '<p>Utilisez les filtres et actions ci-dessus pour gérer les collaborateurs.</p>';
        print '</div>';
        print '</td></tr>';
    }

    print '</table>';
    print '</div>';

    // Pagination
    if ($num > $limit) {
        print '<div class="center" style="margin: 20px 0;">';
        if ($page > 0) {
            print '<a href="'.$_SERVER["PHP_SELF"].'?page='.($page-1).($search_user ? '&search_user='.urlencode($search_user) : '').($search_active != '' ? '&search_active='.$search_active : '').'" class="button">← Précédent</a> ';
        }
        if ($num > $limit) {
            print '<a href="'.$_SERVER["PHP_SELF"].'?page='.($page+1).($search_user ? '&search_user='.urlencode($search_user) : '').($search_active != '' ? '&search_active='.$search_active : '').'" class="button">Suivant →</a>';
        }
        print '</div>';
    }

} catch (Exception $e) {
    print '<div style="background: #f8d7da; border: 1px solid #dc3545; color: #721c24; padding: 15px; margin: 20px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
}

print '<br>';
print '<div class="tabsAction">';
print '<a href="collaborator_card.php?action=create" class="butAction" style="background: #28a745; color: white;">'.img_picto('', 'add', 'class="pictofixedwidth"').' Nouveau Collaborateur</a>';
print '<a href="index.php" class="butAction">'.img_picto('', 'back', 'class="pictofixedwidth"').' Retour au Dashboard</a>';
print '<a href="admin/setup.php" class="butAction">'.img_picto('', 'setup', 'class="pictofixedwidth"').' Configuration</a>';
print '</div>';

llxFooter();
$db->close();
?>
