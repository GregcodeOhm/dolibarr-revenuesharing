<?php
/**
 * Script pour ajouter le champ type_contrat pour les contrats prévisionnels
 * Fichier: /htdocs/custom/revenuesharing/admin/update_contracts_previsionnel.php
 */

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Mise à jour contrats - Type prévisionnel');

print load_fiche_titre(' Mise à jour des contrats - Support des prévisionnels', '', 'generic');

print '<div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 15px; margin: 15px 0;">';
print '<h4 style="margin: 0 0 10px 0; color: #0d47a1;">Contrats prévisionnels</h4>';
print '<p style="margin: 0;">Ajout du support des contrats prévisionnels non liés à des factures existantes.</p>';
print '<p style="margin: 5px 0 0 0;"><strong>Types :</strong> "reel" (lié à une facture) et "previsionnel" (estimation)</p>';
print '</div>';

// Ajouter le champ type_contrat
$sql_add_type = "ALTER TABLE ".MAIN_DB_PREFIX."revenuesharing_contract 
                 ADD COLUMN type_contrat VARCHAR(20) DEFAULT 'reel' AFTER ref";

print '<div style="margin: 20px 0;">';

print '<h3>Ajout du champ type_contrat</h3>';
$resql_add = $db->query($sql_add_type);
if ($resql_add) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">Champ "type_contrat" ajouté avec succès</div>';
    
    // Marquer tous les contrats existants comme "reel"
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract 
                   SET type_contrat = 'reel' 
                   WHERE type_contrat IS NULL OR type_contrat = ''";
    
    $resql_update = $db->query($sql_update);
    if ($resql_update) {
        print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">Contrats existants marqués comme "réel"</div>';
    }
} else {
    $error = $db->lasterror();
    if (strpos($error, 'Duplicate column name') !== false || strpos($error, 'already exists') !== false) {
        print '<div style="color: orange; padding: 10px; background: #fff3cd; border-radius: 5px; margin: 10px 0;">Le champ "type_contrat" existe déjà</div>';
    } else {
        print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">Erreur: ' . $error . '</div>';
    }
}

print '<h3>Types de contrats disponibles</h3>';
print '<div style="background: #f0f8f0; border: 1px solid #c3e6c3; border-radius: 8px; padding: 15px;">';
print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';

print '<div style="background: white; padding: 12px; border-radius: 6px; border-left: 4px solid #28a745;">';
print '<h4 style="margin: 0 0 8px 0; color: #155724;">Contrat Réel</h4>';
print '<ul style="margin: 0; padding-left: 20px; color: #666;">';
print '<li>Lié à une facture existante</li>';
print '<li>Montants basés sur la facture</li>';
print '<li>Suivi complet des paiements</li>';
print '</ul>';
print '</div>';

print '<div style="background: white; padding: 12px; border-radius: 6px; border-left: 4px solid #007cba;">';
print '<h4 style="margin: 0 0 8px 0; color: #004085;"> Contrat Prévisionnel</h4>';
print '<ul style="margin: 0; padding-left: 20px; color: #666;">';
print '<li>Estimation de projet</li>';
print '<li>Saisie manuelle des montants</li>';
print '<li>Planification budgétaire</li>';
print '</ul>';
print '</div>';

print '</div>';
print '</div>';

print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 20px; margin: 20px 0;">';
print '<h3 style="color: #2d7d2d; margin-top: 0;">🎉 Mise à jour terminée !</h3>';
print '<p><strong>Nouvelles fonctionnalités :</strong></p>';
print '<ul>';
print '<li>Champ "type_contrat" ajouté (VARCHAR 20)</li>';
print '<li>Support des contrats prévisionnels</li>';
print '<li>Formulaire de saisie manuelle</li>';
print '<li>Distinction visuelle dans les listes</li>';
print '</ul>';
print '</div>';

print '</div>';

print '<div class="tabsAction">';
print '<a href="contract_previsionnel_form.php" class="butAction"> Nouveau contrat prévisionnel</a>';
print '<a href="../contract_list.php" class="butAction">Voir les contrats</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>