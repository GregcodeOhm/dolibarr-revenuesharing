<?php
/**
 * Outil d'intégration: Créer des contrats Revenue Sharing basés sur les marges Focal
 * Récupère les marges calculées par le module MargeVentesFocal et crée des contrats
 * avec répartition 40%/60% de la marge
 */

$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';
require_once $dolibarr_main_document_root.'/core/lib/admin.lib.php';
require_once $dolibarr_main_document_root.'/core/lib/date.lib.php';
require_once __DIR__.'/../class/repositories/CollaboratorRepository.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cet outil');
}

// Check if MargeVentesFocal module is enabled
$margeventesfocal_enabled = isModEnabled('margeventesfocal');

$year = GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y');
$action = GETPOST('action', 'alpha');
$collaborator_id = GETPOST('collaborator_id', 'int');
$studio_percentage = GETPOST('studio_percentage', 'int') ? GETPOST('studio_percentage', 'int') : 40;
$facture_id = GETPOST('facture_id', 'int');

// Initialize repositories
$collaboratorRepo = new CollaboratorRepository($db);

// ACTIONS
if ($action == 'create_contract' && $facture_id > 0 && $collaborator_id > 0) {
    $db->begin();

    try {
        // Récupérer les infos de la facture et calculer la marge
        $sql_facture = "SELECT
            f.rowid, f.ref, f.datef, f.fk_soc, f.total_ht,
            s.nom as client_name,
            SUM(fd.total_ht) as vente_ht,
            SUM(fd.qty * p.pmp) as cout_pmp
        FROM ".MAIN_DB_PREFIX."facture f
        INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON f.rowid = fd.fk_facture
        INNER JOIN ".MAIN_DB_PREFIX."product p ON fd.fk_product = p.rowid
        INNER JOIN ".MAIN_DB_PREFIX."societe s ON f.fk_soc = s.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pe ON p.rowid = pe.fk_object
        WHERE f.rowid = ".(int)$facture_id."
        AND p.fk_product_type = 0
        AND (pe.focal_is_focal = 1 OR p.ref LIKE '%focal%' OR p.ref LIKE '%JMlab%')
        GROUP BY f.rowid";

        $resql_fac = $db->query($sql_facture);
        if (!$resql_fac || $db->num_rows($resql_fac) == 0) {
            throw new Exception('Facture non trouvée ou sans produits Focal');
        }

        $fac = $db->fetch_object($resql_fac);
        $marge = $fac->vente_ht - $fac->cout_pmp;
        $collab_percentage = 100 - $studio_percentage;
        $collab_amount = $marge * ($collab_percentage / 100);
        $studio_amount = $marge * ($studio_percentage / 100);

        $db->free($resql_fac);

        if ($marge <= 0) {
            throw new Exception('La marge est nulle ou négative');
        }

        // Créer le contrat
        $contract_ref = 'MF'.date('Y').'-'.sprintf('%04d', $facture_id);
        $contract_label = 'Marge Focal - '.$fac->ref.' - '.$fac->client_name;

        $sql_contract = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_contract (
            ref, label, fk_collaborator, fk_facture,
            amount_ht, collaborator_percentage, studio_amount_ht, collaborator_amount_ht, net_collaborator_amount,
            date_creation, fk_user_creat, status,
            note_private
        ) VALUES (
            '".$db->escape($contract_ref)."',
            '".$db->escape($contract_label)."',
            ".(int)$collaborator_id.",
            ".(int)$facture_id.",
            ".$marge.",
            ".$collab_percentage.",
            ".$studio_amount.",
            ".$collab_amount.",
            ".$collab_amount.",
            NOW(),
            ".(int)$user->id.",
            0,
            'Contrat créé automatiquement depuis marge Focal\\nFacture: ".$db->escape($fac->ref)."\\nVente HT: ".price($fac->vente_ht)." €\\nCoût PMP: ".price($fac->cout_pmp)." €\\nMarge: ".price($marge)." €\\nRépartition: ".$studio_percentage."% Ohmnibus / ".$collab_percentage."% Collaborateur\\nPart Studio: ".price($studio_amount)." €\\nPart Collaborateur: ".price($collab_amount)." €'
        )";

        $resql_contract = $db->query($sql_contract);
        if (!$resql_contract) {
            throw new Exception('Erreur lors de la création du contrat: '.$db->lasterror());
        }

        $contract_id = $db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_contract");

        $db->commit();

        setEventMessages('Contrat '.$contract_ref.' créé avec succès ! Montant collaborateur: '.price($collab_amount).' €', null, 'mesgs');
        header('Location: '.$_SERVER['PHP_SELF'].'?year='.$year.'&collaborator_id='.$collaborator_id.'&studio_percentage='.$studio_percentage);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        setEventMessages('Erreur: '.$e->getMessage(), null, 'errors');
    }
}

llxHeader('', 'Contrats Marge Focal', '');

print load_fiche_titre('🎯 Créer des contrats Revenue Sharing depuis les marges Focal', '', 'bill');

// Message info sur le fonctionnement
print '<div class="info" style="background: #e3f2fd; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; border-radius: 4px;">';
print '<h4 style="margin: 0 0 10px 0;">📊 Comment fonctionne cet outil</h4>';
print '<p><strong>Objectif :</strong> Créer automatiquement des contrats Revenue Sharing basés sur les marges réalisées sur les ventes de produits Focal.</p>';
print '<p><strong>Principe :</strong></p>';
print '<ul>';
print '<li>Le module <strong>Marge Focal</strong> calcule déjà les marges (vente - coût) pour chaque produit Focal vendu</li>';
print '<li>Cet outil récupère ces marges et les transforme en contrats Revenue Sharing</li>';
print '<li>Répartition par défaut : <strong>40% Ohmnibus / 60% Collaborateur</strong> (personnalisable)</li>';
print '<li>Les contrats sont créés avec le type "margin_sharing" pour les différencier</li>';
print '</ul>';
print '</div>';

// Check if Marge Focal is enabled
if (!$margeventesfocal_enabled) {
    print '<div class="error">';
    print '<h3>⚠️ Module Marge Focal non activé</h3>';
    print '<p>Le module <strong>MargeVentesFocal</strong> n\'est pas activé ou non installé.</p>';
    print '<p>Ce module est requis pour calculer les marges sur les produits Focal.</p>';
    print '<p><a href="'.DOL_URL_ROOT.'/admin/modules.php" class="button">Activer le module</a></p>';
    print '</div>';
    llxFooter();
    exit;
}

print '<style>
.margin-section {
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
table.margins-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
table.margins-table th {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 10px;
    text-align: left;
    font-weight: bold;
}
table.margins-table td {
    border: 1px solid #dee2e6;
    padding: 8px;
}
table.margins-table tr:hover {
    background: #f8f9fa;
}
.filter-form {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}
.filter-form label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}
</style>';

// Formulaire de sélection
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="filter-form">';
print '<div>';
print '<label>Année :</label>';
print '<select name="year" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
for ($y = date('Y'); $y >= 2020; $y--) {
    print '<option value="'.$y.'"'.($y == $year ? ' selected' : '').'>'.$y.'</option>';
}
print '</select>';
print '</div>';

print '<div>';
print '<label>Collaborateur :</label>';
print '<select name="collaborator_id" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">';
print '<option value="0">Tous les collaborateurs</option>';
try {
    $collaborators = $collaboratorRepo->findAllActive();
    foreach ($collaborators as $collab) {
        $selected = ($collab->rowid == $collaborator_id) ? ' selected' : '';
        print '<option value="'.$collab->rowid.'"'.$selected.'>'.dol_escape_htmltag($collab->label).'</option>';
    }
} catch (Exception $e) {
    // Ignore errors
}
print '</select>';
print '</div>';

print '<div>';
print '<label>% Studio (Ohmnibus) :</label>';
print '<input type="number" name="studio_percentage" value="'.$studio_percentage.'" min="0" max="100" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 80px;">';
print '<small style="display: block; color: #666; margin-top: 3px;">% Collaborateur = '.((100-$studio_percentage)).'%</small>';
print '</div>';

print '<div>';
print '<input type="submit" value="Filtrer" class="button" style="padding: 8px 20px;">';
print '</div>';
print '</form>';

// Requête pour récupérer les marges Focal
// On utilise la même logique que marges.php mais groupée par facture pour créer des contrats
$sql = "SELECT
    f.rowid as facture_id,
    f.ref as facture_ref,
    f.datef as facture_date,
    f.fk_soc,
    s.nom as client_name,
    SUM(fd.total_ht) as total_vente_ht,
    SUM(fd.qty * p.pmp) as total_cout_pmp,
    SUM(fd.total_ht - (fd.qty * p.pmp)) as marge_totale,
    COUNT(DISTINCT p.rowid) as nb_produits
FROM ".MAIN_DB_PREFIX."facture f
INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON f.rowid = fd.fk_facture
INNER JOIN ".MAIN_DB_PREFIX."product p ON fd.fk_product = p.rowid
INNER JOIN ".MAIN_DB_PREFIX."societe s ON f.fk_soc = s.rowid
LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pe ON p.rowid = pe.fk_object
WHERE YEAR(f.datef) = ".(int)$year."
AND f.fk_statut >= 1
AND f.entity IN (0,".$conf->entity.")
AND p.fk_product_type = 0
AND (pe.focal_is_focal = 1 OR p.ref LIKE '%focal%' OR p.ref LIKE '%JMlab%')
GROUP BY f.rowid, f.ref, f.datef, f.fk_soc, s.nom
HAVING marge_totale > 0
ORDER BY f.datef DESC";

$resql = $db->query($sql);

if (!$resql) {
    print '<div class="error">Erreur SQL : '.$db->lasterror().'</div>';
    llxFooter();
    exit;
}

$margins = array();
$total_ventes = 0;
$total_couts = 0;
$total_marges = 0;
$nb_factures = 0;

while ($obj = $db->fetch_object($resql)) {
    $margins[] = array(
        'facture_id' => $obj->facture_id,
        'facture_ref' => $obj->facture_ref,
        'facture_date' => $obj->facture_date,
        'client_name' => $obj->client_name,
        'total_vente_ht' => $obj->total_vente_ht,
        'total_cout_pmp' => $obj->total_cout_pmp,
        'marge_totale' => $obj->marge_totale,
        'nb_produits' => $obj->nb_produits
    );

    $total_ventes += $obj->total_vente_ht;
    $total_couts += $obj->total_cout_pmp;
    $total_marges += $obj->marge_totale;
    $nb_factures++;
}

$db->free($resql);

// Afficher le résumé
print '<div class="margin-section">';
print '<h3>📊 Résumé des marges Focal pour '.$year.'</h3>';

print '<div class="summary-card">';
print '<div class="value">'.$nb_factures.'</div>';
print '<div class="label">Factures avec produits Focal</div>';
print '</div>';

print '<div class="summary-card">';
print '<div class="value">'.price($total_ventes).' €</div>';
print '<div class="label">Total ventes HT</div>';
print '</div>';

print '<div class="summary-card warning">';
print '<div class="value">'.price($total_couts).' €</div>';
print '<div class="label">Total coûts (PMP)</div>';
print '</div>';

print '<div class="summary-card success">';
print '<div class="value">'.price($total_marges).' €</div>';
print '<div class="label">Marge totale</div>';
print '</div>';

$taux_marge = $total_ventes > 0 ? round(($total_marges / $total_ventes) * 100, 2) : 0;

print '<div class="summary-card success">';
print '<div class="value">'.$taux_marge.' %</div>';
print '<div class="label">Taux de marge moyen</div>';
print '</div>';

print '</div>';

// Calcul de la répartition
$collab_percentage = 100 - $studio_percentage;
$studio_part = $total_marges * ($studio_percentage / 100);
$collab_part = $total_marges * ($collab_percentage / 100);

print '<div class="margin-section">';
print '<h3>💰 Répartition avec '.$studio_percentage.'% Ohmnibus / '.$collab_percentage.'% Collaborateur</h3>';

print '<div class="summary-card warning">';
print '<div class="value">'.price($studio_part).' €</div>';
print '<div class="label">Part Ohmnibus ('.$studio_percentage.'%)</div>';
print '</div>';

print '<div class="summary-card success">';
print '<div class="value">'.price($collab_part).' €</div>';
print '<div class="label">Part Collaborateur ('.$collab_percentage.'%)</div>';
print '</div>';

print '</div>';

// Tableau détaillé
if (count($margins) > 0) {
    print '<div class="margin-section">';
    print '<h3>📋 Détail des factures ('.$nb_factures.' factures)</h3>';

    print '<table class="margins-table">';
    print '<thead>';
    print '<tr>';
    print '<th>Facture</th>';
    print '<th>Date</th>';
    print '<th>Client</th>';
    print '<th>Nb produits</th>';
    print '<th class="right">Vente HT</th>';
    print '<th class="right">Coût PMP</th>';
    print '<th class="right">Marge</th>';
    print '<th class="right">Part Studio ('.$studio_percentage.'%)</th>';
    print '<th class="right">Part Collab ('.$collab_percentage.'%)</th>';
    print '<th class="center">Action</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';

    foreach ($margins as $margin) {
        $studio_facture = $margin['marge_totale'] * ($studio_percentage / 100);
        $collab_facture = $margin['marge_totale'] * ($collab_percentage / 100);

        print '<tr>';
        print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$margin['facture_id'].'" target="_blank">'.$margin['facture_ref'].'</a></td>';
        print '<td>'.dol_print_date($db->jdate($margin['facture_date']), 'day').'</td>';
        print '<td>'.$margin['client_name'].'</td>';
        print '<td class="center">'.$margin['nb_produits'].'</td>';
        print '<td class="right">'.price($margin['total_vente_ht']).' €</td>';
        print '<td class="right">'.price($margin['total_cout_pmp']).' €</td>';
        print '<td class="right"><strong>'.price($margin['marge_totale']).' €</strong></td>';
        print '<td class="right">'.price($studio_facture).' €</td>';
        print '<td class="right"><strong>'.price($collab_facture).' €</strong></td>';
        print '<td class="center">';

        // Vérifier si un contrat existe déjà pour cette facture
        $sql_exist = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_contract WHERE fk_facture = ".(int)$margin['facture_id'];
        $resql_exist = $db->query($sql_exist);
        $has_contract = ($resql_exist && $db->num_rows($resql_exist) > 0);

        if ($has_contract) {
            $contract_existing = $db->fetch_object($resql_exist);
            print '<a href="'.dol_buildpath('/revenuesharing/contract_card_complete.php?id='.$contract_existing->rowid, 1).'" class="button smallbutton" style="background: #28a745;" title="Voir le contrat existant">✓ Contrat créé</a>';
        } elseif ($collaborator_id > 0) {
            print '<a href="'.$_SERVER['PHP_SELF'].'?action=create_contract&facture_id='.$margin['facture_id'].'&collaborator_id='.$collaborator_id.'&studio_percentage='.$studio_percentage.'&year='.$year.'&token='.newToken().'" class="button smallbutton" title="Créer un contrat Revenue Sharing">Créer contrat</a>';
        } else {
            print '<span style="color: #999; font-size: 0.9em;">Sélectionnez un collaborateur</span>';
        }

        if ($resql_exist) $db->free($resql_exist);

        print '</td>';
        print '</tr>';
    }

    print '</tbody>';
    print '<tfoot>';
    print '<tr style="background: #f8f9fa; font-weight: bold;">';
    print '<td colspan="4">TOTAL</td>';
    print '<td class="right">'.price($total_ventes).' €</td>';
    print '<td class="right">'.price($total_couts).' €</td>';
    print '<td class="right">'.price($total_marges).' €</td>';
    print '<td class="right">'.price($studio_part).' €</td>';
    print '<td class="right">'.price($collab_part).' €</td>';
    print '<td></td>';
    print '</tr>';
    print '</tfoot>';
    print '</table>';

    print '</div>';
} else {
    print '<div class="margin-section">';
    print '<p>Aucune facture avec produits Focal trouvée pour '.$year.'</p>';
    print '</div>';
}

// Message explicatif
print '<div class="margin-section" style="background: #e3f2fd;">';
print '<h4 style="margin: 0 0 10px 0;">ℹ️ Comment utiliser cet outil</h4>';
print '<ul>';
print '<li><strong>Étape 1</strong> : Sélectionnez un collaborateur dans le filtre ci-dessus</li>';
print '<li><strong>Étape 2</strong> : Ajustez le pourcentage Studio/Collaborateur si nécessaire (défaut: 40%/60%)</li>';
print '<li><strong>Étape 3</strong> : Cliquez sur "Créer contrat" pour chaque facture</li>';
print '<li>Le contrat sera créé en statut <strong>brouillon</strong></li>';
print '<li>La référence sera : <strong>MF{ANNÉE}-{ID_FACTURE}</strong> (ex: MF2025-0123)</li>';
print '<li>Le contrat contiendra :</li>';
print '<ul>';
print '<li>Montant HT = Marge totale</li>';
print '<li>Part Ohmnibus = Marge × '.$studio_percentage.'%</li>';
print '<li>Part Collaborateur = Marge × '.$collab_percentage.'%</li>';
print '<li>Lien vers la facture source</li>';
print '<li>Note privée avec détails du calcul</li>';
print '</ul>';
print '<li>Une fois validé, le contrat créera automatiquement une transaction sur le compte collaborateur</li>';
print '<li>Les contrats déjà créés sont marqués avec un ✓ vert</li>';
print '</ul>';
print '</div>';

llxFooter();
?>