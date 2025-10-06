<?php
// Fichier: transaction_documents.php
// Gestion des documents joints aux transactions

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once __DIR__.'/lib/revenuesharing.lib.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$transaction_id = GETPOST('transaction_id', 'int');
$file_to_delete = GETPOST('file_to_delete', 'alpha');

// Response for AJAX calls
header('Content-Type: application/json');

if ($action == 'upload' && $transaction_id > 0) {
    
    // Vérification du token CSRF
    if (!newToken('check')) {
        echo json_encode(['success' => false, 'error' => 'Token de sécurité invalide']);
        exit;
    }
    
    // Vérifier que la transaction existe
    $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                  WHERE rowid = ".((int) $transaction_id)." AND status = 1";
    $resql_check = $db->query($sql_check);
    if (!$resql_check || $db->num_rows($resql_check) == 0) {
        echo json_encode(['success' => false, 'error' => 'Transaction non trouvée']);
        exit;
    }
    $db->free($resql_check);
    
    // Créer le répertoire de destination
    $upload_dir = DOL_DATA_ROOT.'/revenuesharing/transactions/'.$transaction_id;
    if (!is_dir($upload_dir)) {
        if (!dol_mkdir($upload_dir)) {
            echo json_encode(['success' => false, 'error' => 'Impossible de créer le répertoire']);
            exit;
        }
    }
    
    // Traitement de l'upload
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $uploaded_file = $_FILES['document'];
        
        // Validation du fichier
        $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt');
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé']);
            exit;
        }
        
        // Limite de taille (5MB)
        if ($uploaded_file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)']);
            exit;
        }
        
        // Nom de fichier sécurisé
        $safe_filename = dol_sanitizeFileName($uploaded_file['name']);
        $destination = $upload_dir.'/'.$safe_filename;
        
        // Éviter les doublons
        $counter = 1;
        while (file_exists($destination)) {
            $name_without_ext = pathinfo($safe_filename, PATHINFO_FILENAME);
            $extension = pathinfo($safe_filename, PATHINFO_EXTENSION);
            $destination = $upload_dir.'/'.$name_without_ext.'_'.$counter.'.'.$extension;
            $counter++;
        }
        
        // Déplacer le fichier
        if (move_uploaded_file($uploaded_file['tmp_name'], $destination)) {
            echo json_encode([
                'success' => true, 
                'filename' => basename($destination),
                'size' => formatBytes(filesize($destination))
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']);
    }
    
} elseif ($action == 'delete' && $transaction_id > 0 && $file_to_delete) {
    
    // Vérification du token CSRF
    if (!newToken('check')) {
        echo json_encode(['success' => false, 'error' => 'Token de sécurité invalide']);
        exit;
    }
    
    // Construire le chemin du fichier
    $upload_dir = DOL_DATA_ROOT.'/revenuesharing/transactions/'.$transaction_id;
    $file_path = $upload_dir.'/'.basename($file_to_delete);
    
    // Vérifier que le fichier existe et est dans le bon répertoire
    if (file_exists($file_path) && strpos(realpath($file_path), realpath($upload_dir)) === 0) {
        if (unlink($file_path)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Impossible de supprimer le fichier']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Fichier non trouvé']);
    }
    
} elseif ($action == 'list' && $transaction_id > 0) {
    
    // Lister les fichiers d'une transaction
    $upload_dir = DOL_DATA_ROOT.'/revenuesharing/transactions/'.$transaction_id;
    $files = array();
    
    if (is_dir($upload_dir)) {
        $handle = opendir($upload_dir);
        while (($file = readdir($handle)) !== false) {
            if ($file != '.' && $file != '..' && is_file($upload_dir.'/'.$file)) {
                $files[] = array(
                    'name' => $file,
                    'size' => formatBytes(filesize($upload_dir.'/'.$file)),
                    'date' => date('d/m/Y H:i', filemtime($upload_dir.'/'.$file))
                );
            }
        }
        closedir($handle);
    }
    
    echo json_encode(['success' => true, 'files' => $files]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Action non supportée']);
}

/**
 * Fonction pour formater la taille des fichiers
 */
// formatBytes function is now in lib/revenuesharing.lib.php

$db->close();
?>