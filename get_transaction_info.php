<?php
// Fichier: get_transaction_info.php
// Endpoint AJAX pour charger les informations d'une transaction (libellé, contrat lié, etc.)

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
$transaction_id = GETPOST('transaction_id', 'int');

if ($transaction_id <= 0) {
    echo json_encode(array('success' => false, 'error' => 'ID transaction manquant'));
    exit;
}

try {
    // Récupérer les informations de la transaction avec les liaisons
    $sql = "SELECT t.*, 
                   c.rowid as contract_id, c.label as contract_label, c.ref as contract_ref,
                   f.ref as facture_ref, f.total_ht as facture_amount, soc_f.nom as facture_client,
                   ff.ref as facture_fourn_ref, ff.total_ht as facture_fourn_amount, soc_ff.nom as facture_fourn_client
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = c.fk_facture
            LEFT JOIN ".MAIN_DB_PREFIX."societe soc_f ON soc_f.rowid = f.fk_soc
            LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
            LEFT JOIN ".MAIN_DB_PREFIX."societe soc_ff ON soc_ff.rowid = ff.fk_soc
            WHERE t.rowid = ".((int) $transaction_id)." AND t.status = 1";
    
    $resql = $db->query($sql);
    
    if (!$resql) {
        throw new Exception($db->lasterror());
    }
    
    if ($db->num_rows($resql) == 0) {
        echo json_encode(array('success' => false, 'error' => 'Transaction non trouvée'));
        exit;
    }
    
    $transaction = $db->fetch_object($resql);
    $db->free($resql);
    
    $response = array('success' => true);
    
    // Déterminer le type de libellé et les informations à retourner
    if ($transaction->fk_contract && $transaction->contract_label) {
        // Transaction liée à un contrat
        $response['label'] = $transaction->contract_label;
        $response['label_type'] = 'contract';
        $response['contract_id'] = $transaction->contract_id;
        
        // Si le contrat a une facture client liée
        if ($transaction->facture_ref) {
            $response['facture_info'] = array(
                'ref' => $transaction->facture_ref,
                'client' => $transaction->facture_client,
                'amount' => floatval($transaction->facture_amount)
            );
        }
        
    } elseif ($transaction->fk_facture_fourn && $transaction->facture_fourn_ref) {
        // Transaction liée à une facture fournisseur
        $response['label'] = 'Facture fournisseur: ' . $transaction->facture_fourn_ref;
        $response['label_type'] = 'supplier_invoice';
        $response['supplier_invoice_id'] = $transaction->fk_facture_fourn;
        $response['supplier_invoice_info'] = array(
            'ref' => $transaction->facture_fourn_ref,
            'supplier' => $transaction->facture_fourn_client,
            'amount' => floatval($transaction->facture_fourn_amount)
        );
        
    } else {
        // Transaction non liée
        $response['label'] = null;
        $response['label_type'] = 'none';
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}

$db->close();
?>