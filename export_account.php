<?php
/**
 * Fichier de traitement des exports de comptes collaborateurs
 * Fichier: /htdocs/custom/revenuesharing/export_account.php
 */

require_once '../../main.inc.php';
require_once './class/export_account.class.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');
$format = GETPOST('format', 'alpha'); // 'pdf' ou 'excel'
$filter_type = GETPOST('filter_type', 'alpha');
$filter_year = GETPOST('filter_year', 'int');
$show_previsionnel = GETPOST('show_previsionnel', 'int') ? true : false;

if ($id <= 0) {
    setEventMessages('ID collaborateur manquant', null, 'errors');
    header('Location: account_list.php');
    exit;
}

// Vérification du token CSRF
if ($action == 'export' && !newToken('check')) {
    setEventMessages('Token de sécurité invalide', null, 'errors');
    header('Location: account_detail.php?id='.$id);
    exit;
}

if ($action == 'export' && in_array($format, array('pdf', 'excel'))) {
    
    $export = new ExportAccount($db);
    $export->collaborator_id = $id;
    
    try {
        if ($format == 'pdf') {
            $result = $export->exportToPDF($filter_type, $filter_year, $show_previsionnel);
        } else {
            $result = $export->exportToExcel($filter_type, $filter_year, $show_previsionnel);
        }
        
        // Les méthodes d'export appellent exit() directement après envoi du fichier
        // Si on arrive ici, c'est qu'il y a eu une erreur dans la génération
        setEventMessages('Erreur lors de la génération du fichier', null, 'errors');
        header('Location: account_detail.php?id='.$id);
        exit;
        
    } catch (Exception $e) {
        setEventMessages('Erreur lors de l\'export: '.$e->getMessage(), null, 'errors');
        header('Location: account_detail.php?id='.$id);
        exit;
    }
    
} else {
    // Action non reconnue, retour à la liste
    header('Location: account_detail.php?id='.$id);
    exit;
}
?>