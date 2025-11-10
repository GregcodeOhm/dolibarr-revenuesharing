<?php
/**
 * Outil d'analyse des différences de CA pour l'analytique STU
 * Compare les factures clients (analytique STU) avec les montants du module revenuesharing
 */

$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';
require_once $dolibarr_main_document_root.'/core/lib/admin.lib.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cet outil');
}

$year = GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y');

llxHeader('', 'Analyse Différences CA STU', '');

print load_fiche_titre('🔍 Analyse des différences CA STU', '', 'generic');

// Formulaire de sélection d'année
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom: 20px;">';
print 'Année : <select name="year" onchange="this.form.submit()">';
for ($y = date('Y'); $y >= 2020; $y--) {
    print '<option value="'.$y.'"'.($y == $year ? ' selected' : '').'>'.$y.'</option>';
}
print '</select>';
print '</form>';

print '<style>
.analysis-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
}
.highlight-red {
    background-color: #ffebee;
}
.highlight-green {
    background-color: #e8f5e9;
}
.highlight-orange {
    background-color: #fff3e0;
}
</style>';

// ====================================================================
// PARTIE 1: Factures clients avec analytique STU
// ====================================================================
print '<div class="analysis-section">';
print '<h3>📊 PARTIE 1: Factures clients avec analytique = STU</h3>';

$sql_factures = "SELECT
    f.rowid,
    f.ref,
    f.datef,
    f.total_ht,
    f.total_ttc,
    f.fk_statut,
    fe.analytique,
    fe.intervenant,
    s.nom as client_nom
FROM ".MAIN_DB_PREFIX."facture f
LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
WHERE YEAR(f.datef) = ".(int)$year."
AND fe.analytique = 'STU'
ORDER BY f.datef DESC, f.ref DESC";

$resql_factures = $db->query($sql_factures);

if ($resql_factures) {
    $nb_factures = $db->num_rows($resql_factures);
    $total_ht_all = 0;
    $total_ht_validated = 0;
    $total_ht_paid = 0;
    $nb_draft = 0;
    $nb_validated = 0;
    $nb_paid = 0;

    $factures = array();

    while ($obj = $db->fetch_object($resql_factures)) {
        $factures[] = $obj;
        $total_ht_all += $obj->total_ht;

        // Statut: 0=Brouillon, 1=Validée, 2=Payée, 3=Abandonnée
        if ($obj->fk_statut == 0) {
            $nb_draft++;
        } elseif ($obj->fk_statut == 1) {
            $nb_validated++;
            $total_ht_validated += $obj->total_ht;
        } elseif ($obj->fk_statut == 2) {
            $nb_paid++;
            $total_ht_paid += $obj->total_ht;
        }
    }

    // Résumé
    print '<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
    print '<strong>Résumé des factures STU pour '.$year.' :</strong><br>';
    print '• Total factures: <strong>'.$nb_factures.'</strong><br>';
    print '• Brouillons (statut 0): '.$nb_draft.' factures<br>';
    print '• Validées (statut 1): '.$nb_validated.' factures = <strong>'.price($total_ht_validated, 0, '', 1, -1, -1, 'EUR').'</strong><br>';
    print '• Payées (statut 2): '.$nb_paid.' factures = <strong>'.price($total_ht_paid, 0, '', 1, -1, -1, 'EUR').'</strong><br>';
    print '• <strong>CA total validé + payé (statut 1 ou 2): '.price($total_ht_validated + $total_ht_paid, 0, '', 1, -1, -1, 'EUR').'</strong><br>';
    print '• CA total toutes factures confondues: '.price($total_ht_all, 0, '', 1, -1, -1, 'EUR').'<br>';
    print '</div>';

    // Tableau détaillé
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Réf</th>';
    print '<th>Date</th>';
    print '<th>Client</th>';
    print '<th>Intervenant</th>';
    print '<th class="right">Montant HT</th>';
    print '<th class="center">Statut</th>';
    print '</tr>';

    foreach ($factures as $fac) {
        $rowclass = '';
        if ($fac->fk_statut == 0) {
            $rowclass = 'highlight-orange'; // Brouillon
        } elseif ($fac->fk_statut == 1) {
            $rowclass = 'highlight-green'; // Validée
        } elseif ($fac->fk_statut == 2) {
            $rowclass = ''; // Payée (normal)
        }

        $statut_libelle = '';
        if ($fac->fk_statut == 0) $statut_libelle = '📝 Brouillon';
        elseif ($fac->fk_statut == 1) $statut_libelle = '✅ Validée';
        elseif ($fac->fk_statut == 2) $statut_libelle = '💰 Payée';
        elseif ($fac->fk_statut == 3) $statut_libelle = '❌ Abandonnée';

        print '<tr class="oddeven '.$rowclass.'">';
        print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$fac->rowid.'" target="_blank">'.$fac->ref.'</a></td>';
        print '<td>'.dol_print_date($db->jdate($fac->datef), 'day').'</td>';
        print '<td>'.$fac->client_nom.'</td>';
        print '<td>'.($fac->intervenant ? $fac->intervenant : '<em>Non défini</em>').'</td>';
        print '<td class="right"><strong>'.price($fac->total_ht, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
        print '<td class="center">'.$statut_libelle.'</td>';
        print '</tr>';
    }

    print '</table>';
    $db->free($resql_factures);
} else {
    print '<div class="error">Erreur SQL: '.$db->lasterror().'</div>';
}

print '</div>';

// ====================================================================
// PARTIE 2: Données du module revenuesharing
// ====================================================================
print '<div class="analysis-section">';
print '<h3>💼 PARTIE 2: Contrats revenuesharing pour STU</h3>';

$sql_contracts = "SELECT
    c.rowid,
    c.ref,
    c.invoice_ref,
    c.amount_ht,
    c.studio_amount_ht,
    c.collaborator_amount_ht,
    c.status,
    c.date_creation,
    col.name as collaborator_name
FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator col ON col.rowid = c.fk_collaborator
WHERE YEAR(c.date_creation) = ".(int)$year."
ORDER BY c.date_creation DESC, c.ref DESC";

$resql_contracts = $db->query($sql_contracts);

if ($resql_contracts) {
    $nb_contracts = $db->num_rows($resql_contracts);
    $total_contracts_ht = 0;
    $total_contracts_validated = 0;
    $nb_contracts_draft = 0;
    $nb_contracts_validated = 0;

    $contracts = array();

    while ($obj = $db->fetch_object($resql_contracts)) {
        $contracts[] = $obj;
        $total_contracts_ht += $obj->amount_ht;

        // Statut: 0=Brouillon, 1=Validé/Payé
        if ($obj->status == 0) {
            $nb_contracts_draft++;
        } else {
            $nb_contracts_validated++;
            $total_contracts_validated += $obj->amount_ht;
        }
    }

    // Résumé
    print '<div style="background: #fff3e0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
    print '<strong>Résumé des contrats revenuesharing pour '.$year.' :</strong><br>';
    print '• Total contrats: <strong>'.$nb_contracts.'</strong><br>';
    print '• Brouillons (status 0): '.$nb_contracts_draft.' contrats<br>';
    print '• Validés (status >= 1): '.$nb_contracts_validated.' contrats = <strong>'.price($total_contracts_validated, 0, '', 1, -1, -1, 'EUR').'</strong><br>';
    print '• CA total tous contrats: '.price($total_contracts_ht, 0, '', 1, -1, -1, 'EUR').'<br>';
    print '</div>';

    // Tableau détaillé
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Réf Contrat</th>';
    print '<th>Réf Facture</th>';
    print '<th>Date</th>';
    print '<th>Collaborateur</th>';
    print '<th class="right">Montant HT</th>';
    print '<th class="right">Part Studio</th>';
    print '<th class="right">Part Collab</th>';
    print '<th class="center">Statut</th>';
    print '</tr>';

    foreach ($contracts as $contract) {
        $rowclass = '';
        if ($contract->status == 0) {
            $rowclass = 'highlight-orange';
        }

        $statut_libelle = '';
        if ($contract->status == 0) $statut_libelle = '📝 Brouillon';
        elseif ($contract->status == 1) $statut_libelle = '✅ Validé';
        elseif ($contract->status >= 2) $statut_libelle = '💰 Payé';

        print '<tr class="oddeven '.$rowclass.'">';
        print '<td>'.$contract->ref.'</td>';
        print '<td>'.$contract->invoice_ref.'</td>';
        print '<td>'.dol_print_date($db->jdate($contract->date_creation), 'day').'</td>';
        print '<td>'.($contract->collaborator_name ? $contract->collaborator_name : '<em>N/A</em>').'</td>';
        print '<td class="right"><strong>'.price($contract->amount_ht, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
        print '<td class="right">'.price($contract->studio_amount_ht, 0, '', 1, -1, -1, 'EUR').'</td>';
        print '<td class="right">'.price($contract->collaborator_amount_ht, 0, '', 1, -1, -1, 'EUR').'</td>';
        print '<td class="center">'.$statut_libelle.'</td>';
        print '</tr>';
    }

    print '</table>';
    $db->free($resql_contracts);
} else {
    print '<div class="error">Erreur SQL: '.$db->lasterror().'</div>';
}

print '</div>';

// ====================================================================
// PARTIE 3: Comparaison et analyse
// ====================================================================
print '<div class="analysis-section">';
print '<h3>🔬 PARTIE 3: Analyse comparative</h3>';

$ca_factures_valides = $total_ht_validated + $total_ht_paid;
$ca_contracts_valides = $total_contracts_validated;
$difference = $ca_contracts_valides - $ca_factures_valides;
$difference_pct = $ca_factures_valides > 0 ? ($difference / $ca_factures_valides) * 100 : 0;

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Source</th>';
print '<th class="right">Montant HT</th>';
print '<th>Note</th>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><strong>Factures clients STU (validées + payées)</strong></td>';
print '<td class="right"><strong style="font-size: 1.3em; color: #2196F3;">'.price($ca_factures_valides, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
print '<td>Basé sur llx_facture avec analytique=STU et statut IN (1,2)</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><strong>Contrats revenuesharing (validés)</strong></td>';
print '<td class="right"><strong style="font-size: 1.3em; color: #FF9800;">'.price($ca_contracts_valides, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
print '<td>Basé sur llx_revenuesharing_contract avec status >= 1</td>';
print '</tr>';

$diff_color = $difference >= 0 ? '#f44336' : '#4CAF50';
print '<tr class="liste_titre" style="background: '.$diff_color.'; color: white;">';
print '<td><strong>DIFFÉRENCE</strong></td>';
print '<td class="right"><strong style="font-size: 1.5em;">'.price($difference, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
print '<td>'.($difference >= 0 ? 'Contracts > Factures' : 'Factures > Contracts').' ('.round($difference_pct, 2).'%)</td>';
print '</tr>';

print '</table>';

// Hypothèses d'explication
print '<div style="background: #fffde7; padding: 15px; border-radius: 5px; margin-top: 20px;">';
print '<strong>💡 Hypothèses possibles pour la différence :</strong><br><br>';

if ($difference > 0) {
    print '1. <strong>Contrats sans facture associée</strong> : Des contrats ont été créés manuellement sans lien avec une facture STU<br>';
    print '2. <strong>Factures hors STU</strong> : Des contrats sont liés à des factures avec un autre code analytique<br>';
    print '3. <strong>Doublons</strong> : Certaines factures ont été enregistrées plusieurs fois en contrats<br>';
    print '4. <strong>Années différentes</strong> : La date de création du contrat diffère de la date de facture<br>';
} else {
    print '1. <strong>Factures non converties</strong> : Des factures STU n\'ont pas encore été converties en contrats revenuesharing<br>';
    print '2. <strong>Factures en brouillon ignorées</strong> : '.price($nb_draft * 0, 0, '', 1, -1, -1, 'EUR').' de factures en brouillon non comptées<br>';
    print '3. <strong>Synchronisation manquante</strong> : Le module revenuesharing n\'a pas importé toutes les factures STU<br>';
}

print '</div>';

print '</div>';

// ====================================================================
// PARTIE 4: Correspondance facture <-> contrat
// ====================================================================
print '<div class="analysis-section">';
print '<h3>🔗 PARTIE 4: Vérification des correspondances</h3>';

print '<p>Analyse de la correspondance entre factures STU et contrats revenuesharing basée sur invoice_ref...</p>';

// Créer un mapping invoice_ref -> contract
$contract_by_invoice = array();
foreach ($contracts as $contract) {
    if (!empty($contract->invoice_ref)) {
        if (!isset($contract_by_invoice[$contract->invoice_ref])) {
            $contract_by_invoice[$contract->invoice_ref] = array();
        }
        $contract_by_invoice[$contract->invoice_ref][] = $contract;
    }
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Facture STU</th>';
print '<th class="right">Montant Facture</th>';
print '<th class="center">Contrat(s) lié(s)</th>';
print '<th class="right">Total Contrats</th>';
print '<th class="center">Statut</th>';
print '</tr>';

$factures_sans_contrat = array();
$factures_avec_contrat_multiple = array();
$factures_avec_difference = array();

foreach ($factures as $fac) {
    // Ignorer les brouillons
    if ($fac->fk_statut == 0) continue;

    $has_contract = isset($contract_by_invoice[$fac->ref]);
    $nb_contracts_linked = $has_contract ? count($contract_by_invoice[$fac->ref]) : 0;
    $total_contracts_amount = 0;

    if ($has_contract) {
        foreach ($contract_by_invoice[$fac->ref] as $c) {
            $total_contracts_amount += $c->amount_ht;
        }
    }

    $difference_fac = abs($total_contracts_amount - $fac->total_ht);

    $rowclass = '';
    $status_icon = '';

    if (!$has_contract) {
        $rowclass = 'highlight-red';
        $status_icon = '❌ Pas de contrat';
        $factures_sans_contrat[] = $fac;
    } elseif ($nb_contracts_linked > 1) {
        $rowclass = 'highlight-orange';
        $status_icon = '⚠️ Multiples contrats ('.$nb_contracts_linked.')';
        $factures_avec_contrat_multiple[] = $fac;
    } elseif ($difference_fac > 0.01) {
        $rowclass = 'highlight-orange';
        $status_icon = '⚠️ Différence: '.price($difference_fac, 0, '', 1, -1, -1, 'EUR');
        $factures_avec_difference[] = $fac;
    } else {
        $status_icon = '✅ OK';
    }

    print '<tr class="oddeven '.$rowclass.'">';
    print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$fac->rowid.'" target="_blank">'.$fac->ref.'</a></td>';
    print '<td class="right">'.price($fac->total_ht, 0, '', 1, -1, -1, 'EUR').'</td>';
    print '<td class="center">'.$nb_contracts_linked.'</td>';
    print '<td class="right">'.price($total_contracts_amount, 0, '', 1, -1, -1, 'EUR').'</td>';
    print '<td class="center">'.$status_icon.'</td>';
    print '</tr>';
}

print '</table>';

// Résumé des anomalies
print '<div style="background: #ffebee; padding: 15px; border-radius: 5px; margin-top: 20px;">';
print '<strong>🚨 Anomalies détectées :</strong><br><br>';
print '• Factures STU sans contrat: <strong>'.count($factures_sans_contrat).'</strong><br>';
print '• Factures avec multiples contrats: <strong>'.count($factures_avec_contrat_multiple).'</strong><br>';
print '• Factures avec différence de montant: <strong>'.count($factures_avec_difference).'</strong><br>';
print '</div>';

print '</div>';

llxFooter();
$db->close();
