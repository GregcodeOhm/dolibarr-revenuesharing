/**
 * account_detail.js
 * JavaScript pour la gestion du compte collaborateur
 * Module Revenue Sharing - Dolibarr
 */

// Variables globales - seront initialisées depuis PHP
let typeLabels = {};
let currentTransactionId = null;
let currentContractId = null;
let collaboratorId = null;
let csrfToken = '';

/**
 * Initialisation des variables globales depuis PHP
 */
function initAccountDetail(config) {
    typeLabels = config.typeLabels || {};
    collaboratorId = config.collaboratorId || null;
    csrfToken = config.csrfToken || '';
}

// ========== GESTION DES MODALES ==========

/**
 * Ouvre la modale d'édition de transaction
 */
function openEditModal(transactionId, amount, description, note, type) {
    currentTransactionId = transactionId;

    document.getElementById('edit_transaction_id').value = transactionId;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_note_private').value = note || '';
    document.getElementById('edit_transaction_type').value = type;

    // Charger le libellé de la transaction
    loadTransactionLabel(transactionId);

    // Déterminer quelles sections afficher selon le type
    toggleTransactionSectionsInModal(type);

    // Charger les informations selon le type de transaction
    if (isCreditType(type)) {
        loadCurrentContract(transactionId);
        loadAvailableContracts();
    } else if (isDebitType(type)) {
        loadCurrentSupplierInvoice(transactionId);
        loadAvailableSupplierInvoices();
    }

    document.getElementById('editTransactionModal').style.display = 'block';
}

/**
 * Ferme la modale d'édition de transaction
 */
function closeEditModal() {
    document.getElementById('editTransactionModal').style.display = 'none';
}

/**
 * Supprime la transaction depuis la modale
 */
function deleteTransactionFromModal() {
    if (!currentTransactionId) {
        alert('Erreur: ID de transaction non trouvé');
        return;
    }

    if (confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?\n\nCette action ne peut pas être annulée.')) {
        closeEditModal();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'edit_transaction.php';

        const fields = {
            'action': 'delete',
            'transaction_id': currentTransactionId,
            'collaborator_id': collaboratorId,
            'token': csrfToken
        };

        for (const key in fields) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
}

/**
 * Confirme la suppression d'une transaction (depuis le tableau)
 */
function confirmDeleteTransaction(transactionId, collabId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?\n\nCette action ne peut pas être annulée.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'edit_transaction.php';

        const fields = {
            'action': 'delete',
            'transaction_id': transactionId,
            'collaborator_id': collabId,
            'token': csrfToken
        };

        for (const key in fields) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
}

// ========== TYPES DE TRANSACTIONS ==========

/**
 * Vérifie si un type est un débit
 */
function isDebitType(type) {
    const debitTypes = ['advance', 'fee', 'refund', 'adjustment', 'salary', 'other_debit'];
    return debitTypes.includes(type);
}

/**
 * Vérifie si un type est un crédit
 */
function isCreditType(type) {
    const creditTypes = ['contract', 'commission', 'bonus', 'interest', 'other_credit'];
    return creditTypes.includes(type);
}

/**
 * Affiche/masque les sections selon le type dans la modale
 */
function toggleTransactionSectionsInModal(type) {
    const contractSection = document.getElementById('contract_section_modal');
    const supplierSection = document.getElementById('supplier_invoice_section_modal');

    if (isCreditType(type)) {
        contractSection.style.display = 'block';
        supplierSection.style.display = 'none';
    } else if (isDebitType(type)) {
        contractSection.style.display = 'none';
        supplierSection.style.display = 'block';
    } else {
        contractSection.style.display = 'none';
        supplierSection.style.display = 'none';
    }
}

// ========== CHARGEMENT DES DONNÉES ==========

/**
 * Charge le libellé d'une transaction
 */
function loadTransactionLabel(transactionId) {
    fetch('get_transaction_info.php?transaction_id=' + transactionId)
        .then(response => response.json())
        .then(data => {
            const labelDiv = document.getElementById('edit_label_display');
            const actionsDiv = document.getElementById('edit_label_actions');

            if (data.success && data.label) {
                let labelHtml = '';
                let linkUrl = '';

                if (data.label_type === 'contract') {
                    labelHtml = '<span style="color: #007cba; font-weight: 500;">' + data.label + '</span>';
                    linkUrl = 'contract_card_complete.php?id=' + data.contract_id;
                } else if (data.label_type === 'supplier_invoice') {
                    labelHtml = '<span style="color: #fd7e14; font-weight: 500;"> ' + data.label + '</span>';
                    linkUrl = 'contract_card_complete.php';
                } else if (data.label_type === 'customer_invoice') {
                    labelHtml = '<span style="color: #28a745; font-weight: 500;"> ' + data.label + '</span>';
                    linkUrl = 'contract_card_complete.php';
                }

                labelDiv.innerHTML = labelHtml +
                    '<div style="float: right; display: flex; gap: 5px;">' +
                    '<button type="button" onclick="openContractEditModal(' + data.contract_id + ')" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 0.8em;" title="Modifier dans une fenêtre">Modal</button>' +
                    '<a href="' + linkUrl + '" target="_blank" style="background: #007cba; color: white; text-decoration: none; padding: 4px 8px; border-radius: 3px; font-size: 0.8em;" title="Modifier via contract_card_complete.php"> Page</a>' +
                    '</div>';
                labelDiv.style.fontStyle = 'normal';
                labelDiv.style.color = '#333';
                labelDiv.style.cursor = 'default';

                actionsDiv.style.display = 'block';
                actionsDiv.innerHTML = '<small style="color: #007cba;"><em>Utilisez "Modal" pour éditer rapidement ou "Page" pour ouvrir contract_card_complete.php</em></small>';

            } else {
                labelDiv.innerHTML = 'Aucun libellé (transaction non liée)';
                labelDiv.style.fontStyle = 'italic';
                labelDiv.style.color = '#666';
                labelDiv.style.cursor = 'default';
                actionsDiv.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement du libellé:', error);
            document.getElementById('edit_label_display').innerHTML = 'Erreur de chargement';
            document.getElementById('edit_label_actions').style.display = 'none';
        });
}

// ========== GESTION DES FACTURES FOURNISSEURS ==========

/**
 * Charge la facture fournisseur actuelle
 */
function loadCurrentSupplierInvoice(transactionId) {
    const currentInvoiceDiv = document.getElementById('current_invoice_info');
    currentInvoiceDiv.innerHTML = '<div style="color: #666; font-style: italic;">Chargement...</div>';

    fetch('supplier_invoice_link.php?action=get_current_invoice&transaction_id=' + transactionId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCurrentInvoice(data);
            } else {
                currentInvoiceDiv.innerHTML = '<div style="color: red;">Erreur: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            currentInvoiceDiv.innerHTML = '<div style="color: red;">Erreur de communication</div>';
        });
}

/**
 * Affiche la facture fournisseur actuelle
 */
function displayCurrentInvoice(data) {
    const currentInvoiceDiv = document.getElementById('current_invoice_info');

    if (!data.has_invoice) {
        currentInvoiceDiv.innerHTML = '<div style="color: #666; font-style: italic; padding: 10px; background: #f8f9fa; border-radius: 4px;">Aucune facture fournisseur liée</div>';
        document.getElementById('invoice_documents_list').innerHTML = '';
        return;
    }

    const invoice = data.invoice;
    let html = '<div style="border: 1px solid #007cba; border-radius: 4px; padding: 12px; background: #f0f8ff;">';
    html += '<div style="display: flex; justify-content: space-between; align-items: start;">';
    html += '<div>';
    html += '<div style="font-weight: bold; color: #007cba;"> ' + escapeHtml(invoice.ref) + '</div>';
    html += '<div style="margin: 5px 0;">' + escapeHtml(invoice.supplier_name) + '</div>';
    if (invoice.libelle) {
        html += '<div style="font-style: italic; color: #666;">' + escapeHtml(invoice.libelle) + '</div>';
    }
    html += '<div style="margin-top: 5px;"><strong>' + parseFloat(invoice.total_ht).toFixed(2) + ' €</strong> - ' + formatDate(invoice.date) + '</div>';
    html += '</div>';
    html += '<button type="button" onclick="unlinkSupplierInvoice()" style="background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 3px; cursor: pointer;" title="Délier cette facture"></button>';
    html += '</div>';
    html += '</div>';

    currentInvoiceDiv.innerHTML = html;

    loadInvoiceDocuments(invoice.id);
}

/**
 * Charge les factures fournisseurs disponibles
 */
function loadAvailableSupplierInvoices() {
    const select = document.getElementById('supplier_invoice_select');

    fetch('supplier_invoice_link.php?action=get_available_invoices')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateInvoiceSelect(data.invoices);
            } else {
                console.error('Erreur lors du chargement des factures:', data.error);
            }
        })
        .catch(error => {
            console.error('Erreur de communication:', error);
        });
}

/**
 * Remplit le select des factures
 */
function populateInvoiceSelect(invoices) {
    const select = document.getElementById('supplier_invoice_select');
    select.innerHTML = '<option value="">Sélectionner une facture...</option>';

    invoices.forEach(function(invoice) {
        const option = document.createElement('option');
        option.value = invoice.id;

        const statusIcon = invoice.paye ? '' : '';
        const libelle = invoice.libelle ? ' - ' + invoice.libelle.substring(0, 30) : '';
        option.textContent = statusIcon + ' ' + invoice.ref + ' (' + invoice.supplier_name + ')' + libelle + ' - ' + parseFloat(invoice.total_ht).toFixed(2) + '€';

        select.appendChild(option);
    });
}

/**
 * Lie une facture fournisseur
 */
function linkSupplierInvoice() {
    const select = document.getElementById('supplier_invoice_select');
    const supplierInvoiceId = select.value;

    if (!supplierInvoiceId) {
        showInvoiceMessage('Veuillez sélectionner une facture', 'error');
        return;
    }

    if (!currentTransactionId) {
        showInvoiceMessage('Erreur: ID de transaction manquant', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'link_invoice');
    formData.append('transaction_id', currentTransactionId);
    formData.append('supplier_invoice_id', supplierInvoiceId);
    formData.append('token', csrfToken);

    showInvoiceMessage('Liaison en cours...', 'info');

    fetch('supplier_invoice_link.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showInvoiceMessage(data.message, 'success');
            select.value = '';
            loadCurrentSupplierInvoice(currentTransactionId);
        } else {
            showInvoiceMessage('Erreur: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showInvoiceMessage('Erreur de communication', 'error');
    });
}

/**
 * Délie une facture fournisseur
 */
function unlinkSupplierInvoice() {
    if (!confirm('Êtes-vous sûr de vouloir délier cette facture fournisseur de la transaction ?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'unlink_invoice');
    formData.append('transaction_id', currentTransactionId);
    formData.append('token', csrfToken);

    fetch('supplier_invoice_link.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showInvoiceMessage(data.message + ' - Vous pouvez maintenant fermer cette fenêtre.', 'success');
            loadCurrentSupplierInvoice(currentTransactionId);

            const closeBtn = document.getElementById('modal_close_btn');
            if (closeBtn) {
                closeBtn.style.background = '#28a745';
                closeBtn.style.animation = 'pulse 2s infinite';
                closeBtn.innerHTML = 'Fermer (Liaison supprimée)';
            }

            setTimeout(function() {
                if (confirm('La liaison a été supprimée avec succès. Voulez-vous rafraîchir la page pour voir les modifications dans le tableau ?')) {
                    window.location.reload();
                }
            }, 3000);
        } else {
            showInvoiceMessage('Erreur: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showInvoiceMessage('Erreur de communication', 'error');
    });
}

/**
 * Charge les documents d'une facture
 */
function loadInvoiceDocuments(invoiceId) {
    const documentsDiv = document.getElementById('invoice_documents_list');
    documentsDiv.innerHTML = '<div style="color: #666; font-style: italic;">Chargement des documents...</div>';

    fetch('supplier_invoice_link.php?action=get_invoice_documents&supplier_invoice_id=' + invoiceId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayInvoiceDocuments(data.documents);
            } else {
                documentsDiv.innerHTML = '<div style="color: red;">Erreur: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            documentsDiv.innerHTML = '<div style="color: red;">Erreur de communication</div>';
        });
}

/**
 * Affiche les documents de la facture
 */
function displayInvoiceDocuments(documents) {
    const documentsDiv = document.getElementById('invoice_documents_list');

    if (documents.length === 0) {
        documentsDiv.innerHTML = '<div style="color: #666; font-style: italic; padding: 8px;">Aucun document attaché à cette facture</div>';
        return;
    }

    let html = '<div style="margin-top: 10px;"><h5 style="margin: 0 0 8px 0; color: #007cba;">📎 Documents de la facture:</h5>';
    html += '<div style="border: 1px solid #ddd; border-radius: 4px; max-height: 120px; overflow-y: auto;">';

    documents.forEach(function(doc) {
        const fileIcon = getFileIcon(doc.name);
        html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 8px; border-bottom: 1px solid #eee;">';
        html += '<div style="display: flex; align-items: center; gap: 6px;">';
        html += '<span style="font-size: 1.1em;">' + fileIcon + '</span>';
        html += '<div>';
        html += '<div style="font-weight: bold; font-size: 0.9em;">' + escapeHtml(doc.name) + '</div>';
        html += '<small style="color: #666;">' + doc.size + ' - ' + doc.date + '</small>';
        html += '</div>';
        html += '</div>';
        html += '<a href="' + doc.url + '" target="_blank" style="background: #007cba; color: white; text-decoration: none; padding: 4px 8px; border-radius: 3px; font-size: 0.8em;" title="Voir le document"></a>';
        html += '</div>';
    });

    html += '</div></div>';
    documentsDiv.innerHTML = html;
}

/**
 * Obtient l'icône selon le type de fichier
 */
function getFileIcon(filename) {
    const extension = filename.split('.').pop().toLowerCase();

    switch (extension) {
        case 'pdf': return '';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif': return '🖼️';
        case 'doc':
        case 'docx': return '';
        case 'xls':
        case 'xlsx': return '';
        case 'txt': return '';
        default: return '📎';
    }
}

/**
 * Affiche un message pour la section facture
 */
function showInvoiceMessage(message, type) {
    const messagesDiv = document.getElementById('invoice_messages');

    let bgColor, textColor;
    switch (type) {
        case 'success':
            bgColor = '#d4edda';
            textColor = '#155724';
            break;
        case 'error':
            bgColor = '#f8d7da';
            textColor = '#721c24';
            break;
        case 'info':
            bgColor = '#d1ecf1';
            textColor = '#0c5460';
            break;
        default:
            bgColor = '#f8f9fa';
            textColor = '#495057';
    }

    messagesDiv.innerHTML = '<div style="background: ' + bgColor + '; color: ' + textColor + '; padding: 8px; border-radius: 4px;">' + escapeHtml(message) + '</div>';

    if (type === 'success' || type === 'info') {
        setTimeout(() => {
            messagesDiv.innerHTML = '';
        }, 5000);
    }
}

// ========== GESTION DES CONTRATS ==========

/**
 * Charge le contrat actuellement lié
 */
function loadCurrentContract(transactionId) {
    fetch('get_transaction_info.php?transaction_id=' + transactionId)
        .then(response => response.json())
        .then(data => {
            const contractInfoDiv = document.getElementById('current_contract_info');

            if (data.success && data.label_type === 'contract' && data.contract_id) {
                currentContractId = data.contract_id;

                let html = '<div style="background: #e3f2fd; border: 1px solid #007cba; border-radius: 4px; padding: 10px;">';
                html += '<strong>Contrat lié:</strong> ' + escapeHtml(data.label);

                if (data.facture_info) {
                    html += '<br><strong> Facture client:</strong> ' + escapeHtml(data.facture_info.ref);
                    html += ' - ' + escapeHtml(data.facture_info.client);
                    html += ' (' + parseFloat(data.facture_info.amount).toFixed(2) + ' €)';
                }

                html += '</div>';
                contractInfoDiv.innerHTML = html;
            } else {
                currentContractId = null;
                contractInfoDiv.innerHTML = '<div style="color: #666; font-style: italic;">Aucun contrat lié à cette transaction</div>';
            }
        })
        .catch(error => {
            document.getElementById('current_contract_info').innerHTML = '<div style="color: red;">Erreur lors du chargement du contrat</div>';
            console.error('Erreur:', error);
        });
}

/**
 * Charge la liste des contrats disponibles
 */
function loadAvailableContracts() {
    const select = document.getElementById('contract_select');
    select.innerHTML = '<option value="">Sélectionner un contrat...</option>';

    fetch('get_available_contracts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.contracts) {
                data.contracts.forEach(function(contract) {
                    const option = document.createElement('option');
                    option.value = contract.id;
                    option.textContent = contract.label + ' (' + parseFloat(contract.amount_ht).toFixed(2) + ' € - ' + contract.collaborator + ')';
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des contrats:', error);
        });
}

/**
 * Obtient l'ID du contrat depuis la transaction courante
 */
function getContractIdFromTransaction() {
    return currentContractId;
}

/**
 * Édite le contrat avec fallback
 */
function editContractWithFallback() {
    const contractId = getContractIdFromTransaction();

    if (!contractId) {
        showContractTransactionMessage('Aucun contrat lié à éditer', 'error');
        return;
    }

    try {
        if (typeof openContractEditModal === 'function') {
            openContractEditModal(contractId);
        } else {
            redirectToContractPage(contractId);
        }
    } catch (error) {
        console.error('Erreur lors de l\'ouverture de la modal:', error);
        redirectToContractPage(contractId);
    }
}

/**
 * Redirige vers la page contract_card_complete.php
 */
function redirectToContractPage(contractId) {
    if (contractId) {
        window.open('contract_card_complete.php?id=' + contractId + '&action=edit', '_blank');
    } else {
        showContractTransactionMessage('ID du contrat manquant', 'error');
    }
}

/**
 * Lie un contrat à la transaction
 */
function linkContract() {
    const contractId = document.getElementById('contract_select').value;

    if (!contractId) {
        showContractTransactionMessage('Veuillez sélectionner un contrat', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'link_contract');
    formData.append('transaction_id', currentTransactionId);
    formData.append('contract_id', contractId);
    formData.append('token', csrfToken);

    fetch('manage_transaction_links.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showContractTransactionMessage(data.message, 'success');
            loadCurrentContract(currentTransactionId);
            loadTransactionLabel(currentTransactionId);
        } else {
            showContractTransactionMessage('Erreur: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showContractTransactionMessage('Erreur de communication', 'error');
        console.error('Erreur:', error);
    });
}

/**
 * Délie un contrat de la transaction
 */
function unlinkContract() {
    if (!currentContractId) {
        showContractTransactionMessage('Aucun contrat à délier', 'error');
        return;
    }

    if (!confirm('Êtes-vous sûr de vouloir délier ce contrat de la transaction ?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'unlink_contract');
    formData.append('transaction_id', currentTransactionId);
    formData.append('token', csrfToken);

    fetch('manage_transaction_links.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showContractTransactionMessage(data.message, 'success');
            loadCurrentContract(currentTransactionId);
            loadTransactionLabel(currentTransactionId);
        } else {
            showContractTransactionMessage('Erreur: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showContractTransactionMessage('Erreur de communication', 'error');
        console.error('Erreur:', error);
    });
}

/**
 * Affiche un message dans la section contrat de la modal transaction
 */
function showContractTransactionMessage(message, type) {
    const messagesDiv = document.getElementById('contract_transaction_messages');

    let bgColor, textColor;
    switch (type) {
        case 'success':
            bgColor = '#d4edda';
            textColor = '#155724';
            break;
        case 'error':
            bgColor = '#f8d7da';
            textColor = '#721c24';
            break;
        default:
            bgColor = '#f8f9fa';
            textColor = '#495057';
    }

    messagesDiv.innerHTML = '<div style="background: ' + bgColor + '; color: ' + textColor + '; padding: 8px; border-radius: 4px; margin-bottom: 10px;">' + escapeHtml(message) + '</div>';

    setTimeout(() => {
        messagesDiv.innerHTML = '';
    }, 3000);
}

// ========== MODAL D'ÉDITION DE CONTRAT ==========

/**
 * Ouvre la modal d'édition de contrat
 */
function openContractEditModal(contractId) {
    if (!contractId) {
        showContractMessage('Erreur: ID du contrat manquant', 'error');
        return;
    }

    document.getElementById('contractEditModal').style.display = 'block';
    document.getElementById('contract_edit_id').value = contractId;

    loadContractData(contractId);
}

/**
 * Ferme la modal d'édition de contrat
 */
function closeContractEditModal() {
    document.getElementById('contractEditModal').style.display = 'none';
    document.getElementById('contract_form_content').style.display = 'none';
    document.getElementById('contract_loading').style.display = 'none';
}

/**
 * Charge les données du contrat
 */
function loadContractData(contractId) {
    const loadingDiv = document.getElementById('contract_loading');
    const formDiv = document.getElementById('contract_form_content');

    loadingDiv.style.display = 'block';
    formDiv.style.display = 'none';

    loadCollaborators();

    fetch('get_contract_data.php?id=' + contractId)
        .then(response => response.json())
        .then(data => {
            loadingDiv.style.display = 'none';

            if (data.success) {
                populateContractForm(data.contract);
                formDiv.style.display = 'block';
            } else {
                showContractMessage('Erreur: ' + data.error, 'error');
            }
        })
        .catch(error => {
            loadingDiv.style.display = 'none';
            showContractMessage('Erreur de communication', 'error');
            console.error('Erreur:', error);
        });
}

/**
 * Charge la liste des collaborateurs
 */
function loadCollaborators() {
    const select = document.getElementById('contract_collaborator');
    select.innerHTML = '<option value="">-- Sélectionner un collaborateur --</option>';

    fetch('get_collaborators.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.collaborators) {
                data.collaborators.forEach(function(collab) {
                    const option = document.createElement('option');
                    option.value = collab.id;
                    option.textContent = collab.label + ' (' + collab.default_percentage + '% - ' + collab.cost_per_session + '/séance)';
                    option.setAttribute('data-percentage', collab.default_percentage);
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des collaborateurs:', error);
        });
}

/**
 * Remplit le formulaire avec les données du contrat
 */
function populateContractForm(contract) {
    document.getElementById('contract_collaborator').value = contract.fk_collaborator || '';
    document.getElementById('contract_label').value = contract.label || '';
    document.getElementById('contract_date_creation').value = contract.date_creation || '';
    document.getElementById('contract_amount_ht').value = contract.amount_ht || '';
    document.getElementById('contract_amount_ttc').value = contract.amount_ttc || '';
    document.getElementById('contract_percentage').value = contract.percentage || '';
    document.getElementById('contract_commission_amount').value = contract.commission_amount || '';

    const factureDiv = document.getElementById('contract_facture_info');
    if (contract.facture_ref) {
        factureDiv.innerHTML = '<strong> ' + escapeHtml(contract.facture_ref) + '</strong><br>' +
                               '<span style="color: #666;">' + escapeHtml(contract.facture_client || '') + '</span><br>' +
                               '<span style="color: #28a745;">' + parseFloat(contract.facture_amount || 0).toFixed(2) + ' €</span>';
    } else {
        factureDiv.innerHTML = '<span style="color: #666; font-style: italic;">Aucune facture liée</span>';
    }

    const propalDiv = document.getElementById('contract_propal_info');
    if (contract.propal_ref) {
        propalDiv.innerHTML = '<strong>' + escapeHtml(contract.propal_ref) + '</strong><br>' +
                              '<span style="color: #666;">' + escapeHtml(contract.propal_client || '') + '</span><br>' +
                              '<span style="color: #007cba;">' + parseFloat(contract.propal_amount || 0).toFixed(2) + ' €</span>';
    } else {
        propalDiv.innerHTML = '<span style="color: #666; font-style: italic;">Aucun devis lié</span>';
    }

    calculateCommission();
}

/**
 * Calcule la commission automatiquement
 */
function calculateCommission() {
    const amountHT = parseFloat(document.getElementById('contract_amount_ht').value) || 0;
    const percentage = parseFloat(document.getElementById('contract_percentage').value) || 0;

    if (amountHT > 0 && percentage > 0) {
        const commission = (amountHT * percentage) / 100;
        document.getElementById('contract_commission_amount').value = commission.toFixed(2);
    }
}

/**
 * Affiche un message dans la modal de contrat
 */
function showContractMessage(message, type) {
    const messagesDiv = document.getElementById('contract_messages');

    let bgColor, textColor;
    switch (type) {
        case 'success':
            bgColor = '#d4edda';
            textColor = '#155724';
            break;
        case 'error':
            bgColor = '#f8d7da';
            textColor = '#721c24';
            break;
        case 'info':
            bgColor = '#d1ecf1';
            textColor = '#0c5460';
            break;
        default:
            bgColor = '#f8f9fa';
            textColor = '#495057';
    }

    messagesDiv.innerHTML = '<div style="background: ' + bgColor + '; color: ' + textColor + '; padding: 8px; border-radius: 4px; margin-bottom: 15px;">' + escapeHtml(message) + '</div>';

    if (type === 'success' || type === 'info') {
        setTimeout(() => {
            messagesDiv.innerHTML = '';
        }, 5000);
    }
}

// ========== FONCTIONS UTILITAIRES ==========

/**
 * Formate une date
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
}

/**
 * Échappe le HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========== EVENT LISTENERS ==========

document.addEventListener('DOMContentLoaded', function() {
    // Changement de type de transaction dans la modal
    const typeSelect = document.getElementById('edit_transaction_type');
    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            toggleTransactionSectionsInModal(this.value);

            if (isCreditType(this.value)) {
                loadCurrentContract(currentTransactionId);
                loadAvailableContracts();
            } else if (isDebitType(this.value)) {
                loadCurrentSupplierInvoice(currentTransactionId);
                loadAvailableSupplierInvoices();
            }
        });
    }

    // Boutons d'édition
    const editButtons = document.querySelectorAll('.btn-edit-transaction');
    editButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const transactionId = this.getAttribute('data-transaction-id');
            const amount = this.getAttribute('data-amount');
            const description = this.getAttribute('data-description');
            const note = this.getAttribute('data-note');
            const type = this.getAttribute('data-type');

            openEditModal(transactionId, amount, description, note, type);
        });
    });

    // Fermer les modales en cliquant en dehors
    const modal = document.getElementById('editTransactionModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeEditModal();
            }
        });
    }

    const contractModal = document.getElementById('contractEditModal');
    if (contractModal) {
        contractModal.addEventListener('click', function(e) {
            if (e.target === contractModal) {
                closeContractEditModal();
            }
        });
    }

    // Fermer les modales avec la touche Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
            closeContractEditModal();
        }
    });

    // Calcul automatique de la commission
    const amountInput = document.getElementById('contract_amount_ht');
    const percentageInput = document.getElementById('contract_percentage');

    if (amountInput) {
        amountInput.addEventListener('input', calculateCommission);
    }
    if (percentageInput) {
        percentageInput.addEventListener('input', calculateCommission);
    }

    // Soumission du formulaire de contrat
    const contractForm = document.getElementById('contractEditForm');
    if (contractForm) {
        contractForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(contractForm);

            fetch('contract_card_complete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Contrat modifié avec succès') || data.includes('mis à jour')) {
                    showContractMessage('Contrat modifié avec succès ! La page va se rafraîchir.', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else if (data.includes('erreur') || data.includes('Erreur')) {
                    showContractMessage('Erreur lors de la modification. Vérifiez les données saisies.', 'error');
                } else {
                    showContractMessage('Modification en cours...', 'info');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                showContractMessage('Erreur de communication', 'error');
                console.error('Erreur:', error);
            });
        });
    }
});
