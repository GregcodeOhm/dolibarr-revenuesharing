<?php
/**
 * Script pour transformer un contrat prévisionnel en contrat réel
 * Fichier: /htdocs/custom/revenuesharing/transform_previsionnel.php
 */

require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent transformer les contrats');
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

if ($id <= 0) {
    print '<div style="color: red;">ID contrat manquant</div>';
    llxFooter();
    $db->close();
    exit;
}

// Récupérer le contrat
$sql_contract = "SELECT * FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE rowid = ".((int) $id);
$resql_contract = $db->query($sql_contract);

if (!$resql_contract || $db->num_rows($resql_contract) == 0) {
    print '<div style="color: red;">Contrat non trouvé</div>';
    llxFooter();
    $db->close();
    exit;
}

$contract = $db->fetch_object($resql_contract);
$db->free($resql_contract);

if ($contract->type_contrat != 'previsionnel') {
    header('Location: contract_card_complete.php?id='.$id);
    exit;
}

// Traitement de la transformation
if ($action == 'transform') {
    $facture_id = GETPOST('fk_facture', 'int');
    
    if ($facture_id > 0) {
        $db->begin();
        
        // Récupérer les infos de la facture
        $sql_facture = "SELECT total_ht, total_ttc, datef FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".((int) $facture_id);
        $resql_facture = $db->query($sql_facture);
        
        if ($resql_facture) {
            $facture = $db->fetch_object($resql_facture);
            
            // Mettre à jour le contrat
            $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET 
                          type_contrat = 'reel',
                          fk_facture = ".((int) $facture_id).",
                          amount_ht = ".(float)$facture->total_ht.",
                          amount_ttc = ".(float)$facture->total_ttc.",
                          date_modification = NOW(),
                          fk_user_modif = ".((int) $user->id)."
                          WHERE rowid = ".((int) $id);
            
            $resql_update = $db->query($sql_update);
            
            if ($resql_update) {
                // Recalculer les montants collaborateur en conservant le pourcentage
                $new_collaborator_amount = $facture->total_ht * ($contract->collaborator_percentage / 100);
                $new_studio_amount = $facture->total_ht - $new_collaborator_amount;
                $new_net_amount = $new_collaborator_amount - $contract->total_costs;
                
                $sql_amounts = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract SET 
                               collaborator_amount_ht = ".(float)$new_collaborator_amount.",
                               studio_amount_ht = ".(float)$new_studio_amount.",
                               net_collaborator_amount = ".(float)$new_net_amount."
                               WHERE rowid = ".((int) $id);
                
                $resql_amounts = $db->query($sql_amounts);
                
                if ($resql_amounts) {
                    $db->commit();
                    setEventMessage('Contrat transformé en contrat réel avec succès', 'mesgs');
                    header('Location: contract_card_complete.php?id='.$id);
                    exit;
                } else {
                    $db->rollback();
                    setEventMessage('Erreur lors du recalcul des montants', 'errors');
                }
            } else {
                $db->rollback();
                setEventMessage('Erreur lors de la transformation: ' . $db->lasterror(), 'errors');
            }
            
            $db->free($resql_facture);
        } else {
            $db->rollback();
            setEventMessage('Facture non trouvée', 'errors');
        }
    } else {
        setEventMessage('Veuillez sélectionner une facture', 'errors');
    }
}

llxHeader('', 'Transformer contrat prévisionnel');

print load_fiche_titre('Transformer le contrat prévisionnel "'.$contract->ref.'"', '', 'generic');

print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #0d47a1;">Transformation en contrat réel</h4>';
print '<p style="margin: 0;">Cette action va associer une facture à ce contrat prévisionnel et le transformer en contrat réel.</p>';
print '<p style="margin: 5px 0 0 0;"><strong>Action irréversible :</strong> Les montants seront recalculés selon la facture.</p>';
print '</div>';

// Afficher les détails actuels du contrat
print '<div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #495057;">Contrat prévisionnel actuel</h4>';
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';

print '<div>';
print '<p><strong>Référence :</strong> '.$contract->ref.'</p>';
print '<p><strong>Libellé :</strong> '.$contract->label.'</p>';
print '<p><strong>Collaborateur ID :</strong> '.$contract->fk_collaborator.'</p>';
if ($contract->date_prestation_prevue) {
    print '<p><strong>Date prestation prévue :</strong> '.dol_print_date(strtotime($contract->date_prestation_prevue), 'day').'</p>';
}
print '</div>';

print '<div>';
print '<p><strong>Montant HT :</strong> '.price($contract->amount_ht).'</p>';
print '<p><strong>% Collaborateur :</strong> '.$contract->collaborator_percentage.'%</p>';
print '<p><strong>Part collaborateur :</strong> '.price($contract->collaborator_amount_ht).'</p>';
print '<p><strong>Montant net :</strong> '.price($contract->net_collaborator_amount).'</p>';
print '</div>';

print '</div>';
print '</div>';

// Formulaire de transformation
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="action" value="transform">';
print '<input type="hidden" name="id" value="'.$id.'">';

print '<div style="background: white; border-radius: 8px; padding: 20px; border: 1px solid #dee2e6; margin: 20px 0;">';
print '<h4 style="margin: 0 0 15px 0; color: #007cba;"> Sélectionner une facture</h4>';

print '<div style="margin-bottom: 15px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Facture à associer *</label>';
print '<input type="text" id="facture_search" placeholder="Tapez la référence de la facture..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" autocomplete="off">';
print '<input type="hidden" name="fk_facture" id="selected_facture_id">';
print '<div id="facture_info" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; display: none;">';
print '<div id="facture_details"></div>';
print '</div>';
print '</div>';

print '</div>';

print '<div class="tabsAction" style="text-align: center; margin-top: 20px;">';
print '<input type="submit" value="Transformer en contrat réel" class="butAction" id="submit_btn" disabled>';
print '<a href="contract_card_complete.php?id='.$id.'" class="butActionRefused">Annuler</a>';
print '</div>';

print '</form>';

// JavaScript pour l'autocomplétion unifiée des factures
print '<script src="js/autocomplete.js"></script>';

print '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Utilisation de la classe unifiée pour l\'autocomplétion
    const factureAutocomplete = createFactureAutocomplete("facture_search", {
        endpoint: "contract_card_complete.php",
        onSelect: function(item, input) {
            // Comportement spécifique pour la transformation
            document.getElementById("selected_facture_id").value = item.value;

            const details = `
                <strong>Facture sélectionnée :</strong> ${item.ref}<br>
                <strong>Montant HT :</strong> ${item.total_ht.toFixed(2)} €<br>
                <strong>Client :</strong> ${item.client}<br>
                <small style="color: #666;">Le contrat sera mis à jour avec ce montant</small>
            `;

            document.getElementById("facture_details").innerHTML = details;
            document.getElementById("facture_info").style.display = "block";

            const submitBtn = document.getElementById("submit_btn");
            submitBtn.disabled = false;
            submitBtn.classList.remove("butActionRefused");
            submitBtn.classList.add("butAction");
        }
    });
});
</script>';

llxFooter();
$db->close();
?>