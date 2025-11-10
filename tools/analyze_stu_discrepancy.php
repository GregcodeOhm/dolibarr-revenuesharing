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

// Note: On va calculer les anomalies en deux passes pour afficher l'alerte en haut
// Première passe rapide pour compter
$has_anomalies = false;
$anomaly_count = 0;

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
.kpi-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.kpi-value {
    font-size: 2em;
    font-weight: bold;
    margin: 10px 0;
}
.kpi-label {
    font-size: 0.9em;
    opacity: 0.9;
}
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
</style>';

// ====================================================================
// CALCUL PRÉLIMINAIRE DES DONNÉES POUR AFFICHER LES KPIs EN HAUT
// ====================================================================

// Factures STU - Calcul rapide
$sql_factures_kpi = "SELECT
    COUNT(DISTINCT f.rowid) as nb_factures,
    SUM(CASE WHEN f.fk_statut IN (1,2) THEN f.total_ht ELSE 0 END) as ca_valide,
    SUM(CASE WHEN f.fk_statut = 0 THEN 1 ELSE 0 END) as nb_brouillons
FROM ".MAIN_DB_PREFIX."facture f
LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
WHERE YEAR(f.datef) = ".(int)$year."
AND fe.analytique = 'STU'";

$resql_kpi = $db->query($sql_factures_kpi);
$kpi_factures = array('nb_factures' => 0, 'ca_valide' => 0, 'nb_brouillons' => 0);
if ($resql_kpi) {
    $obj = $db->fetch_object($resql_kpi);
    $kpi_factures['nb_factures'] = $obj->nb_factures;
    $kpi_factures['ca_valide'] = $obj->ca_valide;
    $kpi_factures['nb_brouillons'] = $obj->nb_brouillons;
    $db->free($resql_kpi);
}

// Contrats - Calcul rapide
$sql_contracts_kpi = "SELECT
    COUNT(*) as nb_contracts,
    SUM(CASE WHEN status >= 1 THEN amount_ht ELSE 0 END) as ca_valide,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as nb_brouillons
FROM ".MAIN_DB_PREFIX."revenuesharing_contract
WHERE YEAR(date_creation) = ".(int)$year;

$resql_kpi_c = $db->query($sql_contracts_kpi);
$kpi_contracts = array('nb_contracts' => 0, 'ca_valide' => 0, 'nb_brouillons' => 0);
if ($resql_kpi_c) {
    $obj = $db->fetch_object($resql_kpi_c);
    $kpi_contracts['nb_contracts'] = $obj->nb_contracts;
    $kpi_contracts['ca_valide'] = $obj->ca_valide;
    $kpi_contracts['nb_brouillons'] = $obj->nb_brouillons;
    $db->free($resql_kpi_c);
}

// Calcul de l'écart
$ecart_kpi = $kpi_contracts['ca_valide'] - $kpi_factures['ca_valide'];
$ecart_pct = $kpi_factures['ca_valide'] > 0 ? ($ecart_kpi / $kpi_factures['ca_valide']) * 100 : 0;

// Affichage des KPIs en haut
print '<div class="kpi-grid">';

// KPI 1: Factures STU
print '<div class="kpi-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">';
print '<div class="kpi-label">📊 FACTURES STU '.$year.'</div>';
print '<div class="kpi-value">'.price($kpi_factures['ca_valide'], 0, '', 1, -1, -1, 'EUR').'</div>';
print '<div class="kpi-label">'.$kpi_factures['nb_factures'].' facture'.($kpi_factures['nb_factures'] > 1 ? 's' : '').' validée'.($kpi_factures['nb_factures'] > 1 ? 's' : '').'</div>';
if ($kpi_factures['nb_brouillons'] > 0) {
    print '<div class="kpi-label" style="opacity: 0.7;">+ '.$kpi_factures['nb_brouillons'].' brouillon'.($kpi_factures['nb_brouillons'] > 1 ? 's' : '').'</div>';
}
print '</div>';

// KPI 2: Contrats Revenue Sharing
print '<div class="kpi-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">';
print '<div class="kpi-label">💼 CONTRATS VALIDÉS</div>';
print '<div class="kpi-value">'.price($kpi_contracts['ca_valide'], 0, '', 1, -1, -1, 'EUR').'</div>';
print '<div class="kpi-label">'.$kpi_contracts['nb_contracts'].' contrat'.($kpi_contracts['nb_contracts'] > 1 ? 's' : '').' au total</div>';
if ($kpi_contracts['nb_brouillons'] > 0) {
    print '<div class="kpi-label" style="opacity: 0.7;">'.$kpi_contracts['nb_brouillons'].' brouillon'.($kpi_contracts['nb_brouillons'] > 1 ? 's' : '').'</div>';
}
print '</div>';

// KPI 3: Écart
$ecart_color = abs($ecart_kpi) < 1 ? '#4facfe 0%, #00f2fe 100%' : ($ecart_kpi > 0 ? '#fa709a 0%, #fee140 100%' : '#30cfd0 0%, #330867 100%');
print '<div class="kpi-box" style="background: linear-gradient(135deg, '.$ecart_color.');">';
print '<div class="kpi-label">⚖️ ÉCART</div>';
print '<div class="kpi-value">'.($ecart_kpi >= 0 ? '+' : '').price($ecart_kpi, 0, '', 1, -1, -1, 'EUR').'</div>';
if (abs($ecart_kpi) < 1) {
    print '<div class="kpi-label">✅ Équilibre parfait</div>';
} else {
    print '<div class="kpi-label">'.($ecart_kpi > 0 ? 'Contrats > Factures' : 'Factures > Contrats').' ('.round(abs($ecart_pct), 1).'%)</div>';
}
print '</div>';

// KPI 4: Statut global
$status_color = abs($ecart_kpi) < 1 ? '#11998e 0%, #38ef7d 100%' : '#fc4a1a 0%, #f7b733 100%';
$status_icon = abs($ecart_kpi) < 1 ? '✅' : '⚠️';
$status_text = abs($ecart_kpi) < 1 ? 'COHÉRENT' : 'À VÉRIFIER';
print '<div class="kpi-box" style="background: linear-gradient(135deg, '.$status_color.');">';
print '<div class="kpi-label">🔍 STATUT</div>';
print '<div class="kpi-value" style="font-size: 1.5em;">'.$status_icon.'</div>';
print '<div class="kpi-label" style="font-size: 1.1em; font-weight: bold;">'.$status_text.'</div>';
if (abs($ecart_kpi) >= 1) {
    print '<div class="kpi-label" style="opacity: 0.9;">Analyse requise</div>';
}
print '</div>';

print '</div>';

// ====================================================================
// PARTIE 1: DÉBOGAGE AVANCÉ - VUE D'ENSEMBLE (anciennement PARTIE 6)
// ====================================================================
print '<div class="analysis-section">';
print '<h3>🔬 PARTIE 1: Vue d\'ensemble et analyse de l\'écart</h3>';
print '<p>Cette section affiche immédiatement les informations clés pour identifier l\'origine des différences.</p>';

// NOTE: Le contenu détaillé est calculé et affiché en fin de page
// Pour l'instant on affiche un résumé rapide basé sur les KPIs déjà calculés

print '<div style="background: #e1f5fe; border: 2px solid #0277bd; border-radius: 5px; padding: 20px; margin: 15px 0;">';
print '<h4 style="color: #01579b;">📊 Résumé rapide</h4>';
print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td><strong>Factures STU validées + payées</strong></td>';
print '<td class="right"><strong style="font-size: 1.2em; color: #1976d2;">'.price($kpi_factures['ca_valide'], 0, '', 1, -1, -1, 'EUR').'</strong></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td><strong>Contrats revenuesharing validés</strong></td>';
print '<td class="right"><strong style="font-size: 1.2em; color: #f57c00;">'.price($kpi_contracts['ca_valide'], 0, '', 1, -1, -1, 'EUR').'</strong></td>';
print '</tr>';
print '<tr class="liste_titre" style="background: '.($ecart_kpi >= 0 ? '#ffebee' : '#e8f5e9').'">';
print '<td><strong>ÉCART TOTAL</strong></td>';
print '<td class="right"><strong style="font-size: 1.4em; color: '.($ecart_kpi >= 0 ? '#d32f2f' : '#388e3c').';">'.price($ecart_kpi, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
print '</tr>';
print '</table>';

if (abs($ecart_kpi) >= 1) {
    print '<div style="background: #fff3e0; padding: 15px; border-radius: 5px; margin-top: 15px;">';
    print '<strong style="color: #e65100;">⚠️ Un écart a été détecté</strong><br>';
    print 'Consultez les parties suivantes pour identifier les anomalies:<br>';
    print '• <a href="#partie2">PARTIE 2</a>: Liste des factures STU<br>';
    print '• <a href="#partie3">PARTIE 3</a>: Liste des contrats<br>';
    print '• <a href="#partie6">PARTIE 6</a>: Détail des problèmes à corriger<br>';
    print '</div>';
} else {
    print '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 15px; color: #155724;">';
    print '<strong>✅ Aucun écart détecté!</strong><br>';
    print 'Les factures STU et les contrats sont parfaitement alignés.';
    print '</div>';
}
print '</div>';

// ====================================================================
// PARTIE 2: Factures clients avec analytique STU (anciennement PARTIE 1)
// ====================================================================
print '</div>';
print '<div class="analysis-section" id="partie2">';
print '<h3>📊 PARTIE 2: Factures clients avec analytique = STU</h3>';

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
// PARTIE 3: Données du module revenuesharing
// ====================================================================
print '<div class="analysis-section">';
print '<h3>💼 PARTIE 3: Contrats revenuesharing pour STU</h3>';

$sql_contracts = "SELECT
    c.rowid,
    c.ref,
    c.fk_facture,
    f.ref as facture_ref,
    c.amount_ht,
    c.studio_amount_ht,
    c.collaborator_amount_ht,
    c.status,
    c.date_creation,
    col.label as collaborator_name
FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator col ON col.rowid = c.fk_collaborator
LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = c.fk_facture
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
        print '<td>'.($contract->facture_ref ? '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$contract->fk_facture.'" target="_blank">'.$contract->facture_ref.'</a>' : '<em>N/A</em>').'</td>';
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
// PARTIE 4: Comparaison et analyse
// ====================================================================
print '<div class="analysis-section">';
print '<h3>🔬 PARTIE 4: Analyse comparative</h3>';

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
// PARTIE 5: Correspondance facture <-> contrat
// ====================================================================
print '<div class="analysis-section">';
print '<h3>🔗 PARTIE 5: Vérification des correspondances</h3>';

print '<p>Analyse de la correspondance entre factures STU et contrats revenuesharing basée sur invoice_ref...</p>';

// Créer un mapping facture_ref -> contract
$contract_by_invoice = array();
foreach ($contracts as $contract) {
    if (!empty($contract->facture_ref)) {
        if (!isset($contract_by_invoice[$contract->facture_ref])) {
            $contract_by_invoice[$contract->facture_ref] = array();
        }
        $contract_by_invoice[$contract->facture_ref][] = $contract;
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
$total_anomalies = count($factures_sans_contrat) + count($factures_avec_contrat_multiple) + count($factures_avec_difference);

// Alerte globale en haut si anomalies détectées
if ($total_anomalies > 0) {
    print '<div style="position: sticky; top: 10px; z-index: 1000; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; border: 3px solid #c92a2a; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.3); animation: pulse 2s infinite;">';
    print '<div style="display: flex; align-items: center; gap: 20px;">';
    print '<div style="font-size: 3em;">🚨</div>';
    print '<div style="flex: 1;">';
    print '<h2 style="margin: 0; color: white; font-size: 1.5em;">ATTENTION: '.$total_anomalies.' anomalie'.($total_anomalies > 1 ? 's' : '').' détectée'.($total_anomalies > 1 ? 's' : '').' !</h2>';
    print '<p style="margin: 5px 0 0 0; font-size: 1.1em;">';
    if (count($factures_sans_contrat) > 0) {
        print '• <strong>'.count($factures_sans_contrat).'</strong> facture'.( count($factures_sans_contrat) > 1 ? 's' : '').' sans contrat &nbsp; ';
    }
    if (count($factures_avec_contrat_multiple) > 0) {
        print '• <strong>'.count($factures_avec_contrat_multiple).'</strong> doublon'.( count($factures_avec_contrat_multiple) > 1 ? 's' : '').' &nbsp; ';
    }
    if (count($factures_avec_difference) > 0) {
        print '• <strong>'.count($factures_avec_difference).'</strong> différence'.( count($factures_avec_difference) > 1 ? 's' : '').' de montant';
    }
    print '</p>';
    print '</div>';
    print '<div><a href="#partie6" style="background: white; color: #c92a2a; padding: 15px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 1.1em; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">⬇️ Voir les détails</a></div>';
    print '</div>';
    print '</div>';

    print '<style>
    @keyframes pulse {
        0%, 100% { box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        50% { box-shadow: 0 4px 20px rgba(255,107,107,0.6), 0 0 30px rgba(255,107,107,0.4); }
    }
    </style>';
}

print '<div style="background: #ffebee; padding: 15px; border-radius: 5px; margin-top: 20px;">';
print '<strong>🚨 Anomalies détectées :</strong><br><br>';
print '• Factures STU sans contrat: <strong>'.count($factures_sans_contrat).'</strong><br>';
print '• Factures avec multiples contrats: <strong>'.count($factures_avec_contrat_multiple).'</strong><br>';
print '• Factures avec différence de montant: <strong>'.count($factures_avec_difference).'</strong><br>';
print '<br><strong style="font-size: 1.2em;">Total anomalies: '.$total_anomalies.'</strong>';
print '</div>';

print '</div>';

// ====================================================================
// PARTIE 6: ALERTES ET DÉTAILS DES PROBLÈMES
// ====================================================================
if ($total_anomalies > 0) {
    print '<div class="analysis-section" id="partie6">';
    print '<h3>⚠️ PARTIE 6: Détail des problèmes à corriger</h3>';

    // Section 1: Factures sans contrat
    if (count($factures_sans_contrat) > 0) {
        print '<div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 5px; padding: 15px; margin-bottom: 20px;">';
        print '<h4 style="color: #856404; margin-top: 0;">❌ FACTURES SANS CONTRAT ('.count($factures_sans_contrat).')</h4>';
        print '<p>Ces factures STU validées/payées n\'ont pas de contrat revenuesharing associé. Cela crée un écart dans le CA.</p>';

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>Réf Facture</th>';
        print '<th>Date</th>';
        print '<th>Client</th>';
        print '<th>Intervenant</th>';
        print '<th class="right">Montant HT</th>';
        print '<th class="center">Action</th>';
        print '</tr>';

        $total_manquant = 0;
        foreach ($factures_sans_contrat as $fac) {
            $total_manquant += $fac->total_ht;
            print '<tr class="oddeven highlight-red">';
            print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$fac->rowid.'" target="_blank"><strong>'.$fac->ref.'</strong></a></td>';
            print '<td>'.dol_print_date($db->jdate($fac->datef), 'day').'</td>';
            print '<td>'.$fac->client_nom.'</td>';
            print '<td>'.($fac->intervenant ? $fac->intervenant : '<em>Non défini</em>').'</td>';
            print '<td class="right"><strong style="color: #d32f2f;">'.price($fac->total_ht, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
            print '<td class="center"><a href="'.DOL_URL_ROOT.'/custom/revenuesharing/contract_card_complete.php?action=create&facid='.$fac->rowid.'" class="button" target="_blank">Créer contrat</a></td>';
            print '</tr>';
        }

        print '<tr class="liste_titre">';
        print '<td colspan="4" class="right"><strong>TOTAL MANQUANT:</strong></td>';
        print '<td class="right"><strong style="font-size: 1.2em; color: #d32f2f;">'.price($total_manquant, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
        print '<td></td>';
        print '</tr>';
        print '</table>';
        print '</div>';
    }

    // Section 2: Factures avec multiples contrats (doublons)
    if (count($factures_avec_contrat_multiple) > 0) {
        print '<div style="background: #ffe0b2; border: 2px solid #ff9800; border-radius: 5px; padding: 15px; margin-bottom: 20px;">';
        print '<h4 style="color: #e65100; margin-top: 0;">🔁 DOUBLONS - FACTURES AVEC MULTIPLES CONTRATS ('.count($factures_avec_contrat_multiple).')</h4>';
        print '<p>Ces factures ont plusieurs contrats associés. Cela peut créer un CA gonflé si les contrats sont tous validés.</p>';

        foreach ($factures_avec_contrat_multiple as $fac) {
            $contracts_linked = $contract_by_invoice[$fac->ref];
            $total_contracts_ht = 0;
            foreach ($contracts_linked as $c) {
                $total_contracts_ht += $c->amount_ht;
            }

            print '<div style="background: white; padding: 10px; margin-bottom: 10px; border-left: 4px solid #ff9800;">';
            print '<strong>Facture: <a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$fac->rowid.'" target="_blank">'.$fac->ref.'</a></strong> - ';
            print 'Montant facture: <strong>'.price($fac->total_ht, 0, '', 1, -1, -1, 'EUR').'</strong><br>';
            print '<strong style="color: #e65100;">⚠️ '.count($contracts_linked).' contrats trouvés (total: '.price($total_contracts_ht, 0, '', 1, -1, -1, 'EUR').')</strong><br>';

            print '<table class="noborder" style="width: 100%; margin-top: 10px;">';
            print '<tr class="liste_titre" style="font-size: 0.9em;">';
            print '<th>Réf Contrat</th>';
            print '<th>Collaborateur</th>';
            print '<th class="right">Montant HT</th>';
            print '<th class="center">Statut</th>';
            print '<th class="center">Action</th>';
            print '</tr>';

            foreach ($contracts_linked as $contract) {
                $statut_libelle = '';
                $rowclass = '';
                if ($contract->status == 0) {
                    $statut_libelle = '📝 Brouillon';
                    $rowclass = 'highlight-orange';
                } elseif ($contract->status == 1) {
                    $statut_libelle = '✅ Validé';
                } elseif ($contract->status >= 2) {
                    $statut_libelle = '💰 Payé';
                }

                print '<tr class="oddeven '.$rowclass.'" style="font-size: 0.9em;">';
                print '<td><a href="'.DOL_URL_ROOT.'/custom/revenuesharing/contract_card_complete.php?id='.$contract->rowid.'" target="_blank">'.$contract->ref.'</a></td>';
                print '<td>'.($contract->collaborator_name ? $contract->collaborator_name : 'N/A').'</td>';
                print '<td class="right">'.price($contract->amount_ht, 0, '', 1, -1, -1, 'EUR').'</td>';
                print '<td class="center">'.$statut_libelle.'</td>';
                print '<td class="center">';
                if ($contract->status == 0) {
                    print '<a href="'.DOL_URL_ROOT.'/custom/revenuesharing/contract_card_complete.php?id='.$contract->rowid.'&action=delete" class="button" style="background: #d32f2f; color: white;" target="_blank">Supprimer</a>';
                } else {
                    print '<em style="color: #666;">Validé</em>';
                }
                print '</td>';
                print '</tr>';
            }
            print '</table>';
            print '</div>';
        }
        print '</div>';
    }

    // Section 3: Factures avec différence de montant
    if (count($factures_avec_difference) > 0) {
        print '<div style="background: #e1f5fe; border: 2px solid #03a9f4; border-radius: 5px; padding: 15px; margin-bottom: 20px;">';
        print '<h4 style="color: #01579b; margin-top: 0;">⚖️ DIFFÉRENCES DE MONTANT ('.count($factures_avec_difference).')</h4>';
        print '<p>Le montant du contrat ne correspond pas exactement au montant de la facture.</p>';

        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>Réf Facture</th>';
        print '<th class="right">Montant Facture</th>';
        print '<th class="right">Montant Contrat(s)</th>';
        print '<th class="right">Écart</th>';
        print '<th class="center">Action</th>';
        print '</tr>';

        foreach ($factures_avec_difference as $fac) {
            $contracts_linked = $contract_by_invoice[$fac->ref];
            $total_contracts_ht = 0;
            foreach ($contracts_linked as $c) {
                $total_contracts_ht += $c->amount_ht;
            }
            $ecart = $total_contracts_ht - $fac->total_ht;

            print '<tr class="oddeven highlight-orange">';
            print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$fac->rowid.'" target="_blank">'.$fac->ref.'</a></td>';
            print '<td class="right">'.price($fac->total_ht, 0, '', 1, -1, -1, 'EUR').'</td>';
            print '<td class="right">'.price($total_contracts_ht, 0, '', 1, -1, -1, 'EUR').'</td>';
            print '<td class="right"><strong style="color: '.($ecart > 0 ? '#f44336' : '#4CAF50').';">'.price($ecart, 0, '', 1, -1, -1, 'EUR').'</strong></td>';
            print '<td class="center">';
            if (count($contracts_linked) == 1) {
                print '<a href="'.DOL_URL_ROOT.'/custom/revenuesharing/contract_card_complete.php?id='.$contracts_linked[0]->rowid.'&action=edit" class="button" target="_blank">Corriger</a>';
            } else {
                print '<em>Multiples contrats</em>';
            }
            print '</td>';
            print '</tr>';
        }
        print '</table>';
        print '</div>';
    }

    // Récapitulatif des actions à mener
    print '<div style="background: #f3e5f5; border: 2px solid #9c27b0; border-radius: 5px; padding: 15px;">';
    print '<h4 style="color: #4a148c; margin-top: 0;">📋 PLAN D\'ACTION RECOMMANDÉ</h4>';
    print '<ol style="line-height: 1.8;">';

    if (count($factures_sans_contrat) > 0) {
        print '<li><strong>Créer les contrats manquants</strong> pour les '.count($factures_sans_contrat).' factures STU sans contrat (cliquez sur "Créer contrat")</li>';
    }

    if (count($factures_avec_contrat_multiple) > 0) {
        print '<li><strong>Supprimer les doublons</strong> : Identifiez le bon contrat et supprimez les '.count($factures_avec_contrat_multiple).' autres (brouillons uniquement)</li>';
    }

    if (count($factures_avec_difference) > 0) {
        print '<li><strong>Corriger les montants</strong> des '.count($factures_avec_difference).' contrats qui ne correspondent pas aux factures</li>';
    }

    print '<li><strong>Vérifier régulièrement</strong> cet outil après chaque import de factures STU</li>';
    print '<li><strong>Actualiser les stats</strong> du dashboard après les corrections (bouton "🔄 Actualiser les stats")</li>';
    print '</ol>';
    print '</div>';

    print '</div>';
} else {
    print '<div class="analysis-section" style="background: #d4edda; border: 2px solid #28a745; color: #155724;">';
    print '<h3 style="margin-top: 0;">✅ Aucun problème détecté !</h3>';
    print '<p style="font-size: 1.1em;">Toutes les factures STU ont un contrat correspondant avec les bons montants. Le CA est cohérent.</p>';
    print '</div>';
}

// L'ancienne PARTIE 6 a été supprimée car son contenu essentiel est maintenant dans la PARTIE 1

llxFooter();
$db->close();
