<?php
// Fichier: manage_transaction_links.php
// Endpoint AJAX pour gérer les liaisons entre transactions et contrats/factures

require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Accès refusé'));
    exit;
}

// Force JSON response
header('Content-Type: application/json');

// Parameters
$action = GETPOST('action', 'alpha');
$transaction_id = GETPOST('transaction_id', 'int');

try {
    if ($action == 'link_contract') {
        $contract_id = GETPOST('contract_id', 'int');
        
        // Vérification du token CSRF
        if (!newToken('check')) {
            echo json_encode(['success' => false, 'error' => 'Token de sécurité invalide']);
            exit;
        }
        
        if ($transaction_id <= 0 || $contract_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
            exit;
        }
        
        // Vérifier que la transaction existe
        $sql_check_trans = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                           WHERE rowid = ".((int) $transaction_id)." AND status = 1";
        $resql_check_trans = $db->query($sql_check_trans);
        if (!$resql_check_trans || $db->num_rows($resql_check_trans) == 0) {
            echo json_encode(['success' => false, 'error' => 'Transaction non trouvée']);
            exit;
        }
        $db->free($resql_check_trans);
        
        // Vérifier que le contrat existe et n'est pas déjà lié
        $sql_check_contract = "SELECT c.rowid FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
                              LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_transaction t ON t.fk_contract = c.rowid AND t.status = 1
                              WHERE c.rowid = ".((int) $contract_id)." AND c.status = 1 AND t.rowid IS NULL";
        $resql_check_contract = $db->query($sql_check_contract);
        if (!$resql_check_contract || $db->num_rows($resql_check_contract) == 0) {
            echo json_encode(['success' => false, 'error' => 'Contrat non trouvé ou déjà utilisé']);
            exit;
        }
        $db->free($resql_check_contract);
        
        // Lier le contrat à la transaction
        $sql_link = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                     SET fk_contract = ".((int) $contract_id)."
                     WHERE rowid = ".((int) $transaction_id);
        
        $resql_link = $db->query($sql_link);
        if ($resql_link) {
            echo json_encode(['success' => true, 'message' => 'Contrat lié avec succès']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la liaison: '.$db->lasterror()]);
        }
        
    } elseif ($action == 'unlink_contract') {
        
        // Vérification du token CSRF
        if (!newToken('check')) {
            echo json_encode(['success' => false, 'error' => 'Token de sécurité invalide']);
            exit;
        }
        
        if ($transaction_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID transaction manquant']);
            exit;
        }
        
        // Délier le contrat de la transaction
        $sql_unlink = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                       SET fk_contract = NULL
                       WHERE rowid = ".((int) $transaction_id)." AND status = 1";
        
        $resql_unlink = $db->query($sql_unlink);
        if ($resql_unlink) {
            echo json_encode(['success' => true, 'message' => 'Contrat délié avec succès']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors du déliage: '.$db->lasterror()]);
        }
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$db->close();
?>