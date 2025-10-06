<?php
// Fichier: get_available_contracts.php
// Endpoint AJAX pour charger les contrats disponibles pour liaison

require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Accès refusé'));
    exit;
}

// Force JSON response
header('Content-Type: application/json');

try {
    // Récupérer les contrats disponibles (non encore liés à une transaction)
    $sql = "SELECT c.rowid, c.label, c.amount_ht, col.label as collaborator_label
            FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator col ON col.rowid = c.fk_collaborator
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_transaction t ON t.fk_contract = c.rowid AND t.status = 1
            WHERE c.status = 1
            AND t.rowid IS NULL
            ORDER BY c.date_creation DESC, c.label";
    
    $resql = $db->query($sql);
    
    if (!$resql) {
        throw new Exception($db->lasterror());
    }
    
    $contracts = array();
    
    while ($obj = $db->fetch_object($resql)) {
        $contracts[] = array(
            'id' => $obj->rowid,
            'label' => $obj->label ?: 'Contrat #'.$obj->rowid,
            'amount_ht' => floatval($obj->amount_ht),
            'collaborator' => $obj->collaborator_label ?: 'Collaborateur non défini'
        );
    }
    
    $db->free($resql);
    
    echo json_encode(array(
        'success' => true,
        'contracts' => $contracts
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}

$db->close();
?>