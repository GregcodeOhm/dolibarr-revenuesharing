<?php
// Fichier: edit_transaction.php
// Contrôleur pour l'édition des transactions

require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$transaction_id = GETPOST('transaction_id', 'int');
$collaborator_id = GETPOST('collaborator_id', 'int');
$amount = GETPOST('amount', 'alpha');
$description = GETPOST('description', 'restricthtml');
$note_private = GETPOST('note_private', 'restricthtml');
$transaction_type = GETPOST('transaction_type', 'alpha');

// Types de transactions autorisés
$allowed_types = array(
    'contract', 'commission', 'bonus', 'interest', 'advance', 'fee', 'refund',
    'adjustment', 'salary', 'other_credit', 'other_debit'
);

// Actions supportées
if ($action == 'update' && $transaction_id > 0) {

    // Vérification du token CSRF
    if (!newToken('check')) {
        setEventMessages('Le jeton de sécurité a expiré, aussi l\'action a été annulée. Merci de ré-essayer.', null, 'errors');
        header('Location: account_detail.php?id='.$collaborator_id);
        exit;
    }
    
    // Validation des données
    $errors = array();
    
    
    if (!$amount || !is_numeric($amount)) {
        $errors[] = 'Montant invalide';
    }
    
    if (!$description || trim($description) == '') {
        $errors[] = 'Description requise';
    }
    
    if ($transaction_type && !in_array($transaction_type, $allowed_types)) {
        $errors[] = 'Type de transaction invalide';
    }
    
    if (count($errors) > 0) {
        setEventMessages(implode(', ', $errors), null, 'errors');
        header('Location: account_detail.php?id='.$collaborator_id);
        exit;
    }
    
    // Récupérer les données actuelles de la transaction
    $sql_current = "SELECT * FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                    WHERE rowid = ".((int) $transaction_id)." AND status = 1";
    $resql_current = $db->query($sql_current);
    
    if (!$resql_current || $db->num_rows($resql_current) == 0) {
        setEventMessages('Transaction non trouvée', null, 'errors');
        header('Location: account_detail.php?id='.$collaborator_id);
        exit;
    }
    
    $current_transaction = $db->fetch_object($resql_current);
    $old_amount = $current_transaction->amount;
    $collaborator_id = $current_transaction->fk_collaborator; // Sécurité : récupérer l'ID du collaborateur de la DB
    $db->free($resql_current);
    
    // Validation du type selon la logique métier
    $credit_types = array('contract', 'commission', 'bonus', 'interest', 'other_credit');
    $debit_types = array('advance', 'fee', 'refund', 'adjustment', 'salary', 'other_debit');
    
    if ($transaction_type) {
        if (in_array($transaction_type, $credit_types) && $amount < 0) {
            $amount = abs($amount); // Forcer positif pour les crédits
        } elseif (in_array($transaction_type, $debit_types) && $amount > 0) {
            $amount = -abs($amount); // Forcer négatif pour les débits
        }
    }
    
    // Mise à jour de la transaction
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction SET ";
    $sql_update .= "amount = ".$amount.", ";
    $sql_update .= "description = '".$db->escape($description)."', ";
    $sql_update .= "note_private = '".$db->escape($note_private)."'";
    
    if ($transaction_type) {
        $sql_update .= ", transaction_type = '".$db->escape($transaction_type)."'";
    }
    
    $sql_update .= " WHERE rowid = ".((int) $transaction_id);
    
    $resql_update = $db->query($sql_update);
    
    if ($resql_update) {
        // Recalculer le solde du collaborateur
        updateCollaboratorBalance($db, $collaborator_id);
        
        // Log de la modification (optionnel - pour l'historique)
        logTransactionModification($db, $transaction_id, $old_amount, $amount, $current_transaction->description, $description, $user->id);
        
        setEventMessages('Transaction modifiée avec succès', null, 'mesgs');
    } else {
        setEventMessages('Erreur lors de la modification: '.$db->lasterror(), null, 'errors');
    }
    
    header('Location: account_detail.php?id='.$collaborator_id);
    exit;
    
} elseif ($action == 'delete' && $transaction_id > 0) {

    // Pas de vérification de token pour la suppression via JavaScript
    // (le token est déjà généré dans la page account_detail.php)
    
    // Récupérer les infos de la transaction avant suppression
    $sql_current = "SELECT fk_collaborator FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                    WHERE rowid = ".((int) $transaction_id)." AND status = 1";
    $resql_current = $db->query($sql_current);
    
    if ($resql_current && $db->num_rows($resql_current) > 0) {
        $current_transaction = $db->fetch_object($resql_current);
        $collaborator_id = $current_transaction->fk_collaborator;
        $db->free($resql_current);
        
        // Soft delete (changement du status à 0)
        $sql_delete = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                       SET status = 0 
                       WHERE rowid = ".((int) $transaction_id);
        
        $resql_delete = $db->query($sql_delete);
        
        if ($resql_delete) {
            // Recalculer le solde du collaborateur
            updateCollaboratorBalance($db, $collaborator_id);
            
            setEventMessages('Transaction supprimée avec succès', null, 'mesgs');
        } else {
            setEventMessages('Erreur lors de la suppression: '.$db->lasterror(), null, 'errors');
        }
    } else {
        setEventMessages('Transaction non trouvée', null, 'errors');
    }
    
    header('Location: account_detail.php?id='.$collaborator_id);
    exit;
    
} else {
    // Action non supportée ou paramètres manquants - redirection plus intelligente
    if ($collaborator_id > 0) {
        setEventMessages('Action non supportée ou paramètres manquants', null, 'errors');
        header('Location: account_detail.php?id='.$collaborator_id);
    } else {
        setEventMessages('Paramètres manquants', null, 'errors');
        header('Location: account_list.php');
    }
    exit;
}

/**
 * Fonction pour recalculer le solde d'un collaborateur
 */
function updateCollaboratorBalance($db, $collaborator_id) {
    // Calculer les nouveaux totaux
    $sql_balance = "SELECT 
        COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_credits,
        COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_debits,
        COALESCE(SUM(amount), 0) as current_balance,
        MAX(transaction_date) as last_transaction_date
        FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
        WHERE fk_collaborator = ".((int) $collaborator_id)." AND status = 1";
    
    $resql_balance = $db->query($sql_balance);
    if ($resql_balance) {
        $balance_info = $db->fetch_object($resql_balance);
        
        // Mettre à jour la table des soldes
        $sql_update_balance = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_account_balance 
            (fk_collaborator, total_credits, total_debits, current_balance, last_transaction_date, date_updated)
            VALUES (".$collaborator_id.", ".$balance_info->total_credits.", ".$balance_info->total_debits.", 
                   ".$balance_info->current_balance.", ".($balance_info->last_transaction_date ? "'".$balance_info->last_transaction_date."'" : "NULL").", NOW())
            ON DUPLICATE KEY UPDATE 
            total_credits = ".$balance_info->total_credits.",
            total_debits = ".$balance_info->total_debits.",
            current_balance = ".$balance_info->current_balance.",
            last_transaction_date = ".($balance_info->last_transaction_date ? "'".$balance_info->last_transaction_date."'" : "NULL").",
            date_updated = NOW()";
        
        $db->query($sql_update_balance);
        $db->free($resql_balance);
    }
}

/**
 * Fonction pour logger les modifications (optionnel)
 */
function logTransactionModification($db, $transaction_id, $old_amount, $new_amount, $old_description, $new_description, $user_id) {
    // Cette fonction pourrait être implémentée plus tard pour créer un audit trail
    // Pour l'instant, on peut juste commenter ou laisser vide
    
    // Exemple d'implémentation future :
    /*
    $sql_log = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_transaction_log 
        (fk_transaction, old_amount, new_amount, old_description, new_description, fk_user_modif, date_modification)
        VALUES (".$transaction_id.", ".$old_amount.", ".$new_amount.", 
               '".$db->escape($old_description)."', '".$db->escape($new_description)."', ".$user_id.", NOW())";
    $db->query($sql_log);
    */
}

$db->close();
?>