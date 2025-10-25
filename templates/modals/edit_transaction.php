<?php
/**
 * Template: Modale d'édition de transaction
 * Variables requises:
 * - $id: ID du collaborateur
 * - $type_labels_js: Array des labels de types pour JS
 */

if (!defined('MAIN_DB_PREFIX')) {
    die('Accès interdit');
}
?>

<!-- Modal d'édition de transaction -->
<div id="editTransactionModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: white; margin: 2% auto; border-radius: 8px; width: 90%; max-width: 700px; height: 90%; max-height: 800px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); display: flex; flex-direction: column;">

        <!-- En-tête fixe -->
        <div style="padding: 20px 20px 15px 20px; border-bottom: 1px solid #ddd; flex-shrink: 0;">
            <h3 style="margin: 0; color: #007cba;">Éditer la transaction</h3>
        </div>

        <!-- Zone de contenu avec scrollbar -->
        <div style="flex: 1; overflow-y: auto; padding: 20px;">
            <form id="editTransactionForm" method="POST" action="edit_transaction.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="transaction_id" id="edit_transaction_id">
                <input type="hidden" name="collaborator_id" value="<?php echo $id; ?>">
                <input type="hidden" name="token" value="<?php echo newToken(); ?>">

                <div style="margin-bottom: 15px;">
                    <label for="edit_transaction_type" style="display: block; font-weight: bold; margin-bottom: 5px;">Type de transaction:</label>
                    <select name="transaction_type" id="edit_transaction_type" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <?php foreach ($type_labels_js as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="edit_amount" style="display: block; font-weight: bold; margin-bottom: 5px;">Montant (€):</label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                    <small style="color: #666; display: block; margin-top: 5px;" id="amount_help">Si vous venez de supprimer une liaison facture, vous pouvez fermer cette fenêtre sans modifier le montant.</small>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="edit_description" style="display: block; font-weight: bold; margin-bottom: 5px;">Description:</label>
                    <input type="text" name="description" id="edit_description" autocomplete="off" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                </div>

                <!-- Section Libellé (contrat/facture liée) -->
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Réf. client (contrat/facture liée):</label>
                    <div id="edit_label_display" style="background: #f8f9fa; padding: 8px; border: 1px solid #e9ecef; border-radius: 4px; min-height: 20px; color: #666; font-style: italic; position: relative;">
                        Aucune réf. client (transaction non liée)
                    </div>
                    <div id="edit_label_actions" style="margin-top: 5px; display: none;">
                        <small style="color: #007cba;">
                            <em>La réf. client peut être modifiée via la page de gestion des contrats</em>
                        </small>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="edit_note_private" style="display: block; font-weight: bold; margin-bottom: 5px;">Note privée:</label>
                    <textarea name="note_private" id="edit_note_private" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
                </div>

            <!-- Section Liaison Contrat/Facture Client (pour les crédits) -->
            <div id="contract_section_modal" style="margin-bottom: 20px; border-top: 1px solid #ddd; padding-top: 15px; display: none;">
                <h4 style="margin: 0 0 10px 0; color: #007cba;">Liaison avec contrat/facture client</h4>

                <!-- Contrat actuellement lié -->
                <div id="current_contract" style="margin-bottom: 15px;">
                    <div id="current_contract_info"></div>
                </div>

                <!-- Actions sur le contrat -->
                <div style="margin-bottom: 10px;">
                    <button type="button" onclick="editContractWithFallback()" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-right: 10px;"><?php print img_picto('', 'edit'); ?> Éditer le contrat</button>
                    <button type="button" onclick="unlinkContract()" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;"><?php print img_picto('', 'unlink'); ?> Délier le contrat</button>
                </div>

                <!-- Sélection d'un nouveau contrat -->
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Lier à un contrat:</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="contract_select" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Sélectionner un contrat...</option>
                        </select>
                        <button type="button" onclick="linkContract()" style="background: #007cba; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;"><?php print img_picto('', 'link'); ?> Lier</button>
                    </div>
                </div>

                <!-- Messages pour contrat -->
                <div id="contract_transaction_messages" style="margin-top: 10px;"></div>
            </div>

            <!-- Section Liaison Facture Fournisseur (seulement pour les débits) -->
            <div id="supplier_invoice_section_modal" style="margin-bottom: 20px; border-top: 1px solid #ddd; padding-top: 15px; display: none;">
                <h4 style="margin: 0 0 10px 0; color: #007cba;">Liaison avec facture fournisseur</h4>

                <!-- Facture actuellement liée -->
                <div id="current_supplier_invoice" style="margin-bottom: 15px;">
                    <div id="current_invoice_info"></div>
                </div>

                <!-- Sélection d'une nouvelle facture -->
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Lier à une facture fournisseur:</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="supplier_invoice_select" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">Sélectionner une facture...</option>
                        </select>
                        <button type="button" onclick="linkSupplierInvoice()" style="background: #007cba; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;"><?php print img_picto('', 'link'); ?> Lier</button>
                    </div>
                </div>

                <!-- Documents de la facture liée -->
                <div id="supplier_invoice_documents" style="margin-top: 15px;">
                    <div id="invoice_documents_list"></div>
                </div>

                <!-- Messages -->
                <div id="invoice_messages" style="margin-top: 10px;"></div>
            </div>

            </form>
        </div>

        <!-- Pied de page fixe avec boutons -->
        <div style="padding: 15px 20px; border-top: 1px solid #ddd; background: #f8f9fa; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" onclick="deleteTransactionFromModal()" id="modal_delete_btn" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">🗑️ Supprimer</button>
            <div>
                <button type="button" onclick="closeEditModal()" id="modal_close_btn" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px; cursor: pointer;">Fermer</button>
                <button type="submit" form="editTransactionForm" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Sauvegarder</button>
            </div>
        </div>
    </div>
</div>
