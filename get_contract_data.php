<?php
// Fichier: get_contract_data.php
// Endpoint AJAX pour charger les données d'un contrat

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
$contract_id = GETPOST('id', 'int');

if ($contract_id <= 0) {
    echo json_encode(array('success' => false, 'error' => 'ID contrat manquant'));
    exit;
}

try {
    // Récupérer les données du contrat avec les informations liées
    $sql = "SELECT c.*, 
                   col.label as collaborator_label,
                   f.ref as facture_ref, f.total_ht as facture_amount, soc_f.nom as facture_client,
                   p.ref as propal_ref, p.total_ht as propal_amount, soc_p.nom as propal_client
            FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator col ON col.rowid = c.fk_collaborator
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = c.fk_facture
            LEFT JOIN ".MAIN_DB_PREFIX."societe soc_f ON soc_f.rowid = f.fk_soc
            LEFT JOIN ".MAIN_DB_PREFIX."propal p ON p.rowid = c.fk_propal
            LEFT JOIN ".MAIN_DB_PREFIX."societe soc_p ON soc_p.rowid = p.fk_soc
            WHERE c.rowid = ".((int) $contract_id);
    
    $resql = $db->query($sql);
    
    if (!$resql) {
        throw new Exception($db->lasterror());
    }
    
    if ($db->num_rows($resql) == 0) {
        echo json_encode(array('success' => false, 'error' => 'Contrat non trouvé'));
        exit;
    }
    
    $contract = $db->fetch_object($resql);
    $db->free($resql);
    
    // Préparer les données pour la réponse
    $contract_data = array(
        'id' => $contract->rowid,
        'fk_collaborator' => $contract->fk_collaborator,
        'collaborator_label' => $contract->collaborator_label,
        'label' => $contract->label,
        'date_creation' => $contract->date_creation ? date('Y-m-d', strtotime($contract->date_creation)) : '',
        'amount_ht' => floatval($contract->amount_ht),
        'amount_ttc' => floatval($contract->amount_ttc),
        'percentage' => floatval($contract->percentage),
        'commission_amount' => floatval($contract->commission_amount),
        'status' => $contract->status,
        
        // Informations facture liée
        'fk_facture' => $contract->fk_facture,
        'facture_ref' => $contract->facture_ref,
        'facture_amount' => floatval($contract->facture_amount),
        'facture_client' => $contract->facture_client,
        
        // Informations devis lié
        'fk_propal' => $contract->fk_propal,
        'propal_ref' => $contract->propal_ref,
        'propal_amount' => floatval($contract->propal_amount),
        'propal_client' => $contract->propal_client
    );
    
    echo json_encode(array(
        'success' => true,
        'contract' => $contract_data
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}

$db->close();
?>