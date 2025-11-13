<?php
// Fichier: /htdocs/custom/revenuesharing/collaborator_card.php
// Fiche collaborateur - consultation et édition

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

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
$id = GETPOST('id', 'int');
$cancel = GETPOST('cancel', 'alpha');

// Variables pour le formulaire
$object = new stdClass();
$object->id = 0;
$object->fk_user = 0;
$object->label = '';
$object->default_percentage = 60;
$object->cost_per_session = 0;
$object->active = 1;
$object->note_private = '';
$object->note_public = '';
$object->date_creation = '';

$error = 0;
$errors = array();

// Actions
if ($action == 'add' && $can_write && !$cancel) {
    $object->fk_user = GETPOST('fk_user', 'int');
    $object->label = GETPOST('label', 'alpha');
    $object->default_percentage = GETPOST('default_percentage', 'int');
    $object->cost_per_session = GETPOST('cost_per_session', 'alpha');
    $object->active = GETPOST('active', 'int') ? 1 : 0;
    $object->note_private = GETPOST('note_private', 'none');
    $object->note_public = GETPOST('note_public', 'none');

    // Validation
    if (!$object->fk_user) {
        $errors[] = "Veuillez sélectionner un utilisateur";
        $error++;
    }
    if (empty($object->label)) {
        $errors[] = "Le libellé est obligatoire";
        $error++;
    }
    if ($object->default_percentage < 0 || $object->default_percentage > 100) {
        $errors[] = "Le pourcentage doit être entre 0 et 100";
        $error++;
    }

    if (!$error) {
        // Vérifier si l'utilisateur n'est pas déjà collaborateur
        $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE fk_user = ".((int) $object->fk_user);
        $resql_check = $db->query($sql_check);
        if ($resql_check && $db->num_rows($resql_check) > 0) {
            $errors[] = "Cet utilisateur est déjà collaborateur";
            $error++;
        }
    }

    if (!$error) {
        // Insertion
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_collaborator (";
        $sql .= "fk_user, label, default_percentage, cost_per_session, active, note_private, note_public, date_creation, fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= ((int) $object->fk_user).", ";
        $sql .= "'".$db->escape($object->label)."', ";
        $sql .= ((float) $object->default_percentage).", ";
        $sql .= ((float) $object->cost_per_session).", ";
        $sql .= ((int) $object->active).", ";
        $sql .= ($object->note_private ? "'".$db->escape($object->note_private)."'" : "NULL").", ";
        $sql .= ($object->note_public ? "'".$db->escape($object->note_public)."'" : "NULL").", ";
        $sql .= "NOW(), ";
        $sql .= ((int) $user->id);
        $sql .= ")";

        $resql = $db->query($sql);
        if ($resql) {
            $id = $db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_collaborator");
            setEventMessages("Collaborateur créé avec succès", null, 'mesgs');
            header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
            exit;
        } else {
            $errors[] = "Erreur lors de la création: ".$db->lasterror();
            $error++;
        }
    }

    if ($error) {
        setEventMessages("Erreur lors de la création", $errors, 'errors');
        $action = 'create';
    }
}

if ($action == 'update' && $can_write && $id > 0 && !$cancel) {
    $object->label = GETPOST('label', 'alpha');
    $object->default_percentage = GETPOST('default_percentage', 'int');
    $object->cost_per_session = GETPOST('cost_per_session', 'alpha');
    $object->active = GETPOST('active', 'int') ? 1 : 0;
    $object->note_private = GETPOST('note_private', 'none');
    $object->note_public = GETPOST('note_public', 'none');

    // Validation
    if (empty($object->label)) {
        $errors[] = "Le libellé est obligatoire";
        $error++;
    }
    if ($object->default_percentage < 0 || $object->default_percentage > 100) {
        $errors[] = "Le pourcentage doit être entre 0 et 100";
        $error++;
    }

    if (!$error) {
        // Mise à jour
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_collaborator SET ";
        $sql .= "label = '".$db->escape($object->label)."', ";
        $sql .= "default_percentage = ".((float) $object->default_percentage).", ";
        $sql .= "cost_per_session = ".((float) $object->cost_per_session).", ";
        $sql .= "active = ".((int) $object->active).", ";
        $sql .= "note_private = ".($object->note_private ? "'".$db->escape($object->note_private)."'" : "NULL").", ";
        $sql .= "note_public = ".($object->note_public ? "'".$db->escape($object->note_public)."'" : "NULL").", ";
        $sql .= "date_modification = NOW(), ";
        $sql .= "fk_user_modif = ".((int) $user->id)." ";
        $sql .= "WHERE rowid = ".((int) $id);

        $resql = $db->query($sql);
        if ($resql) {
            setEventMessages("Collaborateur modifié avec succès", null, 'mesgs');
            $action = '';
        } else {
            $errors[] = "Erreur lors de la modification: ".$db->lasterror();
            $error++;
            setEventMessages("Erreur lors de la modification", $errors, 'errors');
            $action = 'edit';
        }
    } else {
        setEventMessages("Erreur lors de la modification", $errors, 'errors');
        $action = 'edit';
    }
}

if ($cancel) {
    if ($id > 0) {
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
    } else {
        header("Location: collaborator_list.php");
    }
    exit;
}

// Chargement de l'objet si ID fourni
if ($id > 0 && $action != 'create') {
    $sql = "SELECT c.*, u.firstname, u.lastname, u.email";
    $sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
    $sql .= " WHERE c.rowid = ".((int) $id);

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $object->id = $obj->rowid;
        $object->fk_user = $obj->fk_user;
        $object->label = $obj->label;
        $object->default_percentage = $obj->default_percentage;
        $object->cost_per_session = $obj->cost_per_session;
        $object->active = $obj->active;
        $object->note_private = $obj->note_private;
        $object->note_public = $obj->note_public;
        $object->date_creation = $obj->date_creation;
        $object->user_firstname = $obj->firstname;
        $object->user_lastname = $obj->lastname;
        $object->user_email = $obj->email;
    } else {
        setEventMessages("Collaborateur non trouvé", null, 'errors');
        header("Location: collaborator_list.php");
        exit;
    }
}

llxHeader('', 'Fiche Collaborateur', '');

// Titre selon l'action
if ($action == 'create') {
    print load_fiche_titre(' Nouveau Collaborateur', '', 'generic');
} elseif ($action == 'edit') {
    print load_fiche_titre(' Modifier Collaborateur : '.$object->label, '', 'generic');
} else {
    print load_fiche_titre(' Collaborateur : '.$object->label, '', 'generic');
}

// Mode création
if ($action == 'create') {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';

    print '<table class="border centpercent tableforfieldcreate">';

    // Utilisateur
    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Utilisateur</td>';
    print '<td>';

    // Liste des utilisateurs
    print '<select name="fk_user" class="flat minwidth200" required>';
    print '<option value="">-- Sélectionner un utilisateur --</option>';

    $sql_users = "SELECT u.rowid, u.login, u.firstname, u.lastname";
    $sql_users .= " FROM ".MAIN_DB_PREFIX."user u";
    $sql_users .= " WHERE u.entity = ".$conf->entity;
    $sql_users .= " AND u.statut = 1";
    $sql_users .= " ORDER BY u.lastname, u.firstname";

    $resql_users = $db->query($sql_users);
    if ($resql_users) {
        while ($obj_user = $db->fetch_object($resql_users)) {
            $selected = ($obj_user->rowid == $object->fk_user) ? ' selected' : '';
            print '<option value="'.$obj_user->rowid.'"'.$selected.'>';
            print $obj_user->firstname.' '.$obj_user->lastname.' ('.$obj_user->login.')';
            print '</option>';
        }
    }

    print '</select>';
    print '</td>';
    print '</tr>';

    // Label
    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Libellé</td>';
    print '<td><input type="text" name="label" value="'.dol_escape_htmltag($object->label).'" size="50" maxlength="255" required></td>';
    print '</tr>';

    // Pourcentage par défaut
    print '<tr>';
    print '<td class="titlefieldcreate">Pourcentage par défaut (%)</td>';
    print '<td><input type="number" name="default_percentage" value="'.$object->default_percentage.'" min="0" max="100" step="0.01"> %</td>';
    print '</tr>';

    // Coût par séance
    print '<tr>';
    print '<td class="titlefieldcreate">Coût par séance</td>';
    print '<td><input type="number" name="cost_per_session" value="'.$object->cost_per_session.'" min="0" step="0.01"> €</td>';
    print '</tr>';

    // Actif
    print '<tr>';
    print '<td class="titlefieldcreate">Actif</td>';
    print '<td><input type="checkbox" name="active" value="1"'.($object->active ? ' checked' : '').'></td>';
    print '</tr>';

    // Notes
    print '<tr>';
    print '<td class="titlefieldcreate">Note privée</td>';
    print '<td><textarea name="note_private" rows="3" cols="50">'.dol_escape_htmltag($object->note_private).'</textarea></td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Note publique</td>';
    print '<td><textarea name="note_public" rows="3" cols="50">'.dol_escape_htmltag($object->note_public).'</textarea></td>';
    print '</tr>';

    print '</table>';

    print '<div class="tabsAction">';
    print '<input type="submit" class="button" value="Créer">';
    print ' <input type="submit" class="button button-cancel" name="cancel" value="Annuler">';
    print '</div>';

    print '</form>';
}
// Mode édition
elseif ($action == 'edit') {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';

    print '<table class="border centpercent tableforfieldedit">';

    // Utilisateur (non modifiable)
    print '<tr>';
    print '<td class="titlefield">Utilisateur</td>';
    print '<td>';
    if ($object->user_firstname || $object->user_lastname) {
        print $object->user_firstname.' '.$object->user_lastname;
    } else {
        print '<em>Utilisateur non trouvé (ID: '.$object->fk_user.')</em>';
    }
    print '</td>';
    print '</tr>';

    // Label
    print '<tr>';
    print '<td class="fieldrequired">Libellé</td>';
    print '<td><input type="text" name="label" value="'.dol_escape_htmltag($object->label).'" size="50" maxlength="255" required></td>';
    print '</tr>';

    // Pourcentage par défaut
    print '<tr>';
    print '<td>Pourcentage par défaut (%)</td>';
    print '<td><input type="number" name="default_percentage" value="'.$object->default_percentage.'" min="0" max="100" step="0.01"> %</td>';
    print '</tr>';

    // Coût par séance
    print '<tr>';
    print '<td>Coût par séance</td>';
    print '<td><input type="number" name="cost_per_session" value="'.$object->cost_per_session.'" min="0" step="0.01"> €</td>';
    print '</tr>';

    // Actif
    print '<tr>';
    print '<td>Actif</td>';
    print '<td><input type="checkbox" name="active" value="1"'.($object->active ? ' checked' : '').'></td>';
    print '</tr>';

    // Notes
    print '<tr>';
    print '<td>Note privée</td>';
    print '<td><textarea name="note_private" rows="3" cols="50">'.dol_escape_htmltag($object->note_private).'</textarea></td>';
    print '</tr>';

    print '<tr>';
    print '<td>Note publique</td>';
    print '<td><textarea name="note_public" rows="3" cols="50">'.dol_escape_htmltag($object->note_public).'</textarea></td>';
    print '</tr>';

    print '</table>';

    print '<div class="tabsAction">';
    print '<input type="submit" class="button" value="Modifier">';
    print ' <input type="submit" class="button button-cancel" name="cancel" value="Annuler">';
    print '</div>';

    print '</form>';
}
// Mode visualisation
else {
    if ($object->id > 0) {
        // Actions
        print '<div class="tabsAction">';
        if ($can_write) {
            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit"> Modifier</a>';
        }
        if ($can_delete) {
            print '<a class="butActionDelete" href="collaborator_list.php?action=delete&id='.$object->id.'" onclick="return confirm(\'Confirmer la suppression ?\')"> Supprimer</a>';
        }
        print '<a class="butAction" href="collaborator_list.php">Retour à la liste</a>';
        print '</div>';

        print '<table class="border centpercent tableforfield">';

        // ID
        print '<tr>';
        print '<td class="titlefield">ID</td>';
        print '<td>'.$object->id.'</td>';
        print '</tr>';

        // Label
        print '<tr>';
        print '<td>Libellé</td>';
        print '<td><strong>'.dol_escape_htmltag($object->label).'</strong></td>';
        print '</tr>';

        // Utilisateur
        print '<tr>';
        print '<td>Utilisateur lié</td>';
        print '<td>';
        if ($object->user_firstname || $object->user_lastname) {
            print $object->user_firstname.' '.$object->user_lastname;
            if ($object->user_email) {
                print ' ('.$object->user_email.')';
            }
        } else {
            print '<em>Utilisateur non trouvé (ID: '.$object->fk_user.')</em>';
        }
        print '</td>';
        print '</tr>';

        // Pourcentage par défaut
        print '<tr>';
        print '<td>Pourcentage par défaut</td>';
        print '<td><span style="background: #e3f2fd; padding: 5px 10px; border-radius: 3px; font-weight: bold;">'.$object->default_percentage.'%</span></td>';
        print '</tr>';

        // Coût par séance
        print '<tr>';
        print '<td>Coût par séance</td>';
        print '<td>'.price($object->cost_per_session).'</td>';
        print '</tr>';

        // Statut
        print '<tr>';
        print '<td>Statut</td>';
        print '<td>';
        if ($object->active) {
            print '<span class="badge badge-success badge-status">Actif</span>';
        } else {
            print '<span class="badge badge-danger badge-status">Inactif</span>';
        }
        print '</td>';
        print '</tr>';

        // Date création
        print '<tr>';
        print '<td>Date de création</td>';
        print '<td>';
        if ($object->date_creation) {
            print dol_print_date(strtotime($object->date_creation), 'dayhour');
        } else {
            print '-';
        }
        print '</td>';
        print '</tr>';

        // Notes
        if ($object->note_public) {
            print '<tr>';
            print '<td>Note publique</td>';
            print '<td>'.nl2br(dol_escape_htmltag($object->note_public)).'</td>';
            print '</tr>';
        }

        if ($object->note_private) {
            print '<tr>';
            print '<td>Note privée</td>';
            print '<td>'.nl2br(dol_escape_htmltag($object->note_private)).'</td>';
            print '</tr>';
        }

        print '</table>';

        // Statistiques du collaborateur
        print '<br>';
        print '<h3>Statistiques</h3>';

        // Récupérer les stats
        $sql_stats = "SELECT COUNT(*) as nb_contracts,
                             COALESCE(SUM(amount_ht), 0) as total_ht,
                             COALESCE(SUM(collaborator_amount_ht), 0) as total_collaborator_brut,
                             COALESCE(SUM(net_collaborator_amount), 0) as total_collaborator_net,
                             COALESCE(SUM(total_costs), 0) as total_costs";
        $sql_stats .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract";
        $sql_stats .= " WHERE fk_collaborator = ".((int) $object->id);
        $sql_stats .= " AND status >= 1";

        $resql_stats = $db->query($sql_stats);
        $stats = null;
        if ($resql_stats) {
            $stats = $db->fetch_object($resql_stats);
        }

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>Contrats validés</th>';
        print '<th>CA total généré</th>';
        print '<th>Part brute</th>';
        print '<th>Coûts déduits</th>';
        print '<th>Part nette</th>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td class="center">'.($stats ? $stats->nb_contracts : 0).'</td>';
        print '<td class="center">'.($stats ? price($stats->total_ht) : price(0)).'</td>';
        print '<td class="center">'.($stats ? price($stats->total_collaborator_brut) : price(0)).'</td>';
        print '<td class="center">'.($stats ? price($stats->total_costs) : price(0)).'</td>';
        print '<td class="center"><strong>'.($stats ? price($stats->total_collaborator_net) : price(0)).'</strong></td>';
        print '</tr>';

        print '</table>';

        // Liste des contrats récents
        if ($stats && $stats->nb_contracts > 0) {
            print '<br>';
            print '<h3> Contrats récents</h3>';

            $sql_contracts = "SELECT rowid, ref, label, amount_ht, net_collaborator_amount, status, date_creation";
            $sql_contracts .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract";
            $sql_contracts .= " WHERE fk_collaborator = ".((int) $object->id);
            $sql_contracts .= " ORDER BY date_creation DESC";
            $sql_contracts .= " LIMIT 5";

            $resql_contracts = $db->query($sql_contracts);
            if ($resql_contracts) {
                print '<table class="noborder centpercent">';
                print '<tr class="liste_titre">';
                print '<th>Référence</th>';
                print '<th>Libellé</th>';
                print '<th class="center">Montant HT</th>';
                print '<th class="center">Part nette</th>';
                print '<th class="center">Statut</th>';
                print '<th class="center">Date</th>';
                print '</tr>';

                while ($obj_contract = $db->fetch_object($resql_contracts)) {
                    print '<tr class="oddeven">';
                    print '<td><a href="contract_card.php?id='.$obj_contract->rowid.'">'.$obj_contract->ref.'</a></td>';
                    print '<td>'.dol_trunc($obj_contract->label, 40).'</td>';
                    print '<td class="center">'.price($obj_contract->amount_ht).'</td>';
                    print '<td class="center">'.price($obj_contract->net_collaborator_amount).'</td>';
                    print '<td class="center">';
                    if ($obj_contract->status == 0) {
                        print '<span class="badge badge-info badge-status">Brouillon</span>';
                    } else {
                        print '<span class="badge badge-success badge-status">Validé</span>';
                    }
                    print '</td>';
                    print '<td class="center">'.dol_print_date($db->jdate($obj_contract->date_creation), 'day').'</td>';
                    print '</tr>';
                }

                print '</table>';

                if ($stats->nb_contracts > 5) {
                    print '<div class="center" style="margin: 10px 0;">';
                    print '<a href="contract_list.php?search_collaborator='.$object->id.'" class="button">Voir tous les contrats</a>';
                    print '</div>';
                }
            }
        }

    } else {
        setEventMessages("Aucun collaborateur sélectionné", null, 'errors');
    }
}

llxFooter();
$db->close();
?>
