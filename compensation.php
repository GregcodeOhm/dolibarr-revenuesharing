<?php
/* Copyright (C) 2025 Ohmnibus Studio
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    compensation.php
 * \ingroup revenuesharing
 * \brief   Page de compensation entre facture client et fournisseur
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('bills', 'suppliers', 'banks'));

// Security check
if (!$user->admin) {
    accessforbidden('Accès réservé aux administrateurs');
}

// Check if user has rights on invoices
if (empty($user->rights->facture->lire) || empty($user->rights->fournisseur->facture->lire)) {
    accessforbidden('Vous devez avoir les droits de lecture sur les factures clients et fournisseurs');
}

// Parameters
$action = GETPOST('action', 'aZ09');
$ref_client = GETPOST('ref_client', 'alpha');
$ref_fourn = GETPOST('ref_fourn', 'alpha');

/*
 * Actions
 */

if ($action == 'compensate') {
    if (empty($ref_client) || empty($ref_fourn)) {
        setEventMessages('Les deux références sont obligatoires.', null, 'errors');
    } else {
        $db->begin();

        $error = 0;

        // Recherche de la facture client
        $sql = "SELECT rowid, total_ttc, fk_statut, paye";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture";
        $sql .= " WHERE ref = '".$db->escape($ref_client)."'";
        $sql .= " AND entity IN (".getEntity('invoice').")";

        $resql = $db->query($sql);
        if ($resql) {
            $fac_client = $db->fetch_object($resql);
            $db->free($resql);
        } else {
            $error++;
            setEventMessages($db->lasterror(), null, 'errors');
        }

        // Recherche de la facture fournisseur
        if (!$error) {
            $sql = "SELECT rowid, total_ttc, fk_statut, paye";
            $sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn";
            $sql .= " WHERE ref = '".$db->escape($ref_fourn)."'";
            $sql .= " AND entity IN (".getEntity('supplier_invoice').")";

            $resql = $db->query($sql);
            if ($resql) {
                $fac_fourn = $db->fetch_object($resql);
                $db->free($resql);
            } else {
                $error++;
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }

        if (!$error) {
            if (!$fac_client) {
                setEventMessages('Facture client "'.$ref_client.'" introuvable.', null, 'errors');
                $error++;
            }
            if (!$fac_fourn) {
                setEventMessages('Facture fournisseur "'.$ref_fourn.'" introuvable.', null, 'errors');
                $error++;
            }
        }

        // Vérification des statuts
        if (!$error) {
            if ($fac_client->paye == 1) {
                setEventMessages('La facture client est déjà marquée comme payée.', null, 'warnings');
            }
            if ($fac_fourn->paye == 1) {
                setEventMessages('La facture fournisseur est déjà marquée comme payée.', null, 'warnings');
            }
        }

        // Création de la compensation
        if (!$error) {
            // Montant à compenser : le plus petit TTC
            $amount = min($fac_client->total_ttc, $fac_fourn->total_ttc);
            $today = dol_now();
            $refpay = 'COMP-'.dol_print_date($today, '%Y%m%d%H%M%S');

            // --- Paiement client ---
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."paiement";
            $sql .= " (datec, datep, amount, num_paiement, note, fk_paiement, entity)";
            $sql .= " VALUES (";
            $sql .= " '".$db->idate($today)."',";
            $sql .= " '".$db->idate($today)."',";
            $sql .= " ".$amount.",";
            $sql .= " '".$db->escape($refpay)."',";
            $sql .= " 'Compensation automatique client/fournisseur',";
            $sql .= " 6,"; // Type 6 = Compensation
            $sql .= " ".$conf->entity;
            $sql .= ")";

            if (!$db->query($sql)) {
                $error++;
                setEventMessages($db->lasterror(), null, 'errors');
            }

            if (!$error) {
                $id_paiement_client = $db->last_insert_id(MAIN_DB_PREFIX.'paiement');

                // Lien facture client
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."paiement_facture";
                $sql .= " (fk_paiement, fk_facture, amount, multicurrency_amount)";
                $sql .= " VALUES (";
                $sql .= " ".$id_paiement_client.",";
                $sql .= " ".$fac_client->rowid.",";
                $sql .= " ".$amount.",";
                $sql .= " ".$amount;
                $sql .= ")";

                if (!$db->query($sql)) {
                    $error++;
                    setEventMessages($db->lasterror(), null, 'errors');
                }
            }

            // --- Paiement fournisseur ---
            if (!$error) {
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."paiementfourn";
                $sql .= " (datec, datep, amount, num_paiement, note, fk_paiement, entity)";
                $sql .= " VALUES (";
                $sql .= " '".$db->idate($today)."',";
                $sql .= " '".$db->idate($today)."',";
                $sql .= " ".$amount.",";
                $sql .= " '".$db->escape($refpay)."',";
                $sql .= " 'Compensation automatique client/fournisseur',";
                $sql .= " 6,"; // Type 6 = Compensation
                $sql .= " ".$conf->entity;
                $sql .= ")";

                if (!$db->query($sql)) {
                    $error++;
                    setEventMessages($db->lasterror(), null, 'errors');
                } else {
                    $id_paiement_fourn = $db->last_insert_id(MAIN_DB_PREFIX.'paiementfourn');

                    // Lien facture fournisseur
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."paiementfourn_facturefourn";
                    $sql .= " (fk_paiementfourn, fk_facturefourn, amount, multicurrency_amount)";
                    $sql .= " VALUES (";
                    $sql .= " ".$id_paiement_fourn.",";
                    $sql .= " ".$fac_fourn->rowid.",";
                    $sql .= " ".$amount.",";
                    $sql .= " ".$amount;
                    $sql .= ")";

                    if (!$db->query($sql)) {
                        $error++;
                        setEventMessages($db->lasterror(), null, 'errors');
                    }
                }
            }

            // --- MAJ statuts si totalement payées ---
            if (!$error) {
                // Vérifier si la facture client est totalement payée
                $sql = "SELECT SUM(amount) as total_paye";
                $sql .= " FROM ".MAIN_DB_PREFIX."paiement_facture";
                $sql .= " WHERE fk_facture = ".$fac_client->rowid;
                $resql = $db->query($sql);
                $obj = $db->fetch_object($resql);

                if ($obj->total_paye >= $fac_client->total_ttc) {
                    $sql = "UPDATE ".MAIN_DB_PREFIX."facture";
                    $sql .= " SET fk_statut = 2, paye = 1";
                    $sql .= " WHERE rowid = ".$fac_client->rowid;
                    $db->query($sql);
                }

                // Vérifier si la facture fournisseur est totalement payée
                $sql = "SELECT SUM(amount) as total_paye";
                $sql .= " FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn";
                $sql .= " WHERE fk_facturefourn = ".$fac_fourn->rowid;
                $resql = $db->query($sql);
                $obj = $db->fetch_object($resql);

                if ($obj->total_paye >= $fac_fourn->total_ttc) {
                    $sql = "UPDATE ".MAIN_DB_PREFIX."facture_fourn";
                    $sql .= " SET fk_statut = 2, paye = 1";
                    $sql .= " WHERE rowid = ".$fac_fourn->rowid;
                    $db->query($sql);
                }
            }
        }

        if (!$error) {
            $db->commit();
            setEventMessages('Compensation enregistrée : '.price($amount).' '.$langs->trans("Currency".$conf->currency), null, 'mesgs');

            // Reset fields
            $ref_client = '';
            $ref_fourn = '';
        } else {
            $db->rollback();
        }
    }
}

/*
 * View
 */

llxHeader('', 'Compensation Client/Fournisseur');

print load_fiche_titre('Compensation de factures Client / Fournisseur', '', 'bill');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="compensate">';

print '<table class="border centpercent">';

print '<tr>';
print '<td class="fieldrequired">Référence facture client</td>';
print '<td><input type="text" name="ref_client" value="'.dol_escape_htmltag($ref_client).'" size="30" placeholder="ex : FA2509-001"></td>';
print '</tr>';

print '<tr>';
print '<td class="fieldrequired">Référence facture fournisseur</td>';
print '<td><input type="text" name="ref_fourn" value="'.dol_escape_htmltag($ref_fourn).'" size="30" placeholder="ex : FO2509-001"></td>';
print '</tr>';

print '</table>';

print '<br>';
print '<div class="center">';
print '<input type="submit" class="button" name="save" value="Créer la compensation">';
print '</div>';

print '</form>';

print '<br><div class="info">';
print '<strong>ℹ️ Fonctionnement :</strong><br>';
print '- Le montant compensé sera le plus petit TTC entre les deux factures<br>';
print '- Un paiement de type "Compensation" sera créé pour chaque facture<br>';
print '- Si les factures sont totalement payées après compensation, elles seront marquées comme payées<br>';
print '</div>';

// End of page
llxFooter();
$db->close();
