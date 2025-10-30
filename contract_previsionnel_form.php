<?php
/**
 * Formulaire de création de contrats prévisionnels
 * Fichier: /htdocs/custom/revenuesharing/contract_previsionnel_form.php
 */

require_once '../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent créer des contrats prévisionnels');
}

// Récupérer l'action
$action = GETPOST('action', 'alpha');
$id = GETPOST('id', 'int');

// Traitement du formulaire
if ($action == 'create') {
    $collaborator_id = GETPOST('fk_collaborator', 'int');
    $project_id = GETPOST('fk_project', 'int');
    $label = GETPOST('label', 'alphanohtml');
    $date_prestation = GETPOST('date_prestation', 'alpha');
    $date_facturation = GETPOST('date_facturation', 'alpha');
    $amount_ht = price2num(GETPOST('amount_ht', 'alpha'));
    $collaborator_percentage = price2num(GETPOST('collaborator_percentage', 'alpha'));
    $nb_sessions = GETPOST('nb_sessions', 'int');
    $cost_per_session = price2num(GETPOST('cost_per_session', 'alpha'));
    $note_private = GETPOST('note_private', 'restricthtml');
    
    if ($collaborator_id > 0 && !empty($label) && $amount_ht > 0 && $collaborator_percentage > 0) {
        // Calculs automatiques
        $collaborator_amount_ht = $amount_ht * ($collaborator_percentage / 100);
        $studio_amount_ht = $amount_ht - $collaborator_amount_ht;
        $total_costs = $nb_sessions * $cost_per_session;
        $net_collaborator_amount = $collaborator_amount_ht - $total_costs;
        
        // Générer une référence
        $ref = 'PREV-' . date('Y') . '-' . sprintf('%04d', mt_rand(1, 9999));
        
        // Vérifier que la référence n'existe pas
        $sql_check = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE ref = '".$db->escape($ref)."'";
        $resql_check = $db->query($sql_check);
        if ($resql_check) {
            $check_result = $db->fetch_object($resql_check);
            if ($check_result->nb > 0) {
                $ref = 'PREV-' . date('Y') . '-' . sprintf('%04d', mt_rand(1, 9999)) . '-' . time();
            }
            $db->free($resql_check);
        }
        
        $db->begin();
        
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_contract (";
        $sql .= "ref, type_contrat, fk_collaborator, fk_project, label, ";
        $sql .= "amount_ht, collaborator_percentage, collaborator_amount_ht, studio_amount_ht, ";
        $sql .= "nb_sessions, cost_per_session, total_costs, net_collaborator_amount, ";
        $sql .= "date_prestation_prevue, date_facturation_prevue, ";
        $sql .= "status, note_private, date_creation, fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= "'".$db->escape($ref)."', 'previsionnel', ".(int)$collaborator_id.", ";
        $sql .= ($project_id > 0 ? (int)$project_id : "NULL").", '".$db->escape($label)."', ";
        $sql .= (float)$amount_ht.", ".(float)$collaborator_percentage.", ".(float)$collaborator_amount_ht.", ".(float)$studio_amount_ht.", ";
        $sql .= (int)$nb_sessions.", ".(float)$cost_per_session.", ".(float)$total_costs.", ".(float)$net_collaborator_amount.", ";
        $sql .= ($date_prestation ? "'".$db->escape($date_prestation)."'" : "NULL").", ";
        $sql .= ($date_facturation ? "'".$db->escape($date_facturation)."'" : "NULL").", ";
        $sql .= "0, '".$db->escape($note_private)."', NOW(), ".(int)$user->id;
        $sql .= ")";
        
        $resql = $db->query($sql);
        if ($resql) {
            $db->commit();
            setEventMessage('Contrat prévisionnel créé avec succès', 'mesgs');
            header('Location: contract_list.php');
            exit;
        } else {
            $db->rollback();
            setEventMessage('Erreur lors de la création: ' . $db->lasterror(), 'errors');
        }
    } else {
        setEventMessage('Veuillez remplir tous les champs obligatoires', 'errors');
    }
}

llxHeader('', 'Nouveau contrat prévisionnel');

print load_fiche_titre(' Nouveau Contrat Prévisionnel', '', 'generic');

print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #0d47a1;">À propos des contrats prévisionnels</h4>';
print '<p style="margin: 0;">Les contrats prévisionnels permettent de planifier et estimer les revenus futurs sans être liés à une facture existante.</p>';
print '<p style="margin: 5px 0 0 0;"><strong>Avantages :</strong> Planification budgétaire, estimation des soldes, suivi prévisionnel.</p>';
print '</div>';

// Récupérer les collaborateurs
$sql_collabs = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1 ORDER BY label";
$resql_collabs = $db->query($sql_collabs);
$collaborators = array();
if ($resql_collabs) {
    while ($collab = $db->fetch_object($resql_collabs)) {
        $collaborators[$collab->rowid] = $collab->label;
    }
    $db->free($resql_collabs);
}

// Récupérer les projets
$sql_projects = "SELECT rowid, ref, title FROM ".MAIN_DB_PREFIX."projet WHERE entity = ".(int)$conf->entity." AND fk_statut = 1 ORDER BY ref";
$resql_projects = $db->query($sql_projects);
$projects = array();
if ($resql_projects) {
    while ($proj = $db->fetch_object($resql_projects)) {
        $projects[$proj->rowid] = $proj->ref . ' - ' . $proj->title;
    }
    $db->free($resql_projects);
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="action" value="create">';

print '<div style="background: white; border-radius: 8px; padding: 20px; border: 1px solid #dee2e6;">';

// Section collaborateur et projet
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;"> Collaborateur *</label>';
print '<select name="fk_collaborator" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
print '<option value="">-- Sélectionner un collaborateur --</option>';
foreach ($collaborators as $collab_id => $collab_label) {
    print '<option value="'.$collab_id.'">'.$collab_label.'</option>';
}
print '</select>';
print '</div>';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Projet (optionnel)</label>';
print '<select name="fk_project" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
print '<option value="">-- Aucun projet --</option>';
foreach ($projects as $proj_id => $proj_label) {
    print '<option value="'.$proj_id.'">'.$proj_label.'</option>';
}
print '</select>';
print '</div>';

print '</div>';

// Section informations du contrat
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Libellé du contrat *</label>';
print '<input type="text" name="label" required maxlength="255" placeholder="Ex: Prestation son - Concert XYZ" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
print '</div>';

// Section dates
print '<div style="background: #f8f9fa; border-radius: 6px; padding: 15px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 15px 0; color: #495057;">Dates du Contrat</h4>';

print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Date de prestation prévue</label>';
print '<input type="date" name="date_prestation" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" value="'.date('Y-m-d').'">';
print '<small style="color: var(--colortextbackhmenu); display: block; margin-top: 3px;">Date estimée de la prestation</small>';
print '</div>';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Date de facturation prévue</label>';
print '<input type="date" name="date_facturation" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
print '<small style="color: var(--colortextbackhmenu); display: block; margin-top: 3px;">Optionnel - Quand facturer ce contrat</small>';
print '</div>';

print '</div>';
print '</div>';

// Section montants
print '<div style="background: #f8f9fa; border-radius: 6px; padding: 15px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 15px 0; color: #007cba;">Montants et Répartition</h4>';

print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">💶 Montant HT total *</label>';
print '<input type="number" name="amount_ht" required step="0.01" min="0" placeholder="0.00" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" id="amount_ht" onchange="calculateAmounts()">';
print '</div>';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">% Collaborateur *</label>';
print '<input type="number" name="collaborator_percentage" required step="0.01" min="0" max="100" placeholder="0.00" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" id="collaborator_percentage" onchange="calculateAmounts()">';
print '</div>';

print '</div>';

// Résultat des calculs
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';

print '<div style="background: #e8f5e8; padding: 10px; border-radius: 4px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #2d7d2d;">Part collaborateur</label>';
print '<div id="collaborator_amount" style="font-size: 1.2em; font-weight: bold;">0,00 €</div>';
print '</div>';

print '<div style="background: #fff3e0; padding: 10px; border-radius: 4px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #f57c00;"> Part structure</label>';
print '<div id="studio_amount" style="font-size: 1.2em; font-weight: bold;">0,00 €</div>';
print '</div>';

print '</div>';

print '</div>';

// Section coûts
print '<div style="background: #f8f9fa; border-radius: 6px; padding: 15px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 15px 0; color: #dc3545;">Coûts et Sessions</h4>';

print '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Nb de sessions</label>';
print '<input type="number" name="nb_sessions" min="0" value="0" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" id="nb_sessions" onchange="calculateAmounts()">';
print '</div>';

print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">💶 Coût par session</label>';
print '<input type="number" name="cost_per_session" step="0.01" min="0" value="0" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" id="cost_per_session" onchange="calculateAmounts()">';
print '</div>';

print '<div style="background: #f8d7da; padding: 10px; border-radius: 4px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #721c24;">Net collaborateur</label>';
print '<div id="net_amount" style="font-size: 1.2em; font-weight: bold;">0,00 €</div>';
print '</div>';

print '</div>';

print '</div>';

// Notes
print '<div style="margin-bottom: 20px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Notes privées</label>';
print '<textarea name="note_private" rows="3" placeholder="Notes internes sur ce contrat prévisionnel..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>';
print '</div>';

print '</div>';

// Boutons d'action
print '<div class="tabsAction" style="text-align: center; margin-top: 20px;">';
print '<input type="submit" value="Créer le contrat prévisionnel" class="butAction">';
print '<a href="contract_list.php" class="butActionRefused">Annuler</a>';
print '</div>';

print '</form>';

// JavaScript pour les calculs automatiques
print '<script>
function calculateAmounts() {
    const amount_ht = parseFloat(document.getElementById("amount_ht").value) || 0;
    const percentage = parseFloat(document.getElementById("collaborator_percentage").value) || 0;
    const nb_sessions = parseInt(document.getElementById("nb_sessions").value) || 0;
    const cost_per_session = parseFloat(document.getElementById("cost_per_session").value) || 0;
    
    const collaborator_amount = amount_ht * (percentage / 100);
    const studio_amount = amount_ht - collaborator_amount;
    const total_costs = nb_sessions * cost_per_session;
    const net_amount = collaborator_amount - total_costs;
    
    document.getElementById("collaborator_amount").textContent = collaborator_amount.toFixed(2).replace(".", ",") + " €";
    document.getElementById("studio_amount").textContent = studio_amount.toFixed(2).replace(".", ",") + " €";
    document.getElementById("net_amount").textContent = net_amount.toFixed(2).replace(".", ",") + " €";
    
    // Colorier selon le résultat
    const netElement = document.getElementById("net_amount");
    if (net_amount < 0) {
        netElement.style.color = "#dc3545";
        netElement.parentElement.style.background = "#f8d7da";
    } else {
        netElement.style.color = "#155724";
        netElement.parentElement.style.background = "#d4edda";
    }
}

// Initialiser les calculs
document.addEventListener("DOMContentLoaded", function() {
    calculateAmounts();
});
</script>';

llxFooter();
$db->close();
?>