<?php
// Version complète avec toutes les fonctions : création, consultation, édition, validation
// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/lib/revenuesharing.lib.php';

// Load translation files
$langs->load("revenuesharing@revenuesharing");

// Security check
if (!$user->id) {
    accessforbidden();
}

// Permissions
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

// AJAX FACTURES
if (GETPOST('action', 'alpha') == 'search_factures') {
    $search = GETPOST('term', 'alphanohtml');
    $results = array();
    
    if (strlen($search) >= 2) {
        $sql = "SELECT f.rowid, f.ref, f.total_ht, f.total_ttc, f.fk_statut,";
        $sql .= " s.nom as client";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture f";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc";
        $sql .= " WHERE f.entity = ".(int)$conf->entity;
        $sql .= " AND f.ref LIKE '%".$db->escape($search)."%'";
        $sql .= " AND f.fk_statut >= 1";
        $sql .= " ORDER BY f.datec DESC LIMIT 20";
        
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $client_info = !empty($obj->client) ? $obj->client : 'Client non défini';
                
                $results[] = array(
                    'value' => (int)$obj->rowid,
                    'label' => $obj->ref.' - '.price($obj->total_ht).' HT ('.$client_info.')',
                    'total_ht' => (float)$obj->total_ht,
                    'total_ttc' => (float)$obj->total_ttc,
                    'ref' => $obj->ref,
                    'client' => $client_info
                );
            }
            $db->free($resql);
        }
    }
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX DEVIS
if (GETPOST('action', 'alpha') == 'search_propals') {
    $search = GETPOST('term', 'alphanohtml');
    $results = array();
    
    if (strlen($search) >= 2) {
        $sql = "SELECT p.rowid, p.ref, p.total_ht, p.total_ttc, p.fk_statut,";
        $sql .= " s.nom as client";
        $sql .= " FROM ".MAIN_DB_PREFIX."propal p";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = p.fk_soc";
        $sql .= " WHERE p.entity = ".(int)$conf->entity;
        $sql .= " AND p.ref LIKE '%".$db->escape($search)."%'";
        $sql .= " AND p.fk_statut >= 1";
        $sql .= " ORDER BY p.datep DESC LIMIT 20";
        
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $client_info = !empty($obj->client) ? $obj->client : 'Client non défini';
                
                $results[] = array(
                    'value' => (int)$obj->rowid,
                    'label' => $obj->ref.' - '.price($obj->total_ht).' HT ('.$client_info.')',
                    'total_ht' => (float)$obj->total_ht,
                    'total_ttc' => (float)$obj->total_ttc,
                    'ref' => $obj->ref,
                    'client' => $client_info
                );
            }
            $db->free($resql);
        }
    }
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

// Variables
$action = GETPOST('action', 'alpha');
$id = GETPOST('id', 'int');
$cancel = GETPOST('cancel', 'alpha');

$object = new stdClass();
$object->id = 0;
$object->ref = '';
$object->fk_collaborator = 0;
$object->fk_project = 0;
$object->fk_facture = 0;
$object->fk_propal = 0;
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
$object->date_creation = '';
$object->date_valid = '';
$object->collaborator_label = '';
$object->facture_ref = '';
$object->propal_ref = '';

$error = 0;
$errors = array();

// Functions are now in lib/revenuesharing.lib.php

// Chargement de l'objet si ID fourni
if ($id > 0) {
    $sql = "SELECT rc.*, c.label as collaborator_label, u.firstname, u.lastname,";
    $sql .= " p.ref as project_ref, p.title as project_title,";
    $sql .= " f.ref as facture_ref, f.total_ht as facture_ht, f.total_ttc as facture_ttc,";
    $sql .= " pr.ref as propal_ref, pr.total_ht as propal_ht, pr.total_ttc as propal_ttc";
    $sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract rc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = rc.fk_collaborator";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = rc.fk_project";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = rc.fk_facture";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."propal pr ON pr.rowid = rc.fk_propal";
    $sql .= " WHERE rc.rowid = ".((int) $id);
    
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $object->id = $obj->rowid;
        $object->ref = $obj->ref;
        $object->fk_collaborator = $obj->fk_collaborator;
        $object->fk_project = $obj->fk_project;
        $object->fk_facture = $obj->fk_facture;
        $object->fk_propal = $obj->fk_propal;
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
        $object->date_valid = $obj->date_valid;
        $object->collaborator_label = $obj->collaborator_label;
        $object->facture_ref = $obj->facture_ref;
        $object->propal_ref = $obj->propal_ref;
        $db->free($resql);
    } else {
        setEventMessages("Contrat non trouvé", null, 'errors');
        header("Location: contract_list.php");
        exit;
    }
}

// ACTION VALIDATION
if ($action == 'validate' && $can_write && $id > 0) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET ";
    $sql .= "status = 1, ";
    $sql .= "date_valid = NOW(), ";
    $sql .= "fk_user_valid = ".((int) $user->id)." ";
    $sql .= "WHERE rowid = ".((int) $id);
    
    $resql = $db->query($sql);
    if ($resql) {
        // Synchronisation automatique avec le compte collaborateur
        if ($object->net_collaborator_amount > 0) {
            // Vérifier si les tables de comptes existent
            $sql_check = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."revenuesharing_account_transaction'";
            $resql_check = $db->query($sql_check);
            
            if ($resql_check && $db->num_rows($resql_check) > 0) {
                // Vérifier que cette transaction n'existe pas déjà
                $sql_exist = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                    WHERE fk_contract = ".((int) $id)." AND transaction_type = 'contract'";
                $resql_exist = $db->query($sql_exist);
                
                if ($resql_exist && $db->num_rows($resql_exist) == 0) {
                    // Créer la transaction dans le compte
                    $description = 'Revenus contrat '.$object->ref;
                    if ($object->label) {
                        $description = $object->label;
                    }
                    
                    $sql_account = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_account_transaction (";
                    $sql_account .= "fk_collaborator, fk_contract, fk_facture, transaction_type, ";
                    $sql_account .= "amount, description, transaction_date, date_creation, fk_user_creat, status";
                    $sql_account .= ") VALUES (";
                    $sql_account .= $object->fk_collaborator.", ";
                    $sql_account .= $id.", ";
                    $sql_account .= ($object->fk_facture ? $object->fk_facture : "NULL").", ";
                    $sql_account .= "'contract', ";
                    $sql_account .= $object->net_collaborator_amount.", ";
                    $sql_account .= "'".$db->escape($description)."', ";
                    $sql_account .= "NOW(), ";
                    $sql_account .= "NOW(), ";
                    $sql_account .= $user->id.", ";
                    $sql_account .= "1";
                    $sql_account .= ")";
                    
                    $resql_account = $db->query($sql_account);
                    if ($resql_account) {
                        setEventMessages("Contrat validé avec succès et ajouté au compte collaborateur (".price($object->net_collaborator_amount).")", null, 'mesgs');
                    } else {
                        setEventMessages("Contrat validé mais erreur lors de l'ajout au compte: ".$db->lasterror(), null, 'warnings');
                    }
                }
                if ($resql_exist) $db->free($resql_exist);
            }
            if ($resql_check) $db->free($resql_check);
        } else {
            setEventMessages("Contrat validé avec succès", null, 'mesgs');
        }
        
        $object->status = 1;
        $object->date_valid = date('Y-m-d H:i:s');
    } else {
        setEventMessages("Erreur lors de la validation: ".$db->lasterror(), null, 'errors');
    }
}

// ACTION RETOUR EN BROUILLON
if ($action == 'unvalidate' && $can_write && $id > 0) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET ";
    $sql .= "status = 0, ";
    $sql .= "date_valid = NULL, ";
    $sql .= "fk_user_valid = NULL ";
    $sql .= "WHERE rowid = ".((int) $id)." AND status = 1";
    
    $resql = $db->query($sql);
    if ($resql) {
        // Supprimer la transaction du compte collaborateur si elle existe
        $sql_del_account = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
            WHERE fk_contract = ".((int) $id)." AND transaction_type = 'contract'";
        $resql_del_account = $db->query($sql_del_account);
        
        if ($resql_del_account) {
            setEventMessages("Contrat remis en brouillon avec succès et retiré du compte collaborateur", null, 'mesgs');
        } else {
            setEventMessages("Contrat remis en brouillon avec succès", null, 'mesgs');
        }
        
        $object->status = 0;
        $object->date_valid = '';
        $object->fk_user_valid = '';
    } else {
        setEventMessages("Erreur lors du retour en brouillon: ".$db->lasterror(), null, 'errors');
    }
}

// ACTION SUPPRESSION
if ($action == 'delete' && $can_delete && $id > 0) {
    // Vérification supplémentaire : seuls les contrats en brouillon peuvent être supprimés
    $sql_check = "SELECT status FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE rowid = ".((int) $id);
    $resql_check = $db->query($sql_check);
    
    if ($resql_check) {
        $obj_check = $db->fetch_object($resql_check);
        if ($obj_check->status == 0) {  // Seulement brouillons
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE rowid = ".((int) $id);
            $resql = $db->query($sql);
            if ($resql) {
                setEventMessages("Contrat supprimé avec succès", null, 'mesgs');
                header("Location: contract_list.php");
                exit;
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

// ACTION CRÉATION
if ($action == 'add' && $can_write && !$cancel) {
    $object->fk_collaborator = GETPOST('fk_collaborator', 'int');
    $object->fk_facture = GETPOST('fk_facture', 'int');
    $object->fk_propal = GETPOST('fk_propal', 'int');
    $object->type_contrat = GETPOST('type_contrat', 'alpha');
    $object->label = GETPOST('label', 'alpha');
    $object->amount_ht = str_replace(',', '.', GETPOST('amount_ht', 'alpha'));
    $object->amount_ttc = str_replace(',', '.', GETPOST('amount_ttc', 'alpha'));
    $object->date_prestation_prevue = GETPOST('date_prestation_prevue', 'alpha');
    $object->date_facturation_prevue = GETPOST('date_facturation_prevue', 'alpha');
    
    $calculation_type = GETPOST('calculation_type', 'alpha');
    if ($calculation_type == 'amount') {
        $object->collaborator_amount_ht = str_replace(',', '.', GETPOST('collaborator_amount_ht', 'alpha'));
        if ($object->amount_ht > 0) {
            $object->collaborator_percentage = ($object->collaborator_amount_ht / $object->amount_ht) * 100;
        }
    } else {
        $object->collaborator_percentage = str_replace(',', '.', GETPOST('collaborator_percentage', 'alpha'));
    }
    
    $object->nb_sessions = GETPOST('nb_sessions', 'int');
    $object->cost_per_session = str_replace(',', '.', GETPOST('cost_per_session', 'alpha'));
    $object->note_private = GETPOST('note_private', 'restricthtml');
    $object->note_public = GETPOST('note_public', 'restricthtml');
    
    $object = calculateContractAmounts($object);
    
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
    
    if (!$error) {
        $db->begin();
        try {
            $object->ref = getNextContractRef($db);
            
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_contract (";
            $sql .= "ref, type_contrat, fk_collaborator, label, amount_ht, amount_ttc, ";
            $sql .= "collaborator_percentage, collaborator_amount_ht, studio_amount_ht, ";
            $sql .= "nb_sessions, cost_per_session, total_costs, net_collaborator_amount, ";
            $sql .= "status, date_creation, fk_user_creat";
            if ($object->fk_facture) $sql .= ", fk_facture";
            if ($object->fk_propal) $sql .= ", fk_propal";
            if ($object->note_private) $sql .= ", note_private";
            if ($object->note_public) $sql .= ", note_public";
            if ($object->date_prestation_prevue) $sql .= ", date_prestation_prevue";
            if ($object->date_facturation_prevue) $sql .= ", date_facturation_prevue";
            $sql .= ") VALUES (";
            $sql .= "'".$db->escape($object->ref)."', ";
            $sql .= "'".$db->escape($object->type_contrat ?: 'reel')."', ";
            $sql .= ((int) $object->fk_collaborator).", ";
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
            $sql .= "0, NOW(), ".((int) $user->id);
            if ($object->fk_facture) $sql .= ", ".((int) $object->fk_facture);
            if ($object->fk_propal) $sql .= ", ".((int) $object->fk_propal);
            if ($object->note_private) $sql .= ", '".$db->escape($object->note_private)."'";
            if ($object->note_public) $sql .= ", '".$db->escape($object->note_public)."'";
            if ($object->date_prestation_prevue) $sql .= ", '".$db->escape($object->date_prestation_prevue)."'";
            if ($object->date_facturation_prevue) $sql .= ", '".$db->escape($object->date_facturation_prevue)."'";
            $sql .= ")";
            
            $resql = $db->query($sql);
            if ($resql) {
                $id = $db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_contract");
                $db->commit();
                setEventMessages("Contrat créé avec succès", null, 'mesgs');
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
                exit;
            } else {
                throw new Exception("Erreur SQL: ".$db->lasterror());
            }
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Erreur lors de la création: ".$e->getMessage();
            $error++;
        }
    }
    
    if ($error) {
        setEventMessages("Erreur lors de la création", $errors, 'errors');
        $action = 'create';
    }
}

// ACTION MODIFICATION
if ($action == 'update' && $can_write && $id > 0 && !$cancel) {
    $object->fk_collaborator = GETPOST('fk_collaborator', 'int');
    $object->fk_facture = GETPOST('fk_facture', 'int');
    $object->fk_propal = GETPOST('fk_propal', 'int');
    $object->type_contrat = GETPOST('type_contrat', 'alpha');
    $object->label = GETPOST('label', 'alpha');
    $object->amount_ht = str_replace(',', '.', GETPOST('amount_ht', 'alpha'));
    $object->date_prestation_prevue = GETPOST('date_prestation_prevue', 'alpha');
    $object->date_facturation_prevue = GETPOST('date_facturation_prevue', 'alpha');
    
    // Gestion de la date de création modifiable
    $date_creation_post = GETPOST('date_creation', 'alpha');
    if ($date_creation_post) {
        $object->date_creation = $date_creation_post.' 00:00:00'; // Formatage MySQL
    }
    $object->amount_ttc = str_replace(',', '.', GETPOST('amount_ttc', 'alpha'));
    
    $calculation_type = GETPOST('calculation_type', 'alpha');
    if ($calculation_type == 'amount') {
        $object->collaborator_amount_ht = str_replace(',', '.', GETPOST('collaborator_amount_ht', 'alpha'));
        if ($object->amount_ht > 0) {
            $object->collaborator_percentage = ($object->collaborator_amount_ht / $object->amount_ht) * 100;
        }
    } else {
        $object->collaborator_percentage = str_replace(',', '.', GETPOST('collaborator_percentage', 'alpha'));
    }
    
    $object->nb_sessions = GETPOST('nb_sessions', 'int');
    $object->cost_per_session = str_replace(',', '.', GETPOST('cost_per_session', 'alpha'));
    $object->note_private = GETPOST('note_private', 'restricthtml');
    $object->note_public = GETPOST('note_public', 'restricthtml');
    
    $object = calculateContractAmounts($object);
    
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
    
    if (!$error) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET ";
        $sql .= "fk_collaborator = ".((int) $object->fk_collaborator).", ";
        $sql .= "type_contrat = '".$db->escape($object->type_contrat ?: 'reel')."', ";
        $sql .= "label = '".$db->escape($object->label)."', ";
        $sql .= "amount_ht = ".((float) $object->amount_ht).", ";
        $sql .= "amount_ttc = ".((float) $object->amount_ttc).", ";
        $sql .= "collaborator_percentage = ".((float) $object->collaborator_percentage).", ";
        $sql .= "collaborator_amount_ht = ".((float) $object->collaborator_amount_ht).", ";
        $sql .= "studio_amount_ht = ".((float) $object->studio_amount_ht).", ";
        $sql .= "nb_sessions = ".((int) $object->nb_sessions).", ";
        $sql .= "cost_per_session = ".((float) $object->cost_per_session).", ";
        $sql .= "total_costs = ".((float) $object->total_costs).", ";
        if ($object->date_prestation_prevue) {
            $sql .= "date_prestation_prevue = '".$db->escape($object->date_prestation_prevue)."', ";
        } else {
            $sql .= "date_prestation_prevue = NULL, ";
        }
        if ($object->date_facturation_prevue) {
            $sql .= "date_facturation_prevue = '".$db->escape($object->date_facturation_prevue)."', ";
        } else {
            $sql .= "date_facturation_prevue = NULL, ";
        }
        
        // Ajout de la date de création modifiable
        if ($object->date_creation) {
            $sql .= "date_creation = '".$db->escape($object->date_creation)."', ";
        }
        $sql .= "net_collaborator_amount = ".((float) $object->net_collaborator_amount).", ";
        $sql .= "fk_facture = ".($object->fk_facture ? ((int) $object->fk_facture) : "NULL").", ";
        $sql .= "fk_propal = ".($object->fk_propal ? ((int) $object->fk_propal) : "NULL").", ";
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
            setEventMessages("Erreur lors de la modification: ".$db->lasterror(), null, 'errors');
        }
    } else {
        setEventMessages("Erreur lors de la modification", $errors, 'errors');
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

llxHeader('', 'Contrat Revenue Sharing', '');

// Titre
if ($action == 'create') {
    print load_fiche_titre(img_picto('', 'add', 'class="pictofixedwidth"').' Nouveau Contrat de Partage', '', 'generic');
} elseif ($action == 'edit') {
    print load_fiche_titre(img_picto('', 'edit', 'class="pictofixedwidth"').' Modifier Contrat : '.$object->ref, '', 'generic');
} elseif ($id > 0) {
    print load_fiche_titre(img_picto('', 'contract', 'class="pictofixedwidth"').' Contrat : '.$object->ref, '', 'generic');
} else {
    print load_fiche_titre(' Contrat Revenue Sharing', '', 'generic');
}

// CSS
print '<style>
.section-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    margin: 15px 0;
    padding: 15px;
}
.section-title {
    font-weight: bold;
    color: var(--colortextbackhmenu);
    margin-bottom: 10px;
    font-size: 1.1em;
}
.autocomplete-container {
    position: relative;
    display: inline-block;
    width: 100%;
}
.autocomplete-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ccc;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}
.autocomplete-item {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}
.autocomplete-item:hover,
.autocomplete-item.selected {
    background-color: #f0f0f0;
}
.amount-result {
    font-size: 1.1em;
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 3px;
    display: inline-block;
}
</style>';

// Inclusion du fichier JavaScript unifié
print '<script src="js/autocomplete.js"></script>';

// JavaScript spécifique à cette page
print '<script type="text/javascript">

function calculateFromPercentage() {
    const amount_ht = parseFloat(document.getElementById("amount_ht").value) || 0;
    const percentage = parseFloat(document.getElementById("collaborator_percentage").value) || 0;
    const nb_sessions = parseInt(document.getElementById("nb_sessions").value) || 0;
    const cost_per_session = parseFloat(document.getElementById("cost_per_session").value) || 0;
    
    const collaborator_amount = (amount_ht * percentage) / 100;
    const studio_amount = amount_ht - collaborator_amount;
    const total_costs = nb_sessions * cost_per_session;
    const net_amount = collaborator_amount - total_costs;
    
    document.getElementById("collaborator_amount_ht").value = collaborator_amount.toFixed(2);
    
    const collabDisplay = document.getElementById("collaborator_amount_display");
    const studioDisplay = document.getElementById("studio_amount_display");
    const costsDisplay = document.getElementById("total_costs_display");
    const netDisplay = document.getElementById("net_amount_display");
    
    if (collabDisplay) collabDisplay.textContent = collaborator_amount.toFixed(2) + " €";
    if (studioDisplay) studioDisplay.textContent = studio_amount.toFixed(2) + " €";
    if (costsDisplay) costsDisplay.textContent = total_costs.toFixed(2) + " €";
    if (netDisplay) netDisplay.textContent = net_amount.toFixed(2) + " €";
}

function calculateFromAmount() {
    const amount_ht = parseFloat(document.getElementById("amount_ht").value) || 0;
    const collaborator_amount = parseFloat(document.getElementById("collaborator_amount_ht").value) || 0;
    
    if (amount_ht > 0) {
        const percentage = (collaborator_amount / amount_ht) * 100;
        document.getElementById("collaborator_percentage").value = percentage.toFixed(2);
        calculateFromPercentage();
    }
}

document.addEventListener("DOMContentLoaded", function() {
    console.log("Initialisation autocomplétion unifiée");

    // Utilisation de la classe unifiée RevenueAutocomplete
    if (document.getElementById("facture_search")) {
        window.factureAutocomplete = createFactureAutocomplete("facture_search");
    }
    if (document.getElementById("propal_search")) {
        window.propalAutocomplete = createPropalAutocomplete("propal_search");
    }

    calculateFromPercentage();

    console.log("Autocomplétion unifiée prête !");
});

// Fonction pour basculer entre les modes de contrat
function toggleContractFields() {
    const typeContrat = document.getElementById("type_contrat").value;
    const datesSection = document.getElementById("dates_previsionnel");
    const liaisonSection = document.getElementById("liaison_documents");
    const helpReel = document.getElementById("type_help_reel");
    const helpPrev = document.getElementById("type_help_prev");
    
    if (typeContrat === "previsionnel") {
        // Mode prévisionnel
        datesSection.style.display = "block";
        liaisonSection.style.display = "none";
        helpReel.style.display = "none";
        helpPrev.style.display = "block";
        
        // Rendre les factures/devis optionnels
        const factureSearch = document.getElementById("facture_search");
        const propalSearch = document.getElementById("propal_search");
        if (factureSearch) factureSearch.value = "";
        if (propalSearch) propalSearch.value = "";
        document.getElementById("fk_facture").value = "";
        document.getElementById("fk_propal").value = "";
        
    } else {
        // Mode réel
        datesSection.style.display = "none";
        liaisonSection.style.display = "block";
        helpReel.style.display = "block";
        helpPrev.style.display = "none";
    }
}
</script>';

// AFFICHAGE SELON MODE
if ($action == 'create' || $action == 'edit') {
    // FORMULAIRE
    $form_action = ($action == 'create') ? 'add' : 'update';
    
    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].($id > 0 ? '?id='.$id : '').'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="'.$form_action.'">';
    print '<input type="hidden" id="calculation_type" name="calculation_type" value="percentage">';
    print '<input type="hidden" id="fk_facture" name="fk_facture" value="'.$object->fk_facture.'">';
    print '<input type="hidden" id="fk_propal" name="fk_propal" value="'.$object->fk_propal.'">';

    // Collaborateur
    print '<div class="section-box">';
    print '<div class="section-title"> Informations Générales</div>';
    print '<table class="border centpercent">';
    
    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Collaborateur</td>';
    print '<td>';
    print '<select id="fk_collaborator" name="fk_collaborator" class="flat minwidth200" required>';
    print '<option value="">-- Sélectionner un collaborateur --</option>';

    $sql_collab = "SELECT c.rowid, c.label, u.firstname, u.lastname, c.default_percentage, c.cost_per_session";
    $sql_collab .= " FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
    $sql_collab .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
    $sql_collab .= " WHERE c.active = 1 ORDER BY c.label";

    $resql_collab = $db->query($sql_collab);
    if ($resql_collab) {
        while ($obj_collab = $db->fetch_object($resql_collab)) {
            $selected = ($obj_collab->rowid == $object->fk_collaborator) ? ' selected' : '';
            $label = $obj_collab->label ? $obj_collab->label : ($obj_collab->firstname.' '.$obj_collab->lastname);
            print '<option value="'.$obj_collab->rowid.'" data-percentage="'.$obj_collab->default_percentage.'"'.$selected.'>';
            print $label.' ('.$obj_collab->default_percentage.'% - '.price($obj_collab->cost_per_session).'/séance)';
            print '</option>';
        }
        $db->free($resql_collab);
    }
    print '</select>';
    print '</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Type de contrat</td>';
    print '<td>';
    print '<select id="type_contrat" name="type_contrat" class="flat minwidth200" required onchange="toggleContractFields()">';
    print '<option value="reel"'.($object->type_contrat == 'reel' || empty($object->type_contrat) ? ' selected' : '').'>Contrat réel (avec facture)</option>';
    print '<option value="previsionnel"'.($object->type_contrat == 'previsionnel' ? ' selected' : '').'> Contrat prévisionnel (estimation)</option>';
    print '</select>';
    print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu); margin-top: 5px;">';
    print '<span id="type_help_reel" style="display: '.($object->type_contrat == 'previsionnel' ? 'none' : 'block').';">Contrat lié à une facture existante</span>';
    print '<span id="type_help_prev" style="display: '.($object->type_contrat == 'previsionnel' ? 'block' : 'none').';">Estimation de projet, transformable en contrat réel plus tard</span>';
    print '</div>';
    print '</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Libellé</td>';
    print '<td><input type="text" id="label" name="label" value="'.dol_escape_htmltag($object->label).'" size="50" maxlength="255" required></td>';
    print '</tr>';
    
    // Date de création (modifiable en édition, auto en création)
    if ($action == 'edit') {
        print '<tr>';
        print '<td class="titlefieldcreate">Date de création</td>';
        print '<td>';
        $date_creation_value = '';
        if ($object->date_creation) {
            $date_creation_value = date('Y-m-d', strtotime($object->date_creation));
        }
        print '<input type="date" name="date_creation" value="'.$date_creation_value.'" />';
        print '</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';

    // Section dates prévisionnelles (uniquement pour les prévisionnels)
    print '<div class="section-box" id="dates_previsionnel" style="display: '.($object->type_contrat == 'previsionnel' ? 'block' : 'none').';">';
    print '<div class="section-title">Dates Prévisionnelles</div>';
    print '<table class="border centpercent">';
    
    print '<tr>';
    print '<td class="titlefieldcreate">Date de prestation prévue</td>';
    print '<td>';
    $date_prestation_value = '';
    if (isset($object->date_prestation_prevue) && $object->date_prestation_prevue) {
        $date_prestation_value = $object->date_prestation_prevue;
    } elseif ($action == 'create') {
        $date_prestation_value = date('Y-m-d');
    }
    print '<input type="date" name="date_prestation_prevue" value="'.$date_prestation_value.'" />';
    print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu); margin-top: 3px;">Date estimée de la prestation</div>';
    print '</td>';
    print '</tr>';
    
    print '<tr>';
    print '<td class="titlefieldcreate">Date de facturation prévue</td>';
    print '<td>';
    $date_facturation_value = isset($object->date_facturation_prevue) ? $object->date_facturation_prevue : '';
    print '<input type="date" name="date_facturation_prevue" value="'.$date_facturation_value.'" />';
    print '<div style="font-size: 0.9em; color: var(--colortextbackhmenu); margin-top: 3px;">Optionnel - Quand facturer ce contrat</div>';
    print '</td>';
    print '</tr>';
    
    print '</table>';
    print '</div>';

    // Recherche documents (création et édition)
    print '<div class="section-box" id="liaison_documents">';
    print '<div class="section-title">Liaison Documents</div>';
    print '<table class="border centpercent">';

    print '<tr>';
    print '<td class="titlefieldcreate">Recherche Facture</td>';
    print '<td>';
    print '<input type="text" id="facture_search" placeholder="Tapez une référence..." style="width: 300px;"';
    if ($action == 'edit' && $object->facture_ref) {
        print ' value="'.$object->facture_ref.'"';
    }
    print '>';
    print '</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Recherche Devis</td>';
    print '<td>';
    print '<input type="text" id="propal_search" placeholder="Tapez une référence..." style="width: 300px;"';
    if ($action == 'edit' && $object->propal_ref) {
        print ' value="'.$object->propal_ref.'"';
    }
    print '>';
    print '</td>';
    print '</tr>';

    print '</table>';
    print '</div>';

    // Montants
    print '<div class="section-box">';
    print '<div class="section-title">Montants</div>';
    print '<table class="border centpercent">';

    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Montant HT</td>';
    print '<td><input type="number" id="amount_ht" name="amount_ht" value="'.$object->amount_ht.'" step="0.01" min="0" required onchange="calculateFromPercentage()" style="width: 120px;"> €</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Montant TTC</td>';
    print '<td><input type="number" id="amount_ttc" name="amount_ttc" value="'.$object->amount_ttc.'" step="0.01" min="0" style="width: 120px;"> €</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="fieldrequired titlefieldcreate">Pourcentage Collaborateur</td>';
    print '<td><input type="number" id="collaborator_percentage" name="collaborator_percentage" value="'.$object->collaborator_percentage.'" step="0.01" min="0" max="100" onchange="calculateFromPercentage()" style="width: 80px;"> %</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Montant Collaborateur</td>';
    print '<td>';
    print '<input type="number" id="collaborator_amount_ht" name="collaborator_amount_ht" value="'.$object->collaborator_amount_ht.'" step="0.01" min="0" onchange="calculateFromAmount()" style="width: 120px;"> €';
    print '<span id="collaborator_amount_display" class="amount-result" style="background: #d4edda; color: #155724; margin-left: 10px;">'.price($object->collaborator_amount_ht).'</span>';
    print '</td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Nombre de séances</td>';
    print '<td><input type="number" id="nb_sessions" name="nb_sessions" value="'.$object->nb_sessions.'" min="0" onchange="calculateFromPercentage()" style="width: 80px;"></td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Coût par séance</td>';
    print '<td><input type="number" id="cost_per_session" name="cost_per_session" value="'.$object->cost_per_session.'" step="0.01" min="0" onchange="calculateFromPercentage()" style="width: 120px;"> €</td>';
    print '</tr>';

    print '</table>';
    print '</div>';

    // Résumé calculs
    print '<div class="section-box">';
    print '<div class="section-title">Résumé</div>';
    print '<table class="border centpercent">';

    print '<tr>';
    print '<td class="titlefieldcreate">Part Studio</td>';
    print '<td><span id="studio_amount_display" class="amount-result" style="background: #cce5ff; color: #0066cc;">'.price($object->studio_amount_ht).'</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Coûts totaux</td>';
    print '<td><span id="total_costs_display" class="amount-result" style="background: #ffcccc; color: #cc0000;">'.price($object->total_costs).'</span></td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Net Collaborateur</td>';
    print '<td><span id="net_amount_display" class="amount-result" style="background: #d4edda; color: #155724; font-size: 1.2em;">'.price($object->net_collaborator_amount).'</span></td>';
    print '</tr>';

    print '</table>';
    print '</div>';

    // Notes
    print '<div class="section-box">';
    print '<div class="section-title">Notes</div>';
    print '<table class="border centpercent">';

    print '<tr>';
    print '<td class="titlefieldcreate">Note privée</td>';
    print '<td><textarea id="note_private" name="note_private" rows="3" cols="80">'.dol_escape_htmltag($object->note_private).'</textarea></td>';
    print '</tr>';

    print '<tr>';
    print '<td class="titlefieldcreate">Note publique</td>';
    print '<td><textarea id="note_public" name="note_public" rows="3" cols="80">'.dol_escape_htmltag($object->note_public).'</textarea></td>';
    print '</tr>';

    print '</table>';
    print '</div>';

    // Actions
    print '<div class="center">';
    if ($action == 'create') {
        print '<input type="submit" class="button" value="Créer le contrat">';
    } else {
        print '<input type="submit" class="button" value="Modifier le contrat">';
    }
    print ' <input type="submit" class="button button-cancel" name="cancel" value="Annuler">';
    print '</div>';

    print '</form>';

} else {
    // CONSULTATION
    if ($id > 0) {
        print '<div class="section-box">';
        print '<div class="section-title"> Informations</div>';
        print '<table class="border centpercent">';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Référence</td>';
        print '<td><strong>'.$object->ref.'</strong></td>';
        print '</tr>';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Collaborateur</td>';
        print '<td>'.$object->collaborator_label.'</td>';
        print '</tr>';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Libellé</td>';
        print '<td>'.$object->label.'</td>';
        print '</tr>';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Statut</td>';
        print '<td>';
        if ($object->status == 0) {
            print '<span style="background: #ffebcc; color: #cc6600; padding: 3px 8px; border-radius: 3px;">Brouillon</span>';
            print '<br><small style="color: var(--colortextbackhmenu); font-style: italic;">Modifiable • Supprimable • Peut être validé</small>';
        } else {
            print '<span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 3px;">Validé</span>';
            if ($object->date_valid) {
                print '<br><small class="opacitymedium">le '.dol_print_date(strtotime($object->date_valid), 'dayhour').'</small>';
            }
            print '<br><small style="color: var(--colortextbackhmenu); font-style: italic;">Modifiable • Peut repasser en brouillon</small>';
        }
        print '</td>';
        print '</tr>';
        
        if ($object->facture_ref) {
            print '<tr>';
            print '<td class="titlefieldcreate">Facture liée</td>';
            print '<td>'.$object->facture_ref.'</td>';
            print '</tr>';
        }
        
        if ($object->propal_ref) {
            print '<tr>';
            print '<td class="titlefieldcreate">Devis lié</td>';
            print '<td>'.$object->propal_ref.'</td>';
            print '</tr>';
        }
        
        print '</table>';
        print '</div>';
        
        print '<div class="section-box">';
        print '<div class="section-title">Montants</div>';
        print '<table class="border centpercent">';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Montant HT</td>';
        print '<td><strong>'.price($object->amount_ht).'</strong></td>';
        print '</tr>';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Pourcentage Collaborateur</td>';
        print '<td>'.$object->collaborator_percentage.' %</td>';
        print '</tr>';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Montant Collaborateur</td>';
        print '<td><strong style="color: #155724;">'.price($object->collaborator_amount_ht).'</strong></td>';
        print '</tr>';
        
        print '<tr>';
        print '<td class="titlefieldcreate">Part Studio</td>';
        print '<td><strong style="color: #0066cc;">'.price($object->studio_amount_ht).'</strong></td>';
        print '</tr>';
        
        if ($object->nb_sessions > 0) {
            print '<tr>';
            print '<td class="titlefieldcreate">Séances</td>';
            print '<td>'.$object->nb_sessions.' × '.price($object->cost_per_session).' = '.price($object->total_costs).'</td>';
            print '</tr>';
            
            print '<tr>';
            print '<td class="titlefieldcreate">Net Collaborateur</td>';
            print '<td><strong style="color: #155724; font-size: 1.2em;">'.price($object->net_collaborator_amount).'</strong></td>';
            print '</tr>';
        }
        
        print '</table>';
        print '</div>';
        
        if ($object->date_creation) {
            print '<div class="section-box">';
            print '<div class="section-title">Dates</div>';
            print '<table class="border centpercent">';
            
            print '<tr>';
            print '<td class="titlefieldcreate">Date de création</td>';
            print '<td>';
            if ($object->date_creation) {
                print dol_print_date(strtotime($object->date_creation), 'dayhour');
            }
            print '</td>';
            print '</tr>';
            
            if ($object->date_valid) {
                print '<tr>';
                print '<td class="titlefieldcreate">Date de validation</td>';
                print '<td>'.dol_print_date(strtotime($object->date_valid), 'dayhour').'</td>';
                print '</tr>';
            }
            
            print '</table>';
            print '</div>';
        }
        
        // Actions
        print '<div class="tabsAction">';
        if ($can_write) {
            // Bouton spécial pour les contrats prévisionnels
            if (isset($object->type_contrat) && $object->type_contrat == 'previsionnel') {
                print '<a class="butAction" href="transform_previsionnel.php?id='.$object->id.'" style="background: #007cba; color: white;">Transformer en contrat réel</a>';
            }
            
            print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit" style="background: #ffc107; color: #212529;">'.img_picto('', 'edit', 'class="pictofixedwidth"').' Modifier</a>';
            
            if ($object->status == 0) {
                print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=validate" onclick="return confirm(\'Confirmer la validation ?\')" style="background: #28a745; color: white;">Valider</a>';
            } elseif ($object->status == 1) {
                print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unvalidate" onclick="return confirm(\'Confirmer le retour en brouillon ?\\nCela permettra de modifier et supprimer le contrat.\')" style="background: #fd7e14; color: white;">Repasser en brouillon</a>';
            }
        }
        
        if ($can_delete && $object->status == 0) {
            print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete" onclick="return confirm(\'Confirmer la suppression ?\')" style="background: #dc3545; color: white;">'.img_picto('', 'delete', 'class="pictofixedwidth"').' Supprimer</a>';
        }
        
        print '<a class="butAction" href="contract_list.php" style="background: #6c757d; color: white;">'.img_picto('', 'back', 'class="pictofixedwidth"').' Retour à la Liste</a>';
        print '</div>';
    }
}

llxFooter();
$db->close();
?>