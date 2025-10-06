<?php
// Fichier: calculateur.php
// Calculateur de remises et pourcentages collaborateur

require_once '../../main.inc.php';

// Security check
if (!$user->id) {
    accessforbidden();
}

// Vérification des permissions
$has_permission = false;
if ($user->admin) {
    $has_permission = true;
} elseif (isset($user->rights->revenuesharing)) {
    if ($user->rights->revenuesharing->read) $has_permission = true;
}

if (!$has_permission) {
    accessforbidden('Accès au module Revenue Sharing non autorisé');
}

// Parameters
$action = GETPOST('action', 'alpha');
$prix_initial = GETPOST('prix_initial', 'alpha');
$prix_cible = GETPOST('prix_cible', 'alpha');
$type_prix = GETPOST('type_prix', 'alpha'); // 'ht' ou 'ttc'
$pourcentage_collaborateur = GETPOST('pourcentage_collaborateur', 'int');

if (empty($pourcentage_collaborateur)) {
    $pourcentage_collaborateur = 60; // Valeur par défaut
}

$results = array();
$errors = array();

llxHeader('', 'Calculateur de Remises', '');

print load_fiche_titre('🧮 Calculateur de Remises et Pourcentages', '', 'generic');

// Traitement du calcul
if ($action == 'calculate' && $prix_initial && $prix_cible) {

    // Validation des données
    if (!is_numeric($prix_initial) || $prix_initial <= 0) {
        $errors[] = 'Prix initial invalide';
    }

    if (!is_numeric($prix_cible) || $prix_cible <= 0) {
        $errors[] = 'Prix cible invalide';
    }

    if (!in_array($type_prix, array('ht', 'ttc'))) {
        $errors[] = 'Type de prix invalide';
    }

    if ($pourcentage_collaborateur < 0 || $pourcentage_collaborateur > 100) {
        $errors[] = 'Pourcentage collaborateur invalide (0-100%)';
    }

    if (empty($errors)) {
        $prix_initial_num = floatval($prix_initial);
        $prix_cible_num = floatval($prix_cible);

        // Calcul du pourcentage de remise
        $montant_remise = $prix_initial_num - $prix_cible_num;
        $pourcentage_remise = ($montant_remise / $prix_initial_num) * 100;

        // Calcul de la part collaborateur (toujours en HT)
        if ($type_prix == 'ttc') {
            // Si le prix est TTC, on le convertit en HT pour calculer les parts (TVA à 20%)
            $prix_cible_ht = $prix_cible_num / 1.20;
            $part_collaborateur = ($prix_cible_ht * $pourcentage_collaborateur) / 100;
            $part_studio = $prix_cible_ht - $part_collaborateur;
        } else {
            // Si le prix est déjà HT
            $part_collaborateur = ($prix_cible_num * $pourcentage_collaborateur) / 100;
            $part_studio = $prix_cible_num - $part_collaborateur;
        }

        // Calcul du pourcentage studio
        $pourcentage_studio = 100 - $pourcentage_collaborateur;

        $results = array(
            'prix_initial' => $prix_initial_num,
            'prix_cible' => $prix_cible_num,
            'montant_remise' => $montant_remise,
            'pourcentage_remise' => $pourcentage_remise,
            'part_collaborateur' => $part_collaborateur,
            'part_studio' => $part_studio,
            'pourcentage_collaborateur' => $pourcentage_collaborateur,
            'pourcentage_studio' => $pourcentage_studio,
            'type_prix' => $type_prix
        );
    }
}

// Affichage des erreurs
if (!empty($errors)) {
    print '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    print '<h4>Erreurs de validation :</h4>';
    print '<ul>';
    foreach ($errors as $error) {
        print '<li>'.$error.'</li>';
    }
    print '</ul>';
    print '</div>';
}

// Formulaire de calcul
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">';
print '<h3 style="margin-top: 0; color: #007cba;">📊 Calculateur</h3>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="calculate">';

print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';

// Colonne gauche - Prix
print '<div>';
print '<h4 style="color: #28a745; margin-bottom: 15px;">💰 Prix</h4>';

print '<div style="margin-bottom: 15px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Prix initial :</label>';
print '<input type="number" name="prix_initial" value="'.dol_escape_htmltag($prix_initial).'" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>';
print '</div>';

print '<div style="margin-bottom: 15px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Prix cible (après remise) :</label>';
print '<input type="number" name="prix_cible" value="'.dol_escape_htmltag($prix_cible).'" step="0.01" min="0" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>';
print '</div>';

print '<div style="margin-bottom: 15px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Type de prix :</label>';
print '<select name="type_prix" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">';
print '<option value="ht"'.($type_prix == 'ht' ? ' selected' : '').'>HT (Hors Taxes)</option>';
print '<option value="ttc"'.($type_prix == 'ttc' ? ' selected' : '').'>TTC (Toutes Taxes Comprises)</option>';
print '</select>';
print '</div>';

print '</div>';

// Colonne droite - Collaborateur
print '<div>';
print '<h4 style="color: #007cba; margin-bottom: 15px;">👤 Collaborateur</h4>';

print '<div style="margin-bottom: 15px;">';
print '<label style="display: block; font-weight: bold; margin-bottom: 5px;">Pourcentage collaborateur :</label>';
print '<div style="display: flex; align-items: center; gap: 10px;">';
print '<input type="range" name="pourcentage_collaborateur" value="'.$pourcentage_collaborateur.'" min="0" max="100" step="1" style="flex: 1;" oninput="updatePercentageDisplay(this.value)">';
print '<span id="percentage_display" style="background: #e3f2fd; padding: 5px 10px; border-radius: 3px; font-weight: bold; min-width: 50px; text-align: center;">'.$pourcentage_collaborateur.'%</span>';
print '</div>';
print '<small style="color: #666; display: block; margin-top: 5px;">Utilisez le curseur pour ajuster le pourcentage</small>';
print '</div>';

print '<div style="background: #e8f5e8; border: 1px solid #4caf50; border-radius: 5px; padding: 10px;">';
print '<p style="margin: 0; color: #2e7d32;"><strong>ℹ️ Comment ça marche :</strong></p>';
print '<ol style="margin: 10px 0 0 0; color: #2e7d32;">';
print '<li>Entrez le prix initial et le prix TTC rond désiré</li>';
print '<li>Le calculateur détermine le % de remise avec 6 décimales</li>';
print '<li>Puis calcule la part du collaborateur sur le prix final</li>';
print '</ol>';
print '<p style="margin: 10px 0 0 0; color: #2e7d32; font-style: italic;"><strong>💡 Astuce :</strong> Le pourcentage affiché avec 6 décimales vous permet d\'obtenir un prix TTC exactement rond.</p>';
print '</div>';

print '</div>';

print '</div>';

print '<div class="center">';
print '<button type="submit" class="button" style="background: #28a745; color: white; padding: 10px 20px; font-size: 1.1em; border-radius: 5px;">🧮 Calculer</button>';
print '</div>';

print '</form>';
print '</div>';

// Affichage des résultats
if (!empty($results)) {
    print '<div style="background: #d4edda; border: 1px solid #4caf50; border-radius: 8px; padding: 20px; margin: 20px 0;">';
    print '<h3 style="margin-top: 0; color: #155724;">📋 Résultats du Calcul</h3>';

    $type_label = ($results['type_prix'] == 'ht') ? 'HT' : 'TTC';

    // Debug temporaire - à supprimer après test
    //print '<p style="color: red;">DEBUG: type_prix = "'.$results['type_prix'].'" | type_label = "'.$type_label.'"</p>';

    print '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';

    // Colonne gauche - Remise
    print '<div>';
    print '<h4 style="color: #dc3545; margin-bottom: 10px;">💸 Analyse de la Remise</h4>';
    print '<table class="noborder" style="width: 100%;">';
    print '<tr><td><strong>Prix initial ('.$type_label.') :</strong></td><td style="text-align: right;"><strong>'.price($results['prix_initial']).'</strong></td></tr>';
    print '<tr><td><strong>Prix cible ('.$type_label.') :</strong></td><td style="text-align: right;"><strong>'.price($results['prix_cible']).'</strong></td></tr>';
    print '<tr style="background: #fff3cd;"><td><strong>Montant de la remise :</strong></td><td style="text-align: right;"><strong>'.price($results['montant_remise']).'</strong></td></tr>';
    print '<tr style="background: #fff3cd;"><td><strong>Pourcentage de remise :</strong></td><td style="text-align: right;"><strong>'.number_format($results['pourcentage_remise'], 6, '.', '').'%</strong></td></tr>';
    print '</table>';
    print '</div>';

    // Colonne droite - Répartition (toujours en HT pour les parts)
    print '<div>';
    print '<h4 style="color: #007cba; margin-bottom: 10px;">💰 Répartition sur Prix Cible ('.$type_label.')</h4>';
    print '<small style="color: #666; margin-bottom: 10px; display: block; font-style: italic;">⚠️ Les parts collaborateur/studio sont toujours calculées en HT</small>';
    print '<table class="noborder" style="width: 100%;">';
    print '<tr style="background: #e3f2fd;"><td><strong>Part Collaborateur ('.$results['pourcentage_collaborateur'].'%) (HT) :</strong></td><td style="text-align: right;"><strong style="color: #007cba;">'.price($results['part_collaborateur']).'</strong></td></tr>';
    print '<tr style="background: #f8f9fa;"><td><strong>Part Studio ('.$results['pourcentage_studio'].'%) (HT) :</strong></td><td style="text-align: right;"><strong style="color: #28a745;">'.price($results['part_studio']).'</strong></td></tr>';
    print '<tr style="background: #e8f5e8; border-top: 2px solid #4caf50;"><td><strong>Total ('.$type_label.') :</strong></td><td style="text-align: right;"><strong>'.price($results['prix_cible']).'</strong></td></tr>';
    print '</table>';
    print '</div>';

    print '</div>';

    // Résumé visuel
    print '<div style="margin-top: 20px; background: white; border: 1px solid #28a745; border-radius: 5px; padding: 15px;">';
    print '<h4 style="text-align: center; color: #155724; margin: 0;">📊 Résumé Visuel</h4>';

    $collab_width = $results['pourcentage_collaborateur'];
    $studio_width = $results['pourcentage_studio'];

    print '<div style="display: flex; height: 40px; border-radius: 5px; overflow: hidden; margin: 10px 0; border: 2px solid #dee2e6;">';
    print '<div style="background: #007cba; color: white; width: '.$collab_width.'%; display: flex; align-items: center; justify-content: center; font-weight: bold;">Collaborateur '.$collab_width.'%</div>';
    print '<div style="background: #28a745; color: white; width: '.$studio_width.'%; display: flex; align-items: center; justify-content: center; font-weight: bold;">Studio '.$studio_width.'%</div>';
    print '</div>';

    print '<p style="text-align: center; margin: 10px 0; font-style: italic; color: #666;">Répartition sur le montant final de '.price($results['prix_cible']).' '.$type_label.'</p>';
    if ($results['type_prix'] == 'ttc') {
        print '<p style="text-align: center; margin: 5px 0; font-size: 0.9em; color: #dc3545;">⚠️ Parts calculées sur base HT (prix TTC / 1.20)</p>';
    }
    print '</div>';

    print '</div>';
}

print '<div class="tabsAction">';
print '<a href="index.php" class="butAction">'.img_picto('', 'back', 'class="pictofixedwidth"').' Dashboard</a>';
print '<a href="contract_list.php" class="butAction">'.img_picto('', 'contract', 'class="pictofixedwidth"').' Contrats</a>';
print '<a href="collaborator_list.php" class="butAction">'.img_picto('', 'user', 'class="pictofixedwidth"').' Collaborateurs</a>';
print '</div>';

?>

<script type="text/javascript">
function updatePercentageDisplay(value) {
    document.getElementById('percentage_display').textContent = value + '%';
}
</script>

<?php
llxFooter();
$db->close();
?>