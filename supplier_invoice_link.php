<?php
// Fichier: supplier_invoice_link.php
// Gestion des liaisons entre transactions et factures fournisseurs

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/lib/revenuesharing.lib.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$transaction_id = GETPOST('transaction_id', 'int');
$supplier_invoice_id = GETPOST('supplier_invoice_id', 'int');

// Response for AJAX calls
header('Content-Type: application/json');

if ($action == 'get_current_invoice' && $transaction_id > 0) {
    
    // Récupérer la facture fournisseur actuellement liée
    $sql = "SELECT t.fk_facture_fourn, ff.ref, ff.libelle, ff.total_ht, ff.datef, s.nom as supplier_name
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
            LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = ff.fk_soc
            WHERE t.rowid = ".((int) $transaction_id)." AND t.status = 1";
    
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        
        if ($obj->fk_facture_fourn) {
            echo json_encode([
                'success' => true,
                'has_invoice' => true,
                'invoice' => [
                    'id' => $obj->fk_facture_fourn,
                    'ref' => $obj->ref,
                    'libelle' => $obj->libelle,
                    'total_ht' => $obj->total_ht,
                    'date' => $obj->datef,
                    'supplier_name' => $obj->supplier_name
                ]
            ]);
        } else {
            echo json_encode(['success' => true, 'has_invoice' => false]);
        }
        $db->free($resql);
    } else {
        echo json_encode(['success' => false, 'error' => 'Transaction non trouvée']);
    }
    
} elseif ($action == 'get_available_invoices') {
    
    // Récupérer les factures fournisseurs disponibles (dernières 100)
    $sql = "SELECT ff.rowid, ff.ref, ff.libelle, ff.total_ht, ff.datef, s.nom as supplier_name, ff.paye
            FROM ".MAIN_DB_PREFIX."facture_fourn ff
            LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = ff.fk_soc
            WHERE ff.fk_statut >= 1
            ORDER BY ff.datef DESC, ff.rowid DESC
            LIMIT 100";
    
    $resql = $db->query($sql);
    $invoices = array();
    
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $invoices[] = array(
                'id' => $obj->rowid,
                'ref' => $obj->ref,
                'libelle' => $obj->libelle,
                'total_ht' => $obj->total_ht,
                'date' => $obj->datef,
                'supplier_name' => $obj->supplier_name,
                'paye' => $obj->paye
            );
        }
        $db->free($resql);
    }
    
    echo json_encode(['success' => true, 'invoices' => $invoices]);
    
} elseif ($action == 'link_invoice' && $transaction_id > 0 && $supplier_invoice_id > 0) {
    
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
    
    // Vérifier que la facture fournisseur existe
    $sql_invoice = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn 
                    WHERE rowid = ".((int) $supplier_invoice_id);
    $resql_invoice = $db->query($sql_invoice);
    if (!$resql_invoice || $db->num_rows($resql_invoice) == 0) {
        echo json_encode(['success' => false, 'error' => 'Facture fournisseur non trouvée']);
        exit;
    }
    $db->free($resql_invoice);
    
    // Mettre à jour la liaison
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                   SET fk_facture_fourn = ".((int) $supplier_invoice_id)."
                   WHERE rowid = ".((int) $transaction_id);
    
    $resql_update = $db->query($sql_update);
    if ($resql_update) {
        echo json_encode(['success' => true, 'message' => 'Liaison créée avec succès']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour: '.$db->lasterror()]);
    }
    
} elseif ($action == 'unlink_invoice' && $transaction_id > 0) {
    
    // Vérification du token CSRF
    if (!newToken('check')) {
        echo json_encode(['success' => false, 'error' => 'Token de sécurité invalide']);
        exit;
    }
    
    // Supprimer la liaison
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction 
                   SET fk_facture_fourn = NULL
                   WHERE rowid = ".((int) $transaction_id)." AND status = 1";
    
    $resql_update = $db->query($sql_update);
    if ($resql_update) {
        echo json_encode(['success' => true, 'message' => 'Liaison supprimée avec succès']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression: '.$db->lasterror()]);
    }
    
} elseif ($action == 'get_invoice_documents' && $supplier_invoice_id > 0) {
    
    // Récupérer les documents liés à la facture fournisseur
    $upload_dir = $conf->fournisseur->facture->dir_output.'/'.$supplier_invoice_id;
    $documents = array();
    
    if (is_dir($upload_dir)) {
        $handle = opendir($upload_dir);
        while (($file = readdir($handle)) !== false) {
            if ($file != '.' && $file != '..' && is_file($upload_dir.'/'.$file)) {
                // Ignorer les fichiers temporaires et de métadonnées
                if (!preg_match('/\.(tmp|meta)$/i', $file)) {
                    $documents[] = array(
                        'name' => $file,
                        'size' => formatBytes(filesize($upload_dir.'/'.$file)),
                        'date' => date('d/m/Y H:i', filemtime($upload_dir.'/'.$file)),
                        'url' => DOL_URL_ROOT.'/document.php?modulesubdir=fournisseur/facture/'.$supplier_invoice_id.'&file='.urlencode($file)
                    );
                }
            }
        }
        closedir($handle);
    }
    
    echo json_encode(['success' => true, 'documents' => $documents]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Action non supportée ou paramètres manquants']);
}

/**
 * Fonction pour formater la taille des fichiers
 */
// formatBytes function is now in lib/revenuesharing.lib.php

$db->close();
?>