<?php
/**
 * Outil de vérification de la continuité des numéros de factures par année
 * Détecte les trous dans la numérotation des factures clients
 */

$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';
require_once $dolibarr_main_document_root.'/core/lib/admin.lib.php';
require_once $dolibarr_main_document_root.'/compta/facture/class/facture.class.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cet outil');
}

$year = GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y');
$show_all = GETPOST('show_all', 'alpha') == 'yes';

llxHeader('', 'Vérification Continuité Factures', '');

print load_fiche_titre('🔢 Vérification de la continuité des numéros de factures', '', 'bill');

print '<style>
.continuity-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
}
.summary-card {
    display: inline-block;
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin: 10px 10px 10px 0;
    border-radius: 4px;
    min-width: 200px;
}
.summary-card.success {
    border-left-color: #28a745;
    background: #e8f5e9;
}
.summary-card.warning {
    border-left-color: #ffc107;
    background: #fff3cd;
}
.summary-card.danger {
    border-left-color: #dc3545;
    background: #f8d7da;
}
.summary-card .value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}
.summary-card .label {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}
table.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
table.invoice-table th {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}
table.invoice-table td {
    border: 1px solid #dee2e6;
    padding: 8px;
}
table.invoice-table tr.gap-row {
    background: #fff3cd;
}
table.invoice-table tr.deleted-row {
    background: #f8d7da;
    opacity: 0.7;
}
table.invoice-table tr.draft-row {
    background: #e3f2fd;
    opacity: 0.8;
}
.filter-form {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: end;
}
.filter-form label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}
.legend {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    margin: 15px 0;
    font-size: 12px;
}
.legend span {
    display: inline-block;
    margin-right: 20px;
}
.legend .color-box {
    display: inline-block;
    width: 15px;
    height: 15px;
    margin-right: 5px;
    vertical-align: middle;
    border: 1px solid #ccc;
}
</style>';

// Formulaire de sélection
print '<div class="filter-form">';
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="display: flex; gap: 15px; align-items: end; flex: 1;">';
print '<div>';
print '<label>Année :</label>';
print '<select name="year" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
for ($y = date('Y'); $y >= 2020; $y--) {
    print '<option value="'.$y.'"'.($y == $year ? ' selected' : '').'>'.$y.'</option>';
}
print '</select>';
print '</div>';

print '<div>';
print '<label>Affichage :</label>';
print '<select name="show_all" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
print '<option value="no"'.(!$show_all ? ' selected' : '').'>Uniquement les trous</option>';
print '<option value="yes"'.($show_all ? ' selected' : '').'>Toutes les factures</option>';
print '</select>';
print '</div>';

print '<div>';
print '<input type="submit" value="Filtrer" class="button" style="padding: 8px 20px;">';
print '</div>';
print '</form>';
print '</div>';

// Légende
print '<div class="legend">';
print '<strong>Légende :</strong> ';
print '<span><span class="color-box" style="background: #fff3cd;"></span>Trou dans la numérotation</span>';
print '<span><span class="color-box" style="background: #f8d7da;"></span>Facture supprimée</span>';
print '<span><span class="color-box" style="background: #e3f2fd;"></span>Facture brouillon</span>';
print '<span><span class="color-box" style="background: white;"></span>Facture normale</span>';
print '</div>';

// Requête pour récupérer toutes les factures de l'année
// On extrait le numéro de la facture depuis la référence (format: FA2024-0001, FA2024-0002, etc.)
$sql = "SELECT
    f.rowid,
    f.ref,
    f.datef as date_facture,
    f.total_ttc,
    f.fk_statut as status,
    s.nom as client_name,
    SUBSTRING(f.ref, LOCATE('-', f.ref) + 1) as numero
FROM ".MAIN_DB_PREFIX."facture as f
LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = f.fk_soc
WHERE YEAR(f.datef) = ".(int)$year."
AND f.ref LIKE 'FA".(int)$year."-%'
ORDER BY CAST(SUBSTRING(f.ref, LOCATE('-', f.ref) + 1) AS UNSIGNED) ASC";

$resql = $db->query($sql);

if (!$resql) {
    print '<div class="error">Erreur lors de la récupération des factures : '.$db->lasterror().'</div>';
    llxFooter();
    exit;
}

$invoices = array();
$min_num = null;
$max_num = null;
$deleted_count = 0;
$draft_count = 0;
$validated_count = 0;

// Récupérer toutes les factures
while ($obj = $db->fetch_object($resql)) {
    $num = (int)$obj->numero;

    if ($num > 0) {
        if ($min_num === null || $num < $min_num) {
            $min_num = $num;
        }
        if ($max_num === null || $num > $max_num) {
            $max_num = $num;
        }

        $invoices[$num] = array(
            'rowid' => $obj->rowid,
            'ref' => $obj->ref,
            'date' => $obj->date_facture,
            'total_ttc' => $obj->total_ttc,
            'status' => $obj->status,
            'client' => $obj->client_name
        );

        // Compter les statuts
        if ($obj->status == 0) {
            $draft_count++;
        } elseif ($obj->status == 1 || $obj->status == 2) {
            $validated_count++;
        }
    }
}

$db->free($resql);

// Analyser la continuité
$gaps = array();
$total_invoices = count($invoices);

if ($min_num !== null && $max_num !== null) {
    for ($i = $min_num; $i <= $max_num; $i++) {
        if (!isset($invoices[$i])) {
            $gaps[] = $i;
        }
    }
}

$gap_count = count($gaps);
$expected_count = $max_num - $min_num + 1;
$continuity_rate = $expected_count > 0 ? round(($total_invoices / $expected_count) * 100, 2) : 100;

// Afficher le résumé
print '<div class="continuity-section">';
print '<h3>📊 Résumé de l\'analyse pour '.$year.'</h3>';

$card_class = $gap_count == 0 ? 'success' : ($gap_count > 5 ? 'danger' : 'warning');

print '<div class="summary-card">';
print '<div class="value">'.$total_invoices.'</div>';
print '<div class="label">Factures trouvées</div>';
print '</div>';

if ($min_num !== null && $max_num !== null) {
    print '<div class="summary-card">';
    print '<div class="value">'.$min_num.' - '.$max_num.'</div>';
    print '<div class="label">Plage de numéros</div>';
    print '</div>';
}

print '<div class="summary-card '.$card_class.'">';
print '<div class="value">'.$gap_count.'</div>';
print '<div class="label">Trous détectés</div>';
print '</div>';

print '<div class="summary-card '.($continuity_rate >= 95 ? 'success' : ($continuity_rate >= 85 ? 'warning' : 'danger')).'">';
print '<div class="value">'.$continuity_rate.'%</div>';
print '<div class="label">Taux de continuité</div>';
print '</div>';

print '</div>';

// Afficher la répartition par statut
print '<div class="continuity-section">';
print '<h3>📈 Répartition par statut</h3>';

print '<div class="summary-card">';
print '<div class="value">'.$draft_count.'</div>';
print '<div class="label">Brouillons (statut 0)</div>';
print '</div>';

print '<div class="summary-card success">';
print '<div class="value">'.$validated_count.'</div>';
print '<div class="label">Validées (statut 1-2)</div>';
print '</div>';

print '</div>';

// Afficher les détails
if ($gap_count > 0 || $show_all) {
    print '<div class="continuity-section">';

    if ($gap_count > 0) {
        print '<h3>⚠️ Détails des trous dans la numérotation</h3>';
        print '<p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;">';
        print '<strong>'.$gap_count.' numéro(s) manquant(s) :</strong> ';

        // Grouper les trous consécutifs
        $gap_ranges = array();
        $range_start = null;
        $range_end = null;

        foreach ($gaps as $gap) {
            if ($range_start === null) {
                $range_start = $gap;
                $range_end = $gap;
            } elseif ($gap == $range_end + 1) {
                $range_end = $gap;
            } else {
                if ($range_start == $range_end) {
                    $gap_ranges[] = $range_start;
                } else {
                    $gap_ranges[] = $range_start.'-'.$range_end;
                }
                $range_start = $gap;
                $range_end = $gap;
            }
        }

        // Ajouter le dernier range
        if ($range_start !== null) {
            if ($range_start == $range_end) {
                $gap_ranges[] = $range_start;
            } else {
                $gap_ranges[] = $range_start.'-'.$range_end;
            }
        }

        print implode(', ', $gap_ranges);
        print '</p>';
    }

    if ($show_all || $gap_count > 0) {
        print '<h3>📋 Liste détaillée</h3>';
        print '<table class="invoice-table">';
        print '<thead>';
        print '<tr>';
        print '<th>N°</th>';
        print '<th>Référence</th>';
        print '<th>Date</th>';
        print '<th>Client</th>';
        print '<th>Montant TTC</th>';
        print '<th>Statut</th>';
        print '<th>Observation</th>';
        print '</tr>';
        print '</thead>';
        print '<tbody>';

        if ($min_num !== null && $max_num !== null) {
            for ($i = $min_num; $i <= $max_num; $i++) {
                if (isset($invoices[$i])) {
                    $inv = $invoices[$i];

                    // Déterminer le statut
                    $status_label = '';
                    $row_class = '';
                    switch($inv['status']) {
                        case 0:
                            $status_label = 'Brouillon';
                            $row_class = 'draft-row';
                            break;
                        case 1:
                            $status_label = 'Validée';
                            break;
                        case 2:
                            $status_label = 'Payée';
                            break;
                        case 3:
                            $status_label = 'Abandonnée';
                            $row_class = 'deleted-row';
                            break;
                        default:
                            $status_label = 'Statut '.$inv['status'];
                    }

                    // N'afficher que si show_all ou si c'est avant/après un trou
                    $show_this_row = $show_all;
                    if (!$show_this_row) {
                        // Vérifier si le numéro précédent ou suivant est un trou
                        if (in_array($i - 1, $gaps) || in_array($i + 1, $gaps)) {
                            $show_this_row = true;
                        }
                    }

                    if ($show_this_row) {
                        print '<tr class="'.$row_class.'">';
                        print '<td><strong>'.str_pad($i, 4, '0', STR_PAD_LEFT).'</strong></td>';
                        print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$inv['rowid'].'" target="_blank">'.$inv['ref'].'</a></td>';
                        print '<td>'.dol_print_date($db->jdate($inv['date']), 'day').'</td>';
                        print '<td>'.$inv['client'].'</td>';
                        print '<td class="right">'.price($inv['total_ttc']).' €</td>';
                        print '<td>'.$status_label.'</td>';
                        print '<td>-</td>';
                        print '</tr>';
                    }
                } else {
                    // Trou dans la numérotation
                    print '<tr class="gap-row">';
                    print '<td><strong>'.str_pad($i, 4, '0', STR_PAD_LEFT).'</strong></td>';
                    print '<td colspan="5" style="text-align: center; font-style: italic; color: #856404;">Numéro manquant</td>';
                    print '<td>⚠️ TROU</td>';
                    print '</tr>';
                }
            }
        }

        print '</tbody>';
        print '</table>';
    }

    print '</div>';
}

// Recommandations
if ($gap_count > 0) {
    print '<div class="continuity-section">';
    print '<h3>💡 Recommandations</h3>';
    print '<ul>';
    print '<li>Vérifiez si ces factures ont été supprimées ou si ce sont des numéros réservés mais non utilisés</li>';
    print '<li>Les factures brouillons (statut 0) réservent un numéro mais ne sont pas validées</li>';
    print '<li>Les factures abandonnées (statut 3) conservent leur numéro mais ne sont plus actives</li>';
    print '<li>Pour une conformité comptable stricte, tous les numéros devraient être utilisés ou justifiés</li>';
    print '</ul>';
    print '</div>';
}

// Message de succès si pas de trous
if ($gap_count == 0 && $total_invoices > 0) {
    print '<div class="continuity-section" style="background: #d4edda; border-color: #c3e6cb;">';
    print '<h3 style="color: #155724;">✅ Numérotation continue</h3>';
    print '<p style="color: #155724;">Aucun trou détecté dans la numérotation des factures pour l\'année '.$year.'. La continuité est parfaite !</p>';
    print '</div>';
}

llxFooter();
