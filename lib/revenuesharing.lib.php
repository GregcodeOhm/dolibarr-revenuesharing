<?php
/**
 * Bibliothèque de fonctions utilitaires pour le module Revenue Sharing
 *
 * @package     RevenueSharing
 * @author      Your Name
 * @version     1.0
 */

// Protection contre l'inclusion directe
if (!defined('DOL_VERSION')) {
    print "Error: Must be called from a Dolibarr context";
    exit;
}

/**
 * Formate un nombre d'octets en format lisible (Ko, Mo, Go, etc.)
 *
 * @param int $size Taille en octets
 * @param int $precision Nombre de décimales
 * @return string Taille formatée
 */
function formatBytes($size, $precision = 2) {
    $units = array('o', 'Ko', 'Mo', 'Go', 'To');

    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }

    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Calcule les montants d'un contrat Revenue Sharing
 *
 * @param object $contract Objet contrat avec les propriétés amount_ht, collaborator_percentage, nb_sessions, cost_per_session
 * @return object Objet avec les montants calculés
 */
function calculateContractAmounts($contract) {
    if ($contract->amount_ht > 0 && $contract->collaborator_percentage > 0) {
        $contract->collaborator_amount_ht = ($contract->amount_ht * $contract->collaborator_percentage) / 100;
        $contract->studio_amount_ht = $contract->amount_ht - $contract->collaborator_amount_ht;
        $contract->total_costs = $contract->nb_sessions * $contract->cost_per_session;
        $contract->net_collaborator_amount = $contract->collaborator_amount_ht - $contract->total_costs;
    }
    return $contract;
}

/**
 * Génère la prochaine référence de contrat
 *
 * @param DoliDB $db Instance de base de données
 * @param string $prefix Préfixe personnalisé (optionnel)
 * @return string Prochaine référence
 */
function getNextContractRef($db, $prefix = null) {
    if (!$prefix) {
        $year = date('Y');
        $prefix = 'RC'.$year.'-';
    }

    $sql = "SELECT MAX(CAST(SUBSTRING(ref, LENGTH('".$prefix."')+1) AS UNSIGNED)) as max_num";
    $sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract";
    $sql .= " WHERE ref LIKE '".$prefix."%'";

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $num = $obj->max_num ? $obj->max_num + 1 : 1;
        $db->free($resql);
        return $prefix.sprintf('%04d', $num);
    }
    return $prefix.'0001';
}

/**
 * Valide et nettoie les données d'un collaborateur
 *
 * @param array $data Données du collaborateur
 * @return array Données nettoyées et validées
 */
function validateCollaboratorData($data) {
    $cleaned = array();

    // Nom/Label (obligatoire)
    $cleaned['label'] = isset($data['label']) ? trim($data['label']) : '';

    // Email (optionnel mais doit être valide si fourni)
    if (isset($data['email']) && !empty($data['email'])) {
        $email = trim($data['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $cleaned['email'] = $email;
        }
    }

    // Pourcentage par défaut (doit être entre 0 et 100)
    if (isset($data['default_percentage'])) {
        $percentage = floatval($data['default_percentage']);
        $cleaned['default_percentage'] = max(0, min(100, $percentage));
    }

    // Statut actif/inactif
    $cleaned['active'] = isset($data['active']) ? intval($data['active']) : 1;

    return $cleaned;
}

/**
 * Formate un prix selon les standards Dolibarr
 *
 * @param float $amount Montant à formater
 * @param int $decimals Nombre de décimales
 * @return string Prix formaté
 */
function formatPrice($amount, $decimals = 2) {
    global $conf;

    if (function_exists('price')) {
        return price($amount);
    } else {
        // Fallback si la fonction price de Dolibarr n'est pas disponible
        return number_format($amount, $decimals, ',', ' ') . ' €';
    }
}

/**
 * Génère les statistiques de base pour le dashboard
 *
 * @param DoliDB $db Instance de base de données
 * @param int $year Année pour les statistiques (optionnel)
 * @return array Tableau des statistiques
 */
function getRevenueSharingStats($db, $year = null) {
    if (!$year) {
        $year = date('Y');
    }

    $stats = array(
        'nb_collaborators' => 0,
        'nb_contracts' => 0,
        'nb_valid' => 0,
        'nb_draft' => 0,
        'total_ht' => 0,
        'total_collaborator' => 0,
        'total_studio' => 0
    );

    // Nombre de collaborateurs actifs
    $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['nb_collaborators'] = $obj->nb;
        $db->free($resql);
    }

    // Statistiques des contrats pour l'année
    $sql = "SELECT
                COUNT(*) as nb_total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as nb_valid,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as nb_draft,
                SUM(CASE WHEN status = 1 THEN amount_ht ELSE 0 END) as total_ht,
                SUM(CASE WHEN status = 1 THEN collaborator_amount_ht ELSE 0 END) as total_collaborator,
                SUM(CASE WHEN status = 1 THEN studio_amount_ht ELSE 0 END) as total_studio
            FROM ".MAIN_DB_PREFIX."revenuesharing_contract
            WHERE YEAR(date_creation) = ".intval($year);

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['nb_contracts'] = $obj->nb_total;
        $stats['nb_valid'] = $obj->nb_valid;
        $stats['nb_draft'] = $obj->nb_draft;
        $stats['total_ht'] = $obj->total_ht;
        $stats['total_collaborator'] = $obj->total_collaborator;
        $stats['total_studio'] = $obj->total_studio;
        $db->free($resql);
    }

    return $stats;
}

/**
 * Vérifie les permissions utilisateur pour le module Revenue Sharing
 *
 * @param User $user Objet utilisateur Dolibarr
 * @param string $permission Type de permission (read, write, delete)
 * @return bool True si autorisé
 */
function checkRevenueSharingPermission($user, $permission = 'read') {
    if (!$user || !is_object($user)) {
        return false;
    }

    switch ($permission) {
        case 'read':
            return $user->rights->revenuesharing->read ?? false;
        case 'write':
            return $user->rights->revenuesharing->write ?? false;
        case 'delete':
            return $user->rights->revenuesharing->delete ?? false;
        default:
            return false;
    }
}

/**
 * Logs des actions importantes du module
 *
 * @param DoliDB $db Instance de base de données
 * @param User $user Utilisateur effectuant l'action
 * @param string $action Type d'action
 * @param string $object_type Type d'objet (contract, collaborator, etc.)
 * @param int $object_id ID de l'objet
 * @param string $description Description de l'action
 * @return bool True si succès
 */
function logRevenueSharingAction($db, $user, $action, $object_type, $object_id, $description = '') {
    if (!function_exists('dol_syslog')) {
        return true; // Pas de log si fonction non disponible
    }

    $message = sprintf(
        "RevenueSharing: %s %s ID:%d by user %s - %s",
        $action,
        $object_type,
        $object_id,
        $user->login,
        $description
    );

    dol_syslog($message);
    return true;
}

/**
 * Nettoie et valide une référence de contrat
 *
 * @param string $ref Référence à nettoyer
 * @return string Référence nettoyée
 */
function cleanContractRef($ref) {
    // Supprime les caractères non autorisés
    $ref = preg_replace('/[^A-Za-z0-9\-_]/', '', $ref);

    // Limite la longueur
    $ref = substr($ref, 0, 30);

    return strtoupper($ref);
}

?>