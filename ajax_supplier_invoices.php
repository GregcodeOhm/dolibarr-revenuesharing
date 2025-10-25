<?php
// Fichier: ajax_supplier_invoices.php
// Endpoint AJAX pour charger les factures fournisseurs

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Accès refusé'));
    exit;
}

// Force JSON response
header('Content-Type: application/json');

// Parameters de filtrage
$filter_supplier = GETPOST('filter_supplier', 'int');
$filter_year = GETPOST('filter_year', 'int');
$filter_status = GETPOST('filter_status', 'int');
$filter_ref = GETPOST('filter_ref', 'alpha');
$page = GETPOST('page', 'int') ?: 1;
$limit = GETPOST('limit', 'int') ?: 50;
$load_suppliers_only = GETPOST('load_suppliers_only', 'int');

try {
    // Si on veut seulement les fournisseurs
    if ($load_suppliers_only) {
        $suppliers = array();
        $sql_fournisseurs = "SELECT s.rowid, s.nom FROM ".MAIN_DB_PREFIX."societe s";
        $sql_fournisseurs .= " WHERE s.fournisseur = 1";
        $sql_fournisseurs .= " AND (s.status = 1 OR s.status IS NULL)";
        $sql_fournisseurs .= " ORDER BY s.nom";
        
        $resql_fournisseurs = $db->query($sql_fournisseurs);
        if ($resql_fournisseurs) {
            while ($fournisseur = $db->fetch_object($resql_fournisseurs)) {
                $suppliers[] = array(
                    'id' => $fournisseur->rowid,
                    'name' => $fournisseur->nom ?: 'Fournisseur #'.$fournisseur->rowid
                );
            }
            $db->free($resql_fournisseurs);
        }
        
        echo json_encode(array(
            'success' => true,
            'suppliers' => $suppliers
        ));
        exit;
    }
    
    // Récupérer les factures fournisseurs avec filtres et exclusion de celles déjà associées
    $sql_suppliers = "SELECT ff.rowid, ff.ref, ff.libelle, ff.total_ht, ff.datef, ff.paye, s.nom as supplier_name,";
    $sql_suppliers .= " (CASE WHEN at.fk_facture_fourn IS NOT NULL THEN 1 ELSE 0 END) as is_associated,";
    $sql_suppliers .= " at.fk_collaborator as associated_collaborator_id,";
    $sql_suppliers .= " c.label as associated_collaborator_name";
    $sql_suppliers .= " FROM ".MAIN_DB_PREFIX."facture_fourn ff";
    $sql_suppliers .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = ff.fk_soc";
    $sql_suppliers .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_transaction at ON at.fk_facture_fourn = ff.rowid";
    $sql_suppliers .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = at.fk_collaborator";
    $sql_suppliers .= " WHERE ff.entity IN (".getEntity('supplier_invoice').")";

    // Application des filtres
    if ($filter_supplier > 0) {
        $sql_suppliers .= " AND ff.fk_soc = ".((int) $filter_supplier);
    }
    if ($filter_year > 0) {
        $sql_suppliers .= " AND YEAR(ff.datef) = ".((int) $filter_year);
    }
    if ($filter_status >= 0 && $filter_status !== '') {
        $sql_suppliers .= " AND ff.paye = ".((int) $filter_status);
    }
    if ($filter_ref) {
        $sql_suppliers .= " AND ff.ref LIKE '%".$db->escape($filter_ref)."%'";
    }

    // Compter le total
    $sql_count = str_replace("SELECT ff.rowid, ff.ref, ff.libelle, ff.total_ht, ff.datef, ff.paye, s.nom as supplier_name, (CASE WHEN at.fk_facture_fourn IS NOT NULL THEN 1 ELSE 0 END) as is_associated, at.fk_collaborator as associated_collaborator_id, c.label as associated_collaborator_name", "SELECT COUNT(*) as total", $sql_suppliers);
    $resql_count = $db->query($sql_count);
    $total_invoices = 0;
    if ($resql_count) {
        $obj_count = $db->fetch_object($resql_count);
        $total_invoices = $obj_count->total;
        $db->free($resql_count);
    }

    // Pagination
    $offset = ($page - 1) * $limit;
    $sql_suppliers .= " ORDER BY ff.datef DESC LIMIT ".$limit." OFFSET ".$offset;

    $resql_suppliers = $db->query($sql_suppliers);
    
    if (!$resql_suppliers) {
        throw new Exception($db->lasterror());
    }

    $invoices = array();
    $nb_available_invoices = 0;
    
    while ($supplier_invoice = $db->fetch_object($resql_suppliers)) {
        $invoice_data = array(
            'id' => $supplier_invoice->rowid,
            'ref' => $supplier_invoice->ref,
            'libelle' => $supplier_invoice->libelle ?: '',
            'total_ht' => floatval($supplier_invoice->total_ht),
            'datef' => $supplier_invoice->datef,
            'datef_formatted' => dol_print_date($db->jdate($supplier_invoice->datef), 'day'),
            'datef_iso' => dol_print_date($db->jdate($supplier_invoice->datef), '%Y-%m-%d'),
            'paye' => $supplier_invoice->paye,
            'supplier_name' => $supplier_invoice->supplier_name ?: 'N/A',
            'is_associated' => $supplier_invoice->is_associated,
            'associated_collaborator_name' => $supplier_invoice->associated_collaborator_name ?: ''
        );
        
        if (!$supplier_invoice->is_associated) {
            $nb_available_invoices++;
        }
        
        $invoices[] = $invoice_data;
    }
    
    $db->free($resql_suppliers);

    // Récupérer la liste des fournisseurs pour les filtres
    $suppliers = array();
    $sql_fournisseurs = "SELECT s.rowid, s.nom FROM ".MAIN_DB_PREFIX."societe s";
    $sql_fournisseurs .= " WHERE s.fournisseur = 1";
    $sql_fournisseurs .= " AND (s.status = 1 OR s.status IS NULL)";
    $sql_fournisseurs .= " ORDER BY s.nom";
    
    $resql_fournisseurs = $db->query($sql_fournisseurs);
    if ($resql_fournisseurs) {
        while ($fournisseur = $db->fetch_object($resql_fournisseurs)) {
            $suppliers[] = array(
                'id' => $fournisseur->rowid,
                'name' => $fournisseur->nom ?: 'Fournisseur #'.$fournisseur->rowid
            );
        }
        $db->free($resql_fournisseurs);
    }

    // Réponse JSON
    echo json_encode(array(
        'success' => true,
        'invoices' => $invoices,
        'suppliers' => $suppliers,
        'pagination' => array(
            'page' => $page,
            'limit' => $limit,
            'total' => $total_invoices,
            'total_pages' => ceil($total_invoices / $limit),
            'available_invoices' => $nb_available_invoices
        ),
        'filters' => array(
            'filter_supplier' => $filter_supplier,
            'filter_year' => $filter_year,
            'filter_status' => $filter_status,
            'filter_ref' => $filter_ref
        )
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}

$db->close();
?>