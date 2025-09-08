<?php
// Fichier: /htdocs/custom/revenuesharing/contract_card.php
// Fiche contrat de partage de revenus - consultation et édition

$dolibarr_main_document_root = '/home/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';
require_once $dolibarr_main_document_root.'/core/lib/date.lib.php';

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
$object->ref = '';
$object->fk_collaborator = 0;
$object->fk_project = 0;
$object->fk_facture = 0;
$object->label = '';
$object->amount_ht = 0;
$object->amount_ttc = 0;
$object->collaborator_percentage = 60;
$object->collaborator_amount_ht = 0;
$object->studio_amount_ht = 0;
$object->nb_sessions = 0;
$object->cost_per_session = 0;
$object->total_costs = 0;
$object->net_collaborator_amount = 0;
$object->status = 0;
$object->note_private = '';
$object->note_public = '';

$error = 0;
$errors = array();

// Fonction pour calculer les montants
function calculateAmounts($obj) {
    if ($obj->amount_ht > 0 && $obj->collaborator_percentage > 0) {
        $obj->collaborator_amount_ht = ($obj->amount_ht * $obj->collaborator_percentage) / 100;
        $obj->studio_amount_ht = $obj->amount_ht - $obj->collaborator_amount_ht;
        $obj->total_costs = $obj->nb_sessions * $obj->cost_per_session;
        $obj->net_collaborator_amount = $obj->collaborator_amount_ht - $obj->total_costs;
    }
    return $obj;
}

// Fonction pour générer une référence
function getNextRef($db) {
    $year = date('Y');
    $prefix = 'RC'.$year.'-';

    $sql = "SELECT MAX(CAST(SUBSTRING(ref, LENGTH('".$prefix."')+1) AS UNSIGNED)) as max_num";
    $sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract";
    $sql .= " WHERE ref LIKE '".$prefix."%'";

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $num = $obj->max_num ? $obj->max_num + 1 : 1;
        return $prefix.sprintf('%04d', $num);
    }

    return $prefix.'0001';
}

// Actions
if ($action == 'add' && $can_write && !$cancel) {
    $object->fk_collaborator = GETPOST('fk_collaborator', 'int');
    $object->fk_project = GETPOST('fk_project', 'int');
    $object->fk_facture = GETPOST('fk_facture', 'int');
    $object->label = GETPOST('label', 'alpha');
    $object->amount_ht = GETPOST('amount_ht', 'alpha');
    $object->amount_ttc = GETPOST('amount_ttc', 'alpha');
    $object->collaborator_percentage = GETPOST('collaborator_percentage', 'alpha');
    $object->nb_sessions = GETPOST('nb_sessions', 'int');
    $object->cost_per_session = GETPOST('cost_per_session', 'alpha');
    $object->note_private = GETPOST('note_private', 'none');
    $object->note_public = GETPOST('note_public', 'none');

    // Validation
    if (!$object->fk_collaborator) {
        $errors[] = "Veuillez sélectionner un collaborateur";
        $error++;
    }
    if (empty($object->label)) {
        $errors[] = "Le libellé est obligatoire";
        $error++;
    }
    if ($object->amount_ht <= 0) {
        $errors[] = "Le montant HT doit être supérieur à 0";
        $error++;
    }
    if ($object->collaborator_percentage < 0 || $object->collaborator_percentage > 100) {
        $errors[] = "Le pourcentage doit être entre 0 et 100";
        $error++;
    }

    if (!$error) {
        // Calcul des montants
        $object = calculateAmounts($object);

        // Génération de la référence
        $object->ref = getNextRef($db);

        // Insertion
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_contract (";
        $sql .= "ref, fk_collaborator, fk_project, fk_facture, label, amount_ht, amount_ttc, ";
        $sql .= "collaborator_percentage, collaborator_amount_ht, studio_amount_ht, ";
        $sql .= "nb_sessions, cost_per_session, total_costs, net_collaborator_amount, ";
        $sql .= "status, note_private, note_public, date_creation, fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= "'".$db->escape($object->ref)."', ";
        $sql .= ((int) $object->fk_collaborator).", ";
        $sql .= ($object->fk_project ? ((int) $object->fk_project) : "NULL").", ";
        $sql .= ($object->fk_facture ? ((int) $object->fk_facture) : "NULL").", ";
        $sql .= "'".$db->escape($object->label)."', ";
        $sql .= ((float) $object->amount_ht).", ";
        $sql .= ((float) $object->amount_ttc).", ";
        $sql .= ((float) $object->collaborator_percentage).", ";
        $sql .= ((float) $object->collaborator_amount_ht).", ";
        $sql .= ((float) $object->studio_amount_ht).", ";
        $sql .= ((int) $object->nb_sessions).", ";
        $sql .= ((float) $object->cost_per_session).", ";
        $sql .= ((float) $object->total_costs).", ";
        $sql .= ((float) $object->net_collaborator_amount).", ";
        $sql .= "0, ";
        $sql .= ($object->note_private ? "'".$db->escape($object->note_private)."'" : "NULL").", ";
        $sql .= ($object->note_public ? "'".$db->escape($object->note_public)."'" : "NULL").", ";
        $sql .= "NOW(), ";
        $sql .= ((int) $user->id);
        $sql .= ")";

        $resql = $db->query($sql);
        if ($resql) {
            $id = $db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_contract");
            setEventMessages("Contrat créé avec succès", null, 'mesgs');
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
    $object->amount_ht = GETPOST('amount_ht', 'alpha');
    $object->amount_ttc = GETPOST('amount_ttc', 'alpha');
    $object->collaborator_percentage = GETPOST('collaborator_percentage', 'alpha');
    $object->nb_sessions = GETPOST('nb_sessions', 'int');
    $object->cost_per_session = GETPOST('cost_per_session', 'alpha');
    $object->note_private = GETPOST('note_private', 'none');
    $object->note_public = GETPOST('note_public', 'none');

    // Validation
    if (empty($object->label)) {
        $errors[] = "Le libellé est obligatoire";
        $error++;
    }
    if ($object->amount_ht <= 0) {
        $errors[] = "Le montant HT doit être supérieur à 0";
        $error++;
    }
    if ($object->collaborator_percentage < 0 || $object->collaborator_percentage > 100) {
        $errors[] = "Le pourcentage doit être entre 0 et 100";
        $error++;
    }

    if (!$error) {
        // Recalcul des montants
        $object = calculateAmounts($object);

        // Mise à jour
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET ";
        $sql .= "label = '".$db->escape($object->label)."', ";
        $sql .= "amount_ht = ".((float) $object->amount_ht).", ";
        $sql .= "amount_ttc = ".((float) $object->amount_ttc).", ";
        $sql .= "collaborator_percentage = ".((float) $object->collaborator_percentage).", ";
        $sql .= "collaborator_amount_ht = ".((float) $object->collaborator_amount_ht).", ";
        $sql .= "studio_amount_ht = ".((float) $object->studio_amount_ht).", ";
        $sql .= "nb_sessions = ".((int) $object->nb_sessions).", ";
        $sql .= "cost_per_session = ".((float) $object->cost_per_session).", ";
        $sql .= "total_costs = ".((float) $object->total_costs).", ";
        $sql .= "net_collaborator_amount = ".((float) $object->net_collaborator_amount).", ";
        $sql .= "note_private = ".($object->note_private ? "'".$db->escape($object->note_private)."'" : "NULL").", ";
        $sql .= "note_public = ".($object->note_public ? "'".$db->escape($object->note_public)."'" : "NULL").", ";
        $sql .= "date_modification = NOW(), ";
        $sql .= "fk_user_modif = ".((int) $user->id)." ";
        $sql .= "WHERE rowid = ".((int) $id);

        $resql = $db->query($sql);
        if ($resql) {
            setEventMessages("Contrat modifié avec succès", null, 'mesgs');
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

if ($action == 'validate' && $can_write && $id > 0) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET ";
    $sql .= "status = 1, ";
    $sql .= "date_valid = NOW(), ";
    $sql .= "fk_user_valid = ".((int) $user->id)." ";
    $sql .= "WHERE rowid = ".((int) $id);

    $resql = $db->query($sql);
    if ($resql) {
        setEventMessages("Contrat validé avec succès", null, 'mesgs');
    } else {
        setEventMessages("Erreur lors de la validation: ".$db->lasterror(), null, 'errors');
    }
}

if ($cancel) {
    if ($id > 0) {
        header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
    } else {
        header("Location: contract_list.php");
    }
    exit;
}

// Chargement de l'objet si ID fourni
if ($id > 0 && $action != 'create') {
    $sql = "SELECT rc.*, c.label as collaborator_label, u.firstname, u.lastname,";
    $sql .= " p.ref as project_ref, p.title as project_title,";
    $sql .= " f.ref as facture_ref";
    $sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract rc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = rc.fk_collaborator";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = rc.fk_project";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = rc.fk_facture";
    $sql .= " WHERE rc.rowid = ".((int) $id);

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $object->id = $obj->rowid;
        $object->ref = $obj->ref;
        $object->fk_collaborator = $obj->fk_collaborator;
        $object->fk_project = $obj->fk_project;
        $object->fk_facture = $obj->fk_facture;
        $object->label = $obj->label;
        $object->amount_ht = $obj->amount_ht;
        $object->amount_ttc = $obj->amount_ttc;
        $object->collaborator_percentage = $obj->collaborator_percentage;
        $object->collaborator_amount_ht = $obj->collaborator_amount_ht;
        $object->studio_amount_ht = $obj->studio_amount_ht;
        $object->nb_sessions = $obj->nb_sessions;
        $object->cost_per_session = $obj->cost_per_session;
        $object->total_costs = $obj->total_costs;
        $object->net_collaborator_amount = $obj->net_collaborator_amount;
        $object->status = $obj->status;
        $object->note_private = $obj->note_private;
        $object->note_public = $obj->note_public;
        $object->date_creation = $obj->date_creation;
        $object->collaborator_label = $obj->collaborator_label;
        $object->project_ref = $obj->project_ref;
        $object->project_title = $obj->project_title;
        $object->facture_ref = $obj->facture_ref;
    } else {
        setEventMessages("Contrat non trouvé", null, 'errors');
        header("Location: contract_list.php");
        exit;
    }
}

llxHeader('', 'Fiche Contrat', '');

// Titre selon l'action
if ($action == 'create') {
    print load_fiche_titre('➕ Nouveau Contrat de Partage', '', 'generic');
} elseif ($action == 'edit') {
    print load_fiche_titre('✏️ Modifier Contrat : '.$object->ref, '', 'generic');
} else {
    print load_fiche_titre('📄 Contrat : '.$object->ref, '', 'generic');
}

// Script JavaScript pour les calculs automatiques
print '<script type="text/javascript">
function calculateAmounts() {
    var amount_ht = parseFloat(document.getElementById("amount_ht").value) || 0;
    var percentage = parseFloat(document.getElementById("collaborator_percentage").value) || 0;
    var nb_sessions = parseInt(document.getElementById("nb_sessions").value) || 0;
    var cost_per_session = parseFloat(document.getElementById("cost_per_session").value) || 0;

    var collaborator_amount = (amount_ht * percentage) / 100;
    var studio_amount = amount_ht - collaborator_amount;
    var total_costs = nb_sessions * cost_per_session;
    var net_amount = collaborator_amount - total_costs;

    document.getElementById("collaborator_amount_display").innerHTML = collaborator_amount.toFixed(2) + " €";
    document.getElementById("studio_amount_display").innerHTML = studio_amount.toFixed(2) + " €";
    document.getElementById("total_costs_display").innerHTML = total_costs.toFixed(2) + " €";
    document.getElementById("net_amount_display").innerHTML = net_amount.toFixed(2) + " €";
}
</script>';

// Mode création
if ($action == 'create') {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';

    print '<table class="border centpercent tableforfieldcreate">';

    // Collaborateur
    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Collaborateur</td>';
    print '<td>';
    print '<select name="fk_collaborator" class="flat minwidth200" required>';
    print '<option value="">-- Sélectionner un collaborateur --</option>';

    $sql_collab = "SELECT c.rowid, c.label, u.firstname, u.lastname, c.default_percentage";
    $sql_collab .= " FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
    $sql_collab .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
    $sql_collab .= " WHERE c.active = 1";
    $sql_collab .= " ORDER BY c.label";

    $resql_collab = $db->query($sql_collab);
    if ($resql_collab) {
        while ($obj_collab = $db->fetch_object($resql_collab)) {
            $selected = ($obj_collab->rowid == $object->fk_collaborator) ? ' selected' : '';
            print '<option value="'.$obj_collab->rowid.'" data-percentage="'.$obj_collab->default_percentage.'"'.$selected.'>';
            print ($obj_collab->label ? $obj_collab->label : $obj_collab->firstname.' '.$obj_collab->lastname);
            print ' ('.$obj_collab->default_percentage.'%)';
            print '</option>';
        }
    }

    print '</select>';
    print '</td>';
    print '</tr>';

    // Libellé
    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Libellé</td>';
    print '<td><input type="text" name="label" value="'.dol_escape_htmltag($object->label).'" size="50" maxlength="255" required></td>';
    print '</tr>';

    // Montant HT
    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Montant HT</td>';
    print '<td><input type="number" id="amount_ht" name="amount_ht" value="'.$object->amount_ht.'" step="0.01" min="0" required onchange="calculateAmounts()"> €</td>';
    print '</tr>';

    // Montant TTC
    print '<tr>';
    print '<td class="titlefieldcreate">Montant TTC</td>';
    print '<td><input type="number" name="amount_ttc" value="'.$object->amount_ttc.'" step="0.01" min="0"> €</td>';
    print '</tr>';

    // Pourcentage collaborateur
    print '<tr>';
    print '<td class="titlefieldcreate">Pourcentage collaborateur</td>';
    print '<td><input type="number" id="collaborator_percentage" name="collaborator_percentage" value="'.$object->collaborator_percentage.'" step="0.01" min="0" max="100" onchange="calculateAmounts()"> %</td>';
    print '</tr>';

    // Nombre de séances
    print '<tr>';
    print '<td class="titlefieldcreate">Nombre de séances</td>';
    print '<td><input type="number" id="nb_sessions" name="nb_sessions" value="'.$object->nb_sessions.'" min="0" onchange="calculateAmounts()"></td>';
    print '</tr>';

    // Coût par séance
    print '<tr>';
    print '<td class="titlefieldcreate">Coût par séance</td>';
    print '<td><input type="number" id="cost_per_session" name="cost_per_session" value="'.$object->cost_per_session.'" step="0.01" min="0" onchange="calculateAmounts()"> €</td>';
    print '</tr>';

    // Calculs automatiques (affichage)
    print '<tr class="liste_titre">';
    print '<td colspan="2">Calculs automatiques</td>';
    print '</tr>';

    print '<tr>';
    print '<td>Part collaborateur (brute)</td>';
    print '<td><span id="collaborator_amount_display" style="font-weight: bold; color: #007cba;">0.00 €</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td>Part studio</td>';
    print '<td><span id="studio_amount_display" style="font-weight: bold; color: #28a745;">0.00 €</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td>Coûts totaux</td>';
    print '<td><span id="total_costs_display" style="font-weight: bold; color: #dc3545;">0.00 €</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td>Part collaborateur (nette)</td>';
    print '<td><span id="net_amount_display" style="font-weight: bold; color: #ffc107; font-size: 1.2em;">0.00 €</span></td>';
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

    // Script pour remplissage automatique du pourcentage
    print '<script>
    document.querySelector(\'select[name="fk_collaborator"]\').addEventListener("change", function() {
        var selectedOption = this.options[this.selectedIndex];
        if (selectedOption.dataset.percentage) {
            document.getElementById("collaborator_percentage").value = selectedOption.dataset.percentage;
            calculateAmounts();
        }
    });
    </script>';
}
// Mode édition
elseif ($action == 'edit') {
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';

    print '<table class="border centpercent tableforfieldedit">';

    // Référence (non modifiable)
    print '<tr>';
    print '<td class="titlefield">Référence</td>';
    print '<td><strong>'.$object->ref.'</strong></td>';
    print '</tr>';

    // Collaborateur (non modifiable)
    print '<tr>';
    print '<td>Collaborateur</td>';
    print '<td>'.$object->collaborator_label.'</td>';
    print '</tr>';

    // Libellé
    print '<tr>';
    print '<td class="fieldrequired">Libellé</td>';
    print '<td><input type="text" name="label" value="'.dol_escape_htmltag($object->label).'" size="50" maxlength="255" required></td>';
    print '</tr>';

    // Montant HT
    print '<tr>';
    print '<td class="fieldrequired">Montant HT</td>';
    print '<td><input type="number" id="amount_ht" name="amount_ht" value="'.$object->amount_ht.'" step="0.01" min="0" required onchange="calculateAmounts()"> €</td>';
    print '</tr>';

    // Montant TTC
    print '<tr>';
    print '<td>Montant TTC</td>';
    print '<td><input type="number" name="amount_ttc" value="'.$object->amount_ttc.'" step="0.01" min="0"> €</td>';
    print '</tr>';

    // Pourcentage collaborateur
    print '<tr>';
    print '<td>Pourcentage collaborateur</td>';
    print '<td><input type="number" id="collaborator_percentage" name="collaborator_percentage" value="'.$object->collaborator_percentage.'" step="0.01" min="0" max="100" onchange="calculateAmounts()"> %</td>';
    print '</tr>';

    // Nombre de séances
    print '<tr>';
    print '<td>Nombre de séances</td>';
    print '<td><input type="number" id="nb_sessions" name="nb_sessions" value="'.$object->nb_sessions.'" min="0" onchange="calculateAmounts()"></td>';
    print '</tr>';

    // Coût par séance
    print '<tr>';
    print '<td>Coût par séance</td>';
    print '<td><input type="number" id="cost_per_session" name="cost_per_session" value="'.$object->cost_per_session.'" step="0.01" min="0" onchange="calculateAmounts()"> €</td>';
    print '</tr>';

    // Calculs automatiques (affichage)
    print '<tr class="liste_titre">';
    print '<td colspan="2">Calculs automatiques</td>';
    print '</tr>';

    print '<tr>';
    print '<td>Part collaborateur (brute)</td>';
    print '<td><span id="collaborator_amount_display" style="font-weight: bold; color: #007cba;">'.price($object->collaborator_amount_ht).'</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td>Part studio</td>';
    print '<td><span id="studio_amount_display" style="font-weight: bold; color: #28a745;">'.price($object->studio_amount_ht).'</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td>Coûts totaux</td>';
    print '<td><span id="total_costs_display" style="font-weight: bold; color: #dc3545;">'.price($object->total_costs).'</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td>Part collaborateur (nette)</td>';
    print '<td><span id="net_amount_display" style="font-weight: bold; color: #ffc107; font-size: 1.2em;">'.price($object->net_collaborator_amount).'</span></td>';
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

    // Initialiser les calculs
    print '<script>calculateAmounts();</script>';
}
// Mode visualisation
else {
    if ($object->id > 0) {
        // Actions
        print '<div class="tabsAction">';
        if ($can_write && $object->status == 0) {
            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit">✏️ Modifier</a>';
            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=validate" onclick="return confirm(\'Confirmer la validation ?\')">✅ Valider</a>';
        }
        if ($can_delete && $object->status == 0) {
            print '<a class="butActionDelete" href="contract_list.php?action=delete&id='.$object->id.'" onclick="return confirm(\'Confirmer la suppression ?\')">🗑️ Supprimer</a>';
        }
        print '<a class="butAction" href="contract_list.php">📋 Retour à la liste</a>';
        print '</div>';

        print '<table class="border centpercent tableforfield">';

        // Référence
        print '<tr>';
        print '<td class="titlefield">Référence</td>';
        print '<td><strong>'.dol_escape_htmltag($object->ref).'</strong></td>';
        print '</tr>';

        // Statut
        print '<tr>';
        print '<td>Statut</td>';
        print '<td>';
        if ($object->status == 0) {
            print '<span class="badge badge-status1 badge-status">📝 Brouillon</span>';
        } elseif ($object->status == 1) {
            print '<span class="badge badge-status4 badge-status">✅ Validé</span>';
        }
        print '</td>';
        print '</tr>';

        // Collaborateur
        print '<tr>';
        print '<td>Collaborateur</td>';
        print '<td>';
        print '<a href="collaborator_card.php?id='.$object->fk_collaborator.'">';
        print '<strong>'.dol_escape_htmltag($object->collaborator_label).'</strong>';
        print '</a>';
        print '</td>';
        print '</tr>';

        // Libellé
        print '<tr>';
        print '<td>Libellé</td>';
        print '<td>'.dol_escape_htmltag($object->label).'</td>';
        print '</tr>';

        // Projet et facture liés
        if ($object->project_ref || $object->facture_ref) {
            print '<tr>';
            print '<td>Éléments liés</td>';
            print '<td>';
            if ($object->project_ref) {
                print '📁 Projet : <strong>'.$object->project_ref.'</strong>';
                if ($object->project_title) {
                    print ' - '.$object->project_title;
                }
                print '<br>';
            }
            if ($object->facture_ref) {
                print '🧾 Facture : <strong>'.$object->facture_ref.'</strong>';
            }
            print '</td>';
            print '</tr>';
        }

        // Montants
        print '<tr class="liste_titre">';
        print '<td colspan="2">💰 Montants et Répartition</td>';
        print '</tr>';

        print '<tr>';
        print '<td>Montant HT</td>';
        print '<td><span style="font-size: 1.2em; font-weight: bold;">'.price($object->amount_ht).'</span></td>';
        print '</tr>';

        if ($object->amount_ttc > 0) {
            print '<tr>';
            print '<td>Montant TTC</td>';
            print '<td>'.price($object->amount_ttc).'</td>';
            print '</tr>';
        }

        print '<tr>';
        print '<td>Pourcentage collaborateur</td>';
        print '<td><span style="background: #e3f2fd; padding: 5px 10px; border-radius: 3px; font-weight: bold;">'.$object->collaborator_percentage.'%</span></td>';
        print '</tr>';

        print '<tr>';
        print '<td>Part collaborateur (brute)</td>';
        print '<td><span style="color: #007cba; font-weight: bold; font-size: 1.1em;">'.price($object->collaborator_amount_ht).'</span></td>';
        print '</tr>';

        print '<tr>';
        print '<td>Part studio</td>';
        print '<td><span style="color: #28a745; font-weight: bold;">'.price($object->studio_amount_ht).'</span></td>';
        print '</tr>';

        // Coûts et montant net
        if ($object->nb_sessions > 0 || $object->total_costs > 0) {
            print '<tr class="liste_titre">';
            print '<td colspan="2">💸 Coûts et Déductions</td>';
            print '</tr>';

            print '<tr>';
            print '<td>Nombre de séances</td>';
            print '<td>'.$object->nb_sessions.'</td>';
            print '</tr>';

            print '<tr>';
            print '<td>Coût par séance</td>';
            print '<td>'.price($object->cost_per_session).'</td>';
            print '</tr>';

            print '<tr>';
            print '<td>Coûts totaux</td>';
            print '<td><span style="color: #dc3545; font-weight: bold;">'.price($object->total_costs).'</span></td>';
            print '</tr>';
        }

        print '<tr>';
        print '<td><strong>Part collaborateur NETTE</strong></td>';
        print '<td>';
        print '<span style="background: #fff3cd; color: #856404; padding: 8px 15px; border-radius: 5px; font-weight: bold; font-size: 1.3em;">';
        print price($object->net_collaborator_amount);
        print '</span>';
        print '</td>';
        print '</tr>';

        // Dates
        print '<tr class="liste_titre">';
        print '<td colspan="2">📅 Informations de Suivi</td>';
        print '</tr>';

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

        // Résumé visuel
        print '<br>';
        print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 20px;">';
        print '<h3 style="margin: 0 0 15px 0;">📊 Résumé de la Répartition</h3>';

        print '<div style="display: flex; justify-content: space-around; text-align: center;">';

        // CA Total
        print '<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; min-width: 120px;">';
        print '<div style="font-size: 0.9em; color: #666;">CA Total</div>';
        print '<div style="font-size: 1.5em; font-weight: bold; color: #1976d2;">'.price($object->amount_ht).'</div>';
        print '</div>';

        // Part Collaborateur
        print '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; min-width: 120px;">';
        print '<div style="font-size: 0.9em; color: #666;">Collaborateur ('.$object->collaborator_percentage.'%)</div>';
        print '<div style="font-size: 1.5em; font-weight: bold; color: #856404;">'.price($object->net_collaborator_amount).'</div>';
        if ($object->total_costs > 0) {
            print '<div style="font-size: 0.8em; color: #dc3545;">-'.price($object->total_costs).' coûts</div>';
        }
        print '</div>';

        // Part Studio
        $studio_percentage = 100 - $object->collaborator_percentage;
        print '<div style="background: #d4edda; padding: 15px; border-radius: 5px; min-width: 120px;">';
        print '<div style="font-size: 0.9em; color: #666;">Studio ('.$studio_percentage.'%)</div>';
        print '<div style="font-size: 1.5em; font-weight: bold; color: #155724;">'.price($object->studio_amount_ht).'</div>';
        print '</div>';

        print '</div>';
        print '</div>';

    } else {
        setEventMessages("Aucun contrat sélectionné", null, 'errors');
    }
}

llxFooter();
$db->close();
?>
