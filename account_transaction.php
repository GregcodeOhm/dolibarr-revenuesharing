<?php
// Fichier: account_transaction.php
// Gestion des opérations de compte (crédit/débit)

// Utilisation de la méthode standard Dolibarr pour l'inclusion
require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

// Parameters
$action = GETPOST('action', 'alpha');
$collaborator_id = GETPOST('collaborator_id', 'int');
$transaction_type = GETPOST('transaction_type', 'alpha');
$amount = GETPOST('amount', 'alpha');
$description = GETPOST('description', 'restricthtml');
$transaction_date = GETPOST('transaction_date', 'alpha');
$note_private = GETPOST('note_private', 'restricthtml');
$fk_facture_fourn = GETPOST('fk_facture_fourn', 'int');
$load_supplier_amount = GETPOST('load_supplier_amount', 'alpha');

// Filtres pour factures fournisseurs
$filter_supplier = GETPOST('filter_supplier', 'int');
$filter_year = GETPOST('filter_year', 'int');
$filter_status = GETPOST('filter_status', 'int');
$filter_ref = GETPOST('filter_ref', 'alpha');

llxHeader('', 'Opération de Compte', '');

print load_fiche_titre(' Nouvelle Opération de Compte', '', 'generic');

// Chargement automatique du montant de la facture fournisseur
$supplier_invoice_info = null;
if ($load_supplier_amount == 'yes' && $fk_facture_fourn > 0) {
    $sql_supplier = "SELECT ref, total_ht, datef, fk_soc FROM ".MAIN_DB_PREFIX."facture_fourn WHERE rowid = ".((int) $fk_facture_fourn);
    $resql_supplier = $db->query($sql_supplier);
    if ($resql_supplier && $db->num_rows($resql_supplier) > 0) {
        $supplier_invoice_info = $db->fetch_object($resql_supplier);
        if (!$amount) {
            $amount = $supplier_invoice_info->total_ht;
        }
        if (!$description && $supplier_invoice_info->ref) {
            $description = 'Facture fournisseur '.$supplier_invoice_info->ref;
        }
        $db->free($resql_supplier);
    }
}

// Debug désactivé en production - Activer uniquement en mode développement
if (getDolGlobalString('MAIN_FEATURES_LEVEL') >= 2 && $action == 'add') {
    print '<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    print '<h4>Debug - Variables reçues :</h4>';
    print '<ul>';
    print '<li><strong>action:</strong> '.dol_escape_htmltag($action ? $action : 'vide').'</li>';
    print '<li><strong>collaborator_id:</strong> '.((int) $collaborator_id ? $collaborator_id : 'vide').'</li>';
    print '<li><strong>transaction_type:</strong> '.dol_escape_htmltag($transaction_type ? $transaction_type : 'vide').'</li>';
    print '<li><strong>amount:</strong> '.dol_escape_htmltag($amount !== '' ? $amount : 'vide').'</li>';
    print '<li><strong>transaction_date:</strong> '.dol_escape_htmltag($transaction_date ? $transaction_date : 'vide').'</li>';
    print '<li><strong>fk_facture_fourn:</strong> '.((int) $fk_facture_fourn ? $fk_facture_fourn : 'vide').'</li>';
    print '</ul>';
    print '</div>';
}

// Traitement de l'ajout d'opération (sauf si c'est juste un filtrage)
if ($action == 'add' && $collaborator_id > 0 && $transaction_type && $amount != '' && $transaction_date) {
    
    // Validation du montant selon le type
    $credit_types = array('commission', 'bonus', 'interest', 'other_credit');
    $debit_types = array('advance', 'fee', 'refund', 'adjustment', 'salary', 'other_debit');
    
    if (in_array($transaction_type, $credit_types) && $amount < 0) {
        $amount = abs($amount); // Forcer positif pour les crédits
    } elseif (in_array($transaction_type, $debit_types) && $amount > 0) {
        $amount = -abs($amount); // Forcer négatif pour les débits
    }
    
    // Insérer la transaction
    $sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_account_transaction (";
    $sql_insert .= "fk_collaborator, fk_facture_fourn, transaction_type, amount, description, transaction_date, ";
    $sql_insert .= "date_creation, fk_user_creat, note_private, status";
    $sql_insert .= ") VALUES (";
    $sql_insert .= $collaborator_id.", ";
    $sql_insert .= ($fk_facture_fourn > 0 ? $fk_facture_fourn : "NULL").", ";
    $sql_insert .= "'".$db->escape($transaction_type)."', ";
    $sql_insert .= $amount.", ";
    $sql_insert .= "'".$db->escape($description)."', ";
    $sql_insert .= "'".$db->idate($transaction_date)."', ";
    $sql_insert .= "NOW(), ";
    $sql_insert .= $user->id.", ";
    $sql_insert .= "'".$db->escape($note_private)."', ";
    $sql_insert .= "1";
    $sql_insert .= ")";
    
    $resql_insert = $db->query($sql_insert);
    if ($resql_insert) {
        // Récupérer le nom du collaborateur
        $sql_collab = "SELECT label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE rowid = ".((int) $collaborator_id);
        $resql_collab = $db->query($sql_collab);
        $collab_name = '';
        if ($resql_collab) {
            $obj_collab = $db->fetch_object($resql_collab);
            $collab_name = $obj_collab->label;
            $db->free($resql_collab);
        }
        
        $type_labels = array(
            'commission' => 'Commission',
            'bonus' => 'Bonus', 
            'interest' => 'Intéressement',
            'advance' => 'Avance',
            'fee' => 'Frais',
            'refund' => 'Remboursement',
            'adjustment' => 'Ajustement',
            'salary' => 'Salaires',
            'other_credit' => 'Autre crédit',
            'other_debit' => 'Autre débit'
        );
        
        print '<div style="background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 20px 0;">';
        print '<h4>Opération enregistrée avec succès !</h4>';
        print '<ul>';
        print '<li><strong>Collaborateur :</strong> '.$collab_name.'</li>';
        print '<li><strong>Type :</strong> '.$type_labels[$transaction_type].'</li>';
        print '<li><strong>Montant :</strong> '.price($amount).'</li>';
        print '<li><strong>Date :</strong> '.dol_print_date($transaction_date, 'day').'</li>';
        print '</ul>';
        print '<div style="margin-top: 15px;">';
        print '<a href="account_detail.php?id='.$collaborator_id.'" class="button"> Voir le compte</a> ';
        print '<a href="account_list.php" class="button">Tous les comptes</a> ';
        print '<a href="'.$_SERVER["PHP_SELF"].'?collaborator_id='.$collaborator_id.'" class="button"> Nouvelle opération</a>';
        print '</div>';
        print '</div>';
        
    } else {
        print '<div style="background: var(--colorbacktabcard1); padding: 15px; border-radius: 5px; color: var(--colortext);">';
        print '<h4>Erreur lors de l\'enregistrement</h4>';
        print '<p>'.$db->lasterror().'</p>';
        print '</div>';
    }
}

// Formulaire d'ajout d'opération
print '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';

// Sélection du collaborateur
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;"> <span style="color: red;">*</span> Collaborateur :</label>';
print '<select name="collaborator_id" id="collaborator_id" required style="width: 100%; padding: 8px; font-size: 1em;">';
print '<option value="">Sélectionner un collaborateur...</option>';

$sql_collabs = "SELECT c.rowid, c.label, u.firstname, u.lastname FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
$sql_collabs .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
$sql_collabs .= " WHERE c.active = 1 ORDER BY c.label";

$resql_collabs = $db->query($sql_collabs);
if ($resql_collabs) {
    while ($obj_collab = $db->fetch_object($resql_collabs)) {
        $selected = ($obj_collab->rowid == $collaborator_id) ? ' selected' : '';
        $display_name = $obj_collab->label;
        if ($obj_collab->firstname && $obj_collab->lastname) {
            $display_name .= ' ('.$obj_collab->firstname.' '.$obj_collab->lastname.')';
        }
        print '<option value="'.$obj_collab->rowid.'"'.$selected.'>'.dol_escape_htmltag($display_name).'</option>';
    }
    $db->free($resql_collabs);
}
print '</select>';
print '</div>';

// Type d'opération
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;"><span style="color: red;">*</span> Type d\'opération :</label>';
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';

print '<div>';
print '<h4 style="color: green; margin: 10px 0;">CRÉDITS (Revenus)</h4>';
print '<div style="display: flex; flex-direction: column; gap: 8px;">';
print '<label><input type="radio" name="transaction_type" value="commission" required'.($transaction_type == 'commission' ? ' checked' : '').'> Commission</label>';
print '<label><input type="radio" name="transaction_type" value="bonus" required'.($transaction_type == 'bonus' ? ' checked' : '').'>  Bonus</label>';
print '<label><input type="radio" name="transaction_type" value="interest" required'.($transaction_type == 'interest' ? ' checked' : '').'> Intéressement</label>';
print '<label><input type="radio" name="transaction_type" value="other_credit" required'.($transaction_type == 'other_credit' ? ' checked' : '').'>  Autre crédit</label>';
print '</div>';
print '</div>';

print '<div>';
print '<h4 style="color: red; margin: 10px 0;">DÉBITS (Charges)</h4>';
print '<div style="display: flex; flex-direction: column; gap: 8px;">';
print '<label><input type="radio" name="transaction_type" value="advance" required'.($transaction_type == 'advance' ? ' checked' : '').'>  Avance</label>';
print '<label><input type="radio" name="transaction_type" value="fee" required'.($transaction_type == 'fee' ? ' checked' : '').'> Frais</label>';
print '<label><input type="radio" name="transaction_type" value="refund" required'.($transaction_type == 'refund' ? ' checked' : '').'> Remboursement</label>';
print '<label><input type="radio" name="transaction_type" value="adjustment" required'.($transaction_type == 'adjustment' ? ' checked' : '').'> Ajustement</label>';
print '<label><input type="radio" name="transaction_type" value="salary" required'.($transaction_type == 'salary' ? ' checked' : '').'> Salaires</label>';
print '<label><input type="radio" name="transaction_type" value="other_debit" required'.($transaction_type == 'other_debit' ? ' checked' : '').'>  Autre débit</label>';
print '</div>';
print '</div>';

print '</div>';
print '</div>';

// Facture fournisseur (pour les débits)
print '<div id="supplier_invoice_section" style="margin-bottom: 20px; display: none;">';

// Section simplifiée pour la liaison facture fournisseur avec bouton modal
print '<div style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 15px 0; color: #007cba;">Liaison avec facture fournisseur</h4>';

print '<div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">';
print '<button type="button" id="selectInvoiceBtn" onclick="openSupplierInvoiceModal()" style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 1em;">';
print 'Sélectionner une facture fournisseur';
print '</button>';
print '<span style="color: var(--colortextbackhmenu); font-style: italic;">Optionnel - Cliquez pour choisir une facture</span>';
print '</div>';

// Zone d'affichage de la facture sélectionnée
print '<div id="selected_invoice_display" style="display: none; background: #e3f2fd; border: 1px solid #007cba; border-radius: 4px; padding: 12px; margin-bottom: 10px;">';
print '<div id="selected_invoice_info"></div>';
print '<button type="button" onclick="clearSelectedInvoice()" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; margin-top: 8px; font-size: 0.9em;">Supprimer la sélection</button>';
print '</div>';

// Input caché pour stocker l'ID de la facture sélectionnée
print '<input type="hidden" name="fk_facture_fourn" id="fk_facture_fourn" value="">';

print '</div>'; // Fermeture de la section liaison facture fournisseur

// Montant
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;"> <span style="color: red;">*</span> Montant :</label>';
print '<input type="number" name="amount" id="amount" step="0.01" required style="width: 200px; padding: 8px; font-size: 1em;" placeholder="0.00" value="'.($amount ? $amount : '').'">';
print '<small style="display: block; color: var(--colortextbackhmenu); margin-top: 5px;">Le signe sera automatiquement ajusté selon le type d\'opération</small>';
print '</div>';

// Description
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;"><span style="color: red;">*</span> Description :</label>';
print '<input type="text" name="description" id="description" required style="width: 100%; padding: 8px; font-size: 1em;" placeholder="Ex: Commission sur projet XYZ, Bonus performance Q4..." value="'.dol_escape_htmltag($description).'">';
print '</div>';

// Date
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;"><span style="color: red;">*</span> Date de l\'opération :</label>';
$date_value = $transaction_date ? dol_print_date($transaction_date, '%Y-%m-%d') : date('Y-m-d');
print '<input type="date" name="transaction_date" required style="padding: 8px; font-size: 1em;" value="'.$date_value.'">';
print '</div>';

// Note privée
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Note privée (optionnelle) :</label>';
print '<textarea name="note_private" rows="3" style="width: 100%; padding: 8px; font-size: 1em;" placeholder="Informations internes...">'.dol_escape_htmltag($note_private).'</textarea>';
print '</div>';

// Boutons
print '<div style="text-align: center; padding-top: 20px; border-top: 1px solid #ddd;">';
print '<input type="submit" value=" Enregistrer l\'opération" class="button" style="background: #28a745; color: white; font-size: 1.1em; padding: 12px 24px;">';
print '<a href="account_list.php" class="button" style="margin-left: 10px;">Annuler</a>';
print '</div>';

print '</form>';
print '</div>';

// JavaScript pour gestion des types de transaction
print '<script>
// Affichage conditionnel du champ facture fournisseur
function toggleSupplierInvoice() {
    var selectedType = "";
    var radios = document.getElementsByName("transaction_type");
    
    for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked) {
            selectedType = radios[i].value;
            break;
        }
    }
    
    var supplierSection = document.getElementById("supplier_invoice_section");
    // Afficher la section pour tous les types de transaction maintenant que c\'est une modal
    if (selectedType) {
        supplierSection.style.display = "block";
    } else {
        supplierSection.style.display = "none";
        // Réinitialiser la sélection de facture si aucun type sélectionné
        if (typeof clearSelectedInvoice === "function") {
            clearSelectedInvoice();
        }
    }
}

// Ajouter les événements
document.addEventListener("DOMContentLoaded", function() {
    // Événements pour les boutons radio
    var radios = document.getElementsByName("transaction_type");
    for (var i = 0; i < radios.length; i++) {
        radios[i].addEventListener("change", toggleSupplierInvoice);
    }
    
    // Vérifier état initial au chargement
    setTimeout(toggleSupplierInvoice, 100);
});
</script>';

print '<div class="tabsAction">';
print '<a href="account_list.php" class="butAction">Comptes</a>';
print '<a href="collaborator_list.php" class="butAction">Collaborateurs</a>';
print '<a href="index.php" class="butAction">Dashboard</a>';
print '</div>';

?>

<!-- Modal de sélection de facture fournisseur -->
<div id="supplierInvoiceModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: white; margin: 2% auto; border-radius: 8px; width: 90%; max-width: 1000px; height: 90%; max-height: 800px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
        
        <!-- En-tête fixe -->
        <div style="padding: 20px 20px 15px 20px; border-bottom: 1px solid #ddd; flex-shrink: 0;">
            <h3 style="margin: 0; color: #007cba;">Sélectionner une facture fournisseur</h3>
        </div>
        
        <!-- Zone de contenu avec scrollbar -->
        <div style="flex: 1; overflow-y: auto; padding: 20px;">
            
            <!-- Filtres -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 15px 0;">Filtres</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;"> Fournisseur:</label>
                        <select id="modal_filter_supplier" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Tous les fournisseurs</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Année:</label>
                        <select id="modal_filter_year" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Toutes les années</option>
                            <?php
                            for ($y = date('Y'); $y >= date('Y') - 3; $y--) {
                                echo '<option value="'.$y.'">'.$y.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Statut:</label>
                        <select id="modal_filter_status" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Tous les statuts</option>
                            <option value="0"> Non payée</option>
                            <option value="1">Payée</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Référence:</label>
                        <input type="text" id="modal_filter_ref" placeholder="REF..." style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    
                    <div>
                        <button type="button" onclick="applyModalFilters()" style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%;">Filtrer</button>
                    </div>
                    
                </div>
            </div>
            
            <!-- Zone de chargement -->
            <div id="modal_loading" style="text-align: center; padding: 40px; display: none;">
                <div style="font-size: 2em; margin-bottom: 10px;"></div>
                <div>Chargement des factures...</div>
            </div>
            
            <!-- Statistiques -->
            <div id="modal_stats" style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin-bottom: 15px; display: none;">
                <div id="modal_stats_content"></div>
            </div>
            
            <!-- Liste des factures -->
            <div id="modal_invoices_container">
                <div id="modal_invoices_list"></div>
                
                <!-- Pagination -->
                <div id="modal_pagination" style="text-align: center; margin-top: 20px; display: none;">
                    <button type="button" id="modal_prev_btn" onclick="changePage(-1)" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin: 0 5px;">← Précédent</button>
                    <span id="modal_page_info" style="margin: 0 15px; font-weight: bold;"></span>
                    <button type="button" id="modal_next_btn" onclick="changePage(1)" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin: 0 5px;">Suivant →</button>
                </div>
            </div>
            
        </div>
        
        <!-- Pied de page fixe avec boutons -->
        <div style="padding: 15px 20px; border-top: 1px solid #ddd; background: #f8f9fa; flex-shrink: 0; text-align: right;">
            <button type="button" onclick="closeSupplierInvoiceModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Fermer</button>
        </div>
    </div>
</div>

<script>
// Variables pour la modal
let currentModalPage = 1;
let modalFilters = {
    supplier: '',
    year: '',
    status: '',
    ref: ''
};

// Fonction pour ouvrir la modal
function openSupplierInvoiceModal() {
    document.getElementById('supplierInvoiceModal').style.display = 'block';
    
    // Charger les fournisseurs pour le filtre
    loadModalSuppliers();
    
    // Charger les factures
    loadModalInvoices();
}

// Fonction pour fermer la modal
function closeSupplierInvoiceModal() {
    document.getElementById('supplierInvoiceModal').style.display = 'none';
}

// Fonction pour charger les fournisseurs dans le filtre modal
function loadModalSuppliers() {
    const select = document.getElementById('modal_filter_supplier');
    select.innerHTML = '<option value="">Tous les fournisseurs</option>';
    
    // Cette fonction utilise l'endpoint AJAX existant pour récupérer les fournisseurs
    fetch('ajax_supplier_invoices.php?load_suppliers_only=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suppliers) {
                data.suppliers.forEach(function(supplier) {
                    const option = document.createElement('option');
                    option.value = supplier.id;
                    option.textContent = supplier.name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des fournisseurs:', error);
        });
}

// Fonction pour appliquer les filtres
function applyModalFilters() {
    modalFilters.supplier = document.getElementById('modal_filter_supplier').value;
    modalFilters.year = document.getElementById('modal_filter_year').value;
    modalFilters.status = document.getElementById('modal_filter_status').value;
    modalFilters.ref = document.getElementById('modal_filter_ref').value;
    
    currentModalPage = 1;
    loadModalInvoices();
}

// Fonction pour charger les factures dans la modal
function loadModalInvoices() {
    const loadingDiv = document.getElementById('modal_loading');
    const listDiv = document.getElementById('modal_invoices_list');
    const statsDiv = document.getElementById('modal_stats');
    const paginationDiv = document.getElementById('modal_pagination');
    
    loadingDiv.style.display = 'block';
    listDiv.innerHTML = '';
    statsDiv.style.display = 'none';
    paginationDiv.style.display = 'none';
    
    // Construire les paramètres
    const params = new URLSearchParams({
        page: currentModalPage,
        limit: 20,
        filter_supplier: modalFilters.supplier,
        filter_year: modalFilters.year,
        filter_status: modalFilters.status,
        filter_ref: modalFilters.ref
    });
    
    fetch('ajax_supplier_invoices.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            loadingDiv.style.display = 'none';
            
            if (data.success) {
                displayModalInvoices(data.invoices);
                displayModalStats(data.pagination);
                displayModalPagination(data.pagination);
            } else {
                listDiv.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Erreur: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            loadingDiv.style.display = 'none';
            listDiv.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Erreur de communication</div>';
            console.error('Erreur:', error);
        });
}

// Fonction pour afficher les factures
function displayModalInvoices(invoices) {
    const listDiv = document.getElementById('modal_invoices_list');
    
    if (invoices.length === 0) {
        listDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--colortextbackhmenu);"><div style="font-size: 3em;"></div><h3>Aucune facture trouvée</h3><p>Essayez de modifier les filtres ci-dessus</p></div>';
        return;
    }
    
    let html = '<div style="display: grid; gap: 10px;">';
    
    invoices.forEach(function(invoice) {
        const isAssociated = invoice.is_associated == '1';
        const statusIcon = invoice.paye == '1' ? '' : '';
        const lockIcon = isAssociated ? '' : '';
        
        html += '<div style="border: 1px solid ' + (isAssociated ? '#ddd' : '#007cba') + '; border-radius: 8px; padding: 15px; background: ' + (isAssociated ? '#f8f9fa' : 'white') + '; ' + (isAssociated ? 'opacity: 0.6;' : '') + '">';
        
        html += '<div style="display: flex; justify-content: space-between; align-items: start;">';
        
        // Informations de la facture
        html += '<div style="flex: 1;">';
        html += '<div style="font-weight: bold; color: #007cba; font-size: 1.1em;">' + lockIcon + ' ' + statusIcon + ' ' + escapeHtml(invoice.ref) + '</div>';
        html += '<div style="margin: 5px 0; color: var(--colortextbackhmenu);">' + escapeHtml(invoice.supplier_name) + '</div>';
        if (invoice.libelle) {
            html += '<div style="font-style: italic; color: var(--colortextbackhmenu); margin: 5px 0;">' + escapeHtml(invoice.libelle) + '</div>';
        }
        html += '<div style="margin: 8px 0;"><strong>' + parseFloat(invoice.total_ht).toFixed(2) + ' €</strong> - ' + invoice.datef_formatted + '</div>';
        
        if (isAssociated && invoice.associated_collaborator_name) {
            html += '<div style="color: #fd7e14; font-size: 0.9em; margin-top: 5px;">Déjà associée à: ' + escapeHtml(invoice.associated_collaborator_name) + '</div>';
        }
        html += '</div>';
        
        // Bouton de sélection
        html += '<div style="margin-left: 15px;">';
        if (!isAssociated) {
            html += '<button type="button" onclick="selectInvoice(' + invoice.id + ', \'' + escapeForJs(invoice.ref) + '\', ' + invoice.total_ht + ', \'' + escapeForJs(invoice.datef_iso) + '\', \'' + escapeForJs(invoice.libelle || '') + '\', \'' + escapeForJs(invoice.supplier_name) + '\')" ';
            html += 'style="background: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">✓ Sélectionner</button>';
        } else {
            html += '<div style="color: #999; font-style: italic; padding: 10px 15px;">Non disponible</div>';
        }
        html += '</div>';
        
        html += '</div>';
        html += '</div>';
    });
    
    html += '</div>';
    listDiv.innerHTML = html;
}

// Fonction pour afficher les statistiques
function displayModalStats(pagination) {
    const statsDiv = document.getElementById('modal_stats');
    const statsContent = document.getElementById('modal_stats_content');
    
    let html = '<strong>' + pagination.total + ' facture(s) trouvée(s)</strong>';
    if (pagination.available_invoices !== undefined) {
        html += ' - <strong style="color: #28a745;">' + pagination.available_invoices + ' disponible(s)</strong>';
        if (pagination.available_invoices != pagination.total) {
            const associated = pagination.total - pagination.available_invoices;
            html += ' - <span style="color: #fd7e14;">' + associated + ' déjà associée(s)</span>';
        }
    }
    
    statsContent.innerHTML = html;
    statsDiv.style.display = 'block';
}

// Fonction pour afficher la pagination
function displayModalPagination(pagination) {
    const paginationDiv = document.getElementById('modal_pagination');
    const pageInfo = document.getElementById('modal_page_info');
    const prevBtn = document.getElementById('modal_prev_btn');
    const nextBtn = document.getElementById('modal_next_btn');
    
    if (pagination.total_pages > 1) {
        pageInfo.textContent = 'Page ' + pagination.page + ' sur ' + pagination.total_pages;
        prevBtn.disabled = pagination.page <= 1;
        nextBtn.disabled = pagination.page >= pagination.total_pages;
        
        prevBtn.style.opacity = prevBtn.disabled ? '0.5' : '1';
        nextBtn.style.opacity = nextBtn.disabled ? '0.5' : '1';
        
        paginationDiv.style.display = 'block';
    } else {
        paginationDiv.style.display = 'none';
    }
}

// Fonction pour changer de page
function changePage(delta) {
    const newPage = currentModalPage + delta;
    if (newPage >= 1) {
        currentModalPage = newPage;
        loadModalInvoices();
    }
}

// Fonction pour sélectionner une facture
function selectInvoice(id, ref, amount, date, libelle, supplierName) {
    // Mettre à jour le champ caché
    document.getElementById('fk_facture_fourn').value = id;
    
    // Mettre à jour l'affichage
    const displayDiv = document.getElementById('selected_invoice_display');
    const infoDiv = document.getElementById('selected_invoice_info');
    
    let html = '<div style="display: flex; justify-content: space-between; align-items: start;">';
    html += '<div>';
    html += '<div style="font-weight: bold; color: #007cba; font-size: 1.1em;"> ' + escapeHtml(ref) + '</div>';
    html += '<div style="margin: 5px 0; color: var(--colortextbackhmenu);">' + escapeHtml(supplierName) + '</div>';
    if (libelle) {
        html += '<div style="font-style: italic; color: var(--colortextbackhmenu); margin: 5px 0;">' + escapeHtml(libelle) + '</div>';
    }
    html += '<div style="margin-top: 8px;"><strong>' + parseFloat(amount).toFixed(2) + ' €</strong></div>';
    html += '</div>';
    html += '</div>';
    
    infoDiv.innerHTML = html;
    displayDiv.style.display = 'block';
    
    // Pré-remplir les champs du formulaire
    document.getElementById('amount').value = parseFloat(amount).toFixed(2);
    document.getElementById('description').value = 'Facture fournisseur ' + ref;
    
    // Remplir la date si possible
    if (date && document.getElementsByName('transaction_date')[0]) {
        document.getElementsByName('transaction_date')[0].value = date;
    }
    
    // Fermer la modal
    closeSupplierInvoiceModal();
}

// Fonction pour supprimer la sélection
function clearSelectedInvoice() {
    document.getElementById('fk_facture_fourn').value = '';
    document.getElementById('selected_invoice_display').style.display = 'none';
    
    // Optionnel: vider les champs pré-remplis
    // document.getElementById('amount').value = '';
    // document.getElementById('description').value = '';
}

// Fonctions utilitaires
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function escapeForJs(text) {
    return (text || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// Fermer la modal en cliquant en dehors
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('supplierInvoiceModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeSupplierInvoiceModal();
            }
        });
    }
    
    // Fermer avec Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSupplierInvoiceModal();
        }
    });
});
</script>

<?php
llxFooter();
$db->close();
?>