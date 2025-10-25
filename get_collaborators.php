<?php
// Fichier: get_collaborators.php  
// Endpoint AJAX pour charger la liste des collaborateurs

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
    // Récupérer la liste des collaborateurs actifs
    $sql = "SELECT c.rowid, c.label, u.firstname, u.lastname, c.default_percentage, c.cost_per_session
            FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
            LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
            WHERE c.active = 1 
            ORDER BY c.label";
    
    $resql = $db->query($sql);
    
    if (!$resql) {
        throw new Exception($db->lasterror());
    }
    
    $collaborators = array();
    
    while ($obj = $db->fetch_object($resql)) {
        $label = $obj->label;
        if (!$label && $obj->firstname && $obj->lastname) {
            $label = $obj->firstname . ' ' . $obj->lastname;
        }
        if (!$label) {
            $label = 'Collaborateur #' . $obj->rowid;
        }
        
        $collaborators[] = array(
            'id' => $obj->rowid,
            'label' => $label,
            'default_percentage' => floatval($obj->default_percentage),
            'cost_per_session' => floatval($obj->cost_per_session)
        );
    }
    
    $db->free($resql);
    
    echo json_encode(array(
        'success' => true,
        'collaborators' => $collaborators
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}

$db->close();
?>