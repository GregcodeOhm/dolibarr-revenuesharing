<?php
/**
 * API pour récupérer le solde d'un collaborateur
 * Fichier: /htdocs/custom/revenuesharing/get_collaborator_balance.php
 */

require_once '../../main.inc.php';

header('Content-Type: application/json');

// Security check
if (!$user->admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit;
}

$collaborator_id = GETPOST('id', 'int');

if ($collaborator_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID collaborateur manquant']);
    exit;
}

try {
    // Récupérer le solde brut du collaborateur (transactions)
    $sql_balance = "SELECT 
        COALESCE(SUM(amount), 0) as gross_balance
        FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
        WHERE fk_collaborator = ".(int)$collaborator_id." AND status = 1";
    
    $resql_balance = $db->query($sql_balance);
    if (!$resql_balance) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors du calcul du solde']);
        exit;
    }
    
    $balance_info = $db->fetch_object($resql_balance);
    $gross_balance = (float)$balance_info->gross_balance;
    $db->free($resql_balance);
    
    // Calculer le total des soldes utilisés dans les déclarations de salaires
    $sql_used = "SELECT 
        COALESCE(SUM(solde_utilise), 0) as total_used
        FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration 
        WHERE fk_collaborator = ".(int)$collaborator_id." 
        AND status IN (1, 2, 3)"; // Tous les statuts sauf supprimé
    
    $resql_used = $db->query($sql_used);
    $total_used = 0;
    if ($resql_used) {
        $used_info = $db->fetch_object($resql_used);
        $total_used = (float)$used_info->total_used;
        $db->free($resql_used);
    }
    
    // Solde disponible = solde brut - soldes utilisés
    $current_balance = $gross_balance - $total_used;
    
    // Calculer le cachet unitaire moyen ET la masse salariale moyenne
    $sql_cachet = "SELECT 
        AVG(cachet_brut_unitaire) as avg_cachet,
        AVG(masse_salariale / total_days) as avg_masse_par_jour
        FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration 
        WHERE fk_collaborator = ".(int)$collaborator_id." 
        AND cachet_brut_unitaire > 0 AND total_days > 0";
    
    $resql_cachet = $db->query($sql_cachet);
    $avg_cachet = 0;
    $avg_masse_par_jour = 0;
    if ($resql_cachet) {
        $cachet_info = $db->fetch_object($resql_cachet);
        $avg_cachet = (float)$cachet_info->avg_cachet;
        $avg_masse_par_jour = (float)$cachet_info->avg_masse_par_jour;
        $db->free($resql_cachet);
    }
    
    // Si pas de données historiques, utiliser des valeurs par défaut
    if ($avg_cachet <= 0) {
        $avg_cachet = 150.00;
    }
    if ($avg_masse_par_jour <= 0) {
        // Estimation : cachet + 55% de charges = masse salariale
        $avg_masse_par_jour = $avg_cachet * 1.55;
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $current_balance,
        'gross_balance' => $gross_balance,
        'total_used' => $total_used,
        'cachet_unitaire' => $avg_cachet,
        'masse_unitaire' => $avg_masse_par_jour,
        'nb_cachets_possible' => $avg_masse_par_jour > 0 && $current_balance > 0 ? floor($current_balance / $avg_masse_par_jour) : 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$db->close();
?>