<?php
/**
 * Page analytique pour l'activité commerciale
 * Utilise les extrafields : intervenant et analytique
 */

// Chemin corrigé pour votre installation
$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';
require_once $dolibarr_main_document_root.'/core/lib/admin.lib.php';
require_once __DIR__.'/class/repositories/AnalyticsRepository.php';
require_once __DIR__.'/class/CacheManager.php';

// Security check - accessible aux admins
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder aux analyses');
}

// Initialiser repositories et cache
$cache = new CacheManager(null, 600, true); // 10min pour analytics
$analyticsRepo = new AnalyticsRepository($db, $cache);

// Parameters
$filter_year = GETPOST('filter_year', 'int') ? GETPOST('filter_year', 'int') : date('Y');
$filter_analytique = GETPOST('filter_analytique', 'alpha');
$filter_intervenant = GETPOST('filter_intervenant', 'alpha');
$period_type = GETPOST('period_type', 'alpha') ? GETPOST('period_type', 'alpha') : 'month';

llxHeader('', 'Analyse Activité Commerciale', '');

print load_fiche_titre('Analyse de l\'Activité Commerciale', '', 'generic');

// CSS pour les graphiques et cartes
print '<style>
.analytics-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
}
.kpi-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}
.kpi-value {
    font-size: 2.5em;
    font-weight: bold;
    margin: 10px 0;
}
.kpi-label {
    font-size: 0.9em;
    opacity: 0.9;
}
.chart-container {
    position: relative;
    height: 400px;
    margin: 20px 0;
}
.progress-bar {
    background: #f0f0f0;
    border-radius: 10px;
    height: 20px;
    overflow: hidden;
    margin: 5px 0;
}
.progress-fill {
    height: 100%;
    transition: width 0.3s ease;
}
</style>';

// Formulaire de filtres
print '<div class="analytics-card">';
print '<h3>Filtres d\'analyse</h3>';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">';

// Filtre année
print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Année :</label>';
print '<select name="filter_year" style="padding: 8px; border-radius: 4px;">';
for ($y = date('Y'); $y >= date('Y') - 5; $y--) {
    $selected = ($y == $filter_year) ? ' selected' : '';
    print '<option value="'.$y.'"'.$selected.'>'.$y.'</option>';
}
print '</select>';
print '</div>';

// Filtre analytique
print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Secteur analytique :</label>';
print '<select name="filter_analytique" style="padding: 8px; border-radius: 4px;">';
print '<option value="">Tous secteurs</option>';
$analytique_options = array(
    'STU' => ' STU (Studio)',
    'FORM' => '📚 FORM (Formation)',
    'CONS' => 'CONS (Conseil)',
    'LOC' => '📦 LOC (Location matériel)',
    'VENTE' => 'VENTE (Vente matériel)',
    'Vente Focal' => '🔊 Vente Focal',
    'IMMO' => ' IMMO (Vente immobilisation)',
    'REP' => ' REP (Répétition)',
    'LOC_IMMO' => 'LOC IMMO (Location espaces)',
    'AUTRE' => 'AUTRE (Autre)'
);
foreach ($analytique_options as $value => $label) {
    $selected = ($value == $filter_analytique) ? ' selected' : '';
    print '<option value="'.$value.'"'.$selected.'>'.$label.'</option>';
}
print '</select>';
print '</div>';

// Filtre intervenant
print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;"> Intervenant :</label>';
print '<select name="filter_intervenant" style="padding: 8px; border-radius: 4px;">';
print '<option value="">Tous intervenants</option>';
$sql_intervenants = "SELECT DISTINCT fe.intervenant
    FROM ".MAIN_DB_PREFIX."facture_extrafields fe
    LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fe.fk_object
    WHERE fe.intervenant IS NOT NULL AND fe.intervenant != '' AND YEAR(f.datef) = ".$filter_year."
    ORDER BY fe.intervenant";
$resql_int = $db->query($sql_intervenants);
if ($resql_int) {
    while ($obj_int = $db->fetch_object($resql_int)) {
        $selected = ($obj_int->intervenant == $filter_intervenant) ? ' selected' : '';
        print '<option value="'.dol_escape_htmltag($obj_int->intervenant).'"'.$selected.'>'.dol_escape_htmltag($obj_int->intervenant).'</option>';
    }
}
print '</select>';
print '</div>';

// Type de période
print '<div>';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Période :</label>';
print '<select name="period_type" style="padding: 8px; border-radius: 4px;">';
print '<option value="month"'.($period_type == 'month' ? ' selected' : '').'>Par mois</option>';
print '<option value="quarter"'.($period_type == 'quarter' ? ' selected' : '').'>Par trimestre</option>';
print '<option value="year"'.($period_type == 'year' ? ' selected' : '').'>Annuel</option>';
print '</select>';
print '</div>';

print '<div>';
print '<input type="submit" value="Analyser" class="button" style="background: #007cba; color: white; padding: 10px 20px; border-radius: 4px;">';
print '</div>';

print '</form>';
print '</div>';

// Construction de la clause WHERE pour les filtres
$where_conditions = array();
$where_conditions[] = "YEAR(f.datef) = ".$filter_year;
$where_conditions[] = "f.fk_statut IN (1,2)"; // Validées et payées seulement

if ($filter_analytique) {
    $where_conditions[] = "fe.analytique = '".$db->escape($filter_analytique)."'";
}
if ($filter_intervenant) {
    $where_conditions[] = "fe.intervenant = '".$db->escape($filter_intervenant)."'";
}

$where_clause = "WHERE ".implode(" AND ", $where_conditions);

// KPIs principaux (via repository avec cache)
try {
    $kpi_data = $analyticsRepo->getKPIs([
        'year' => $filter_year,
        'analytique' => $filter_analytique,
        'intervenant' => $filter_intervenant
    ]);
} catch (Exception $e) {
    print '<div style="background: #f8d7da; border: 1px solid #dc3545; color: #721c24; padding: 15px; margin: 20px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur KPIs:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
    $kpi_data = new stdClass();
    $kpi_data->ca_total_ht = 0;
    $kpi_data->ca_moyen = 0;
    $kpi_data->nb_factures = 0;
    $kpi_data->nb_intervenants = 0;
    $kpi_data->nb_secteurs = 0;
}

// Affichage des KPIs
print '<div class="analytics-grid">';

print '<div class="kpi-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">';
print '<div class="kpi-label">Chiffre d\'Affaires HT</div>';
print '<div class="kpi-value">'.number_format($kpi_data->ca_total_ht, 0, ',', ' ').' €</div>';
print '<div class="kpi-label">'.$kpi_data->nb_factures.' facture(s)</div>';
print '</div>';

print '<div class="kpi-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">';
print '<div class="kpi-label">Panier Moyen</div>';
print '<div class="kpi-value">'.number_format($kpi_data->ca_moyen, 0, ',', ' ').' €</div>';
print '<div class="kpi-label">Moyenne par facture</div>';
print '</div>';

print '<div class="kpi-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">';
print '<div class="kpi-label">Intervenants Actifs</div>';
print '<div class="kpi-value">'.$kpi_data->nb_intervenants.'</div>';
print '<div class="kpi-label">Collaborateurs impliqués</div>';
print '</div>';

print '<div class="kpi-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">';
print '<div class="kpi-label">Secteurs Actifs</div>';
print '<div class="kpi-value">'.$kpi_data->nb_secteurs.'</div>';
print '<div class="kpi-label">Domaines d\'activité</div>';
print '</div>';

print '</div>';

// Répartition par secteur analytique (via repository avec cache)
print '<div class="analytics-card">';
print '<h3>Répartition par Secteur d\'Activité</h3>';

try {
    $secteurs_data = $analyticsRepo->getByAnalyticsSector([
        'year' => $filter_year,
        'intervenant' => $filter_intervenant
    ]);

    $total_ca_secteurs = 0;
    foreach ($secteurs_data as $secteur) {
        $total_ca_secteurs += $secteur->ca_ht;
    }
} catch (Exception $e) {
    print '<div style="background: #f8d7da; border: 1px solid #dc3545; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur secteurs:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
    $secteurs_data = [];
    $total_ca_secteurs = 0;
}

if (!empty($secteurs_data)) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Secteur</th>';
    print '<th class="center">Nb factures</th>';
    print '<th class="center">CA HT</th>';
    print '<th class="center">% CA</th>';
    print '<th class="center">Panier moyen</th>';
    print '<th>Progression</th>';
    print '</tr>';

    foreach ($secteurs_data as $secteur) {
        $pourcentage = $total_ca_secteurs > 0 ? ($secteur->ca_ht / $total_ca_secteurs) * 100 : 0;
        $secteur_label = isset($analytique_options[$secteur->analytique]) ? $analytique_options[$secteur->analytique] : $secteur->analytique;

        // Couleurs basées sur le pourcentage
        $color = '';
        if ($pourcentage >= 30) $color = '#28a745';
        elseif ($pourcentage >= 15) $color = '#ffc107';
        elseif ($pourcentage >= 5) $color = '#fd7e14';
        else $color = '#dc3545';

        print '<tr class="oddeven">';
        print '<td><strong>'.$secteur_label.'</strong></td>';
        print '<td class="center">'.$secteur->nb_factures.'</td>';
        print '<td class="center"><strong>'.number_format($secteur->ca_ht, 0, ',', ' ').' €</strong></td>';
        print '<td class="center"><strong>'.number_format($pourcentage, 1).'%</strong></td>';
        print '<td class="center">'.number_format($secteur->ca_moyen, 0, ',', ' ').' €</td>';
        print '<td>';
        print '<div class="progress-bar">';
        print '<div class="progress-fill" style="width: '.$pourcentage.'%; background: '.$color.';"></div>';
        print '</div>';
        print '</td>';
        print '</tr>';
    }
    print '</table>';
} else {
    print '<p>Aucune donnée analytique disponible pour les critères sélectionnés.</p>';
}

print '</div>';

// Répartition par intervenant
print '<div class="analytics-card">';
print '<h3>Performance par Intervenant</h3>';

try {
    $intervenants_data = $analyticsRepo->getByIntervenant([
        'year' => $filter_year,
        'analytique' => $filter_analytique
    ]);
} catch (Exception $e) {
    print '<div style="background: #f8d7da; border: 1px solid #dc3545; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur intervenants:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
    $intervenants_data = [];
}

if (!empty($intervenants_data)) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Intervenant</th>';
    print '<th class="center">Nb factures</th>';
    print '<th class="center">CA HT</th>';
    print '<th class="center">Panier moyen</th>';
    print '<th>Performance</th>';
    print '</tr>';

    $total_ca_intervenants = 0;
    foreach ($intervenants_data as $obj) {
        $total_ca_intervenants += $obj->ca_ht;
    }

    foreach ($intervenants_data as $intervenant) {
        $pourcentage = $total_ca_intervenants > 0 ? ($intervenant->ca_ht / $total_ca_intervenants) * 100 : 0;

        print '<tr class="oddeven">';
        print '<td><strong>'.dol_escape_htmltag($intervenant->intervenant).'</strong></td>';
        print '<td class="center">'.$intervenant->nb_factures.'</td>';
        print '<td class="center"><strong>'.number_format($intervenant->ca_ht, 0, ',', ' ').' €</strong></td>';
        print '<td class="center">'.number_format($intervenant->ca_moyen, 0, ',', ' ').' €</td>';
        print '<td>';

        // Barre de performance
        $color_perf = '';
        if ($pourcentage >= 25) $color_perf = '#28a745';
        elseif ($pourcentage >= 15) $color_perf = '#17a2b8';
        elseif ($pourcentage >= 10) $color_perf = '#ffc107';
        else $color_perf = '#6c757d';

        print '<div style="display: flex; align-items: center; gap: 10px;">';
        print '<div class="progress-bar" style="width: 100px;">';
        print '<div class="progress-fill" style="width: '.min($pourcentage, 100).'%; background: '.$color_perf.';"></div>';
        print '</div>';
        print '<span style="font-size: 0.9em;">'.number_format($pourcentage, 1).'%</span>';
        print '</div>';
        print '</td>';
        print '</tr>';
    }
    print '</table>';
} else {
    print '<p>Aucune donnée d\'intervenant disponible pour les critères sélectionnés.</p>';
}

print '</div>';

// Évolution temporelle
print '<div class="analytics-card">';
print '<h3>Évolution '.($period_type == 'month' ? 'Mensuelle' : ($period_type == 'quarter' ? 'Trimestrielle' : 'Annuelle')).'</h3>';

// Requête pour l'évolution temporelle
$sql_evolution = "";
$period_format = "";
$period_label = "";

switch ($period_type) {
    case 'month':
        $sql_evolution = "SELECT
            MONTH(f.datef) as periode,
            MONTHNAME(f.datef) as periode_label,
            COUNT(f.rowid) as nb_factures,
            COALESCE(SUM(f.total_ht), 0) as ca_ht
            FROM ".MAIN_DB_PREFIX."facture f
            LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
            $where_clause
            GROUP BY MONTH(f.datef), MONTHNAME(f.datef)
            ORDER BY MONTH(f.datef)";
        break;
    case 'quarter':
        $sql_evolution = "SELECT
            QUARTER(f.datef) as periode,
            CONCAT('T', QUARTER(f.datef)) as periode_label,
            COUNT(f.rowid) as nb_factures,
            COALESCE(SUM(f.total_ht), 0) as ca_ht
            FROM ".MAIN_DB_PREFIX."facture f
            LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
            $where_clause
            GROUP BY QUARTER(f.datef)
            ORDER BY QUARTER(f.datef)";
        break;
    case 'year':
        $sql_evolution = "SELECT
            YEAR(f.datef) as periode,
            YEAR(f.datef) as periode_label,
            COUNT(f.rowid) as nb_factures,
            COALESCE(SUM(f.total_ht), 0) as ca_ht
            FROM ".MAIN_DB_PREFIX."facture f
            LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
            WHERE f.fk_statut IN (1,2)
            ".($filter_analytique ? " AND fe.analytique = '".$db->escape($filter_analytique)."'" : "")."
            ".($filter_intervenant ? " AND fe.intervenant = '".$db->escape($filter_intervenant)."'" : "")."
            GROUP BY YEAR(f.datef)
            ORDER BY YEAR(f.datef) DESC
            LIMIT 5";
        break;
}

$resql_evolution = $db->query($sql_evolution);

if ($resql_evolution && $db->num_rows($resql_evolution) > 0) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Période</th>';
    print '<th class="center">Nb factures</th>';
    print '<th class="center">CA HT</th>';
    print '<th class="center">Évolution</th>';
    print '</tr>';

    $evolution_data = array();
    while ($obj = $db->fetch_object($resql_evolution)) {
        $evolution_data[] = $obj;
    }

    $max_ca = max(array_column($evolution_data, 'ca_ht'));
    $previous_ca = null;

    foreach ($evolution_data as $periode) {
        $evolution_pct = null;
        $evolution_class = '';
        $evolution_icon = '';

        if ($previous_ca !== null && $previous_ca > 0) {
            $evolution_pct = (($periode->ca_ht - $previous_ca) / $previous_ca) * 100;
            if ($evolution_pct > 0) {
                $evolution_class = 'color: #28a745;';
                $evolution_icon = '+';
            } elseif ($evolution_pct < 0) {
                $evolution_class = 'color: #dc3545;';
                $evolution_icon = '';
            } else {
                $evolution_class = 'color: #6c757d;';
                $evolution_icon = ' ';
            }
        }

        $bar_width = $max_ca > 0 ? ($periode->ca_ht / $max_ca) * 100 : 0;

        print '<tr class="oddeven">';
        print '<td><strong>'.$periode->periode_label.'</strong></td>';
        print '<td class="center">'.$periode->nb_factures.'</td>';
        print '<td class="center"><strong>'.number_format($periode->ca_ht, 0, ',', ' ').' €</strong></td>';
        print '<td class="center">';
        if ($evolution_pct !== null) {
            print '<span style="'.$evolution_class.'">'.$evolution_icon.number_format(abs($evolution_pct), 1).'%</span>';
        } else {
            print '<span style="color: #6c757d;">-</span>';
        }
        print '</td>';
        print '</tr>';

        $previous_ca = $periode->ca_ht;
    }
    print '</table>';
} else {
    print '<p>Aucune donnée d\'évolution disponible pour les critères sélectionnés.</p>';
}

print '</div>';

// Analyse croisée Secteur × Intervenant
if (!$filter_analytique && !$filter_intervenant) {
    print '<div class="analytics-card">';
    print '<h3>Matrice Secteur × Intervenant</h3>';

    $sql_matrice = "SELECT
        fe.analytique,
        fe.intervenant,
        COUNT(f.rowid) as nb_factures,
        COALESCE(SUM(f.total_ht), 0) as ca_ht
        FROM ".MAIN_DB_PREFIX."facture f
        LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
        WHERE YEAR(f.datef) = ".$filter_year."
        AND f.fk_statut IN (1,2)
        AND fe.analytique IS NOT NULL AND fe.analytique != ''
        AND fe.intervenant IS NOT NULL AND fe.intervenant != ''
        GROUP BY fe.analytique, fe.intervenant
        HAVING ca_ht > 0
        ORDER BY fe.analytique, ca_ht DESC";

    $resql_matrice = $db->query($sql_matrice);

    if ($resql_matrice && $db->num_rows($resql_matrice) > 0) {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<th>Secteur</th>';
        print '<th>Intervenant</th>';
        print '<th class="center">Factures</th>';
        print '<th class="center">CA HT</th>';
        print '<th>Contribution</th>';
        print '</tr>';

        $current_secteur = '';
        $max_ca_matrice = 0;

        // Trouver le max pour les barres
        $temp_data = array();
        while ($obj = $db->fetch_object($resql_matrice)) {
            $temp_data[] = $obj;
            $max_ca_matrice = max($max_ca_matrice, $obj->ca_ht);
        }

        foreach ($temp_data as $obj) {
            $secteur_label = isset($analytique_options[$obj->analytique]) ? $analytique_options[$obj->analytique] : $obj->analytique;
            $bar_width = $max_ca_matrice > 0 ? ($obj->ca_ht / $max_ca_matrice) * 100 : 0;

            print '<tr class="oddeven">';

            if ($current_secteur != $obj->analytique) {
                print '<td><strong>'.$secteur_label.'</strong></td>';
                $current_secteur = $obj->analytique;
            } else {
                print '<td style="color: var(--colortextbackhmenu); font-style: italic;">↳ suite</td>';
            }

            print '<td>'.dol_escape_htmltag($obj->intervenant).'</td>';
            print '<td class="center">'.$obj->nb_factures.'</td>';
            print '<td class="center"><strong>'.number_format($obj->ca_ht, 0, ',', ' ').' €</strong></td>';
            print '<td>';
            print '<div class="progress-bar" style="width: 150px;">';
            print '<div class="progress-fill" style="width: '.$bar_width.'%; background: #007cba;"></div>';
            print '</div>';
            print '</td>';
            print '</tr>';
        }
        print '</table>';
    } else {
        print '<p>Aucune donnée croisée disponible.</p>';
    }

    print '</div>';
}

// Liens d'actions
print '<div class="tabsAction">';
print '<a href="admin/edit_extrafields.php" class="butAction">Éditer Extrafields</a>';
print '<a href="contract_list.php" class="butAction">Contrats</a>';
print '<a href="collaborator_list.php" class="butAction">Collaborateurs</a>';
print '<a href="index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>