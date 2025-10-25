<?php
/**
 * Template: Section de filtres pour account_detail.php
 * Variables requises:
 * - $id: ID du collaborateur d'origine
 * - $displayed_collaborator_id: ID du collaborateur actuellement affiché
 * - $filter_year: Année filtrée (0 = toutes)
 * - $filter_type: Type de transaction filtré (vide = tous)
 * - $show_previsionnel: Booléen pour afficher les prévisionnels
 * - $type_labels: Array des labels de types de transactions
 * - $collaboratorRepo: Repository des collaborateurs
 * - $user: Objet utilisateur Dolibarr
 */

if (!defined('MAIN_DB_PREFIX')) {
    die('Accès interdit');
}
?>

<div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 15px 0; border: 1px solid #dee2e6;">
    <h4 style="margin: 0 0 15px 0; color: #495057;">Filtres et Options</h4>

    <form method="GET" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr; gap: 15px; align-items: end;">

            <!-- Sélecteur de collaborateur -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Collaborateur</label>
                <select name="collaborator_filter" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php
                    $all_collabs = $collaboratorRepo->findAllActive();
                    foreach ($all_collabs as $collab_option) {
                        $selected = ($collab_option->rowid == $displayed_collaborator_id) ? ' selected' : '';
                        echo '<option value="'.$collab_option->rowid.'"'.$selected.'>'.dol_escape_htmltag($collab_option->label).'</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Filtre année -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Année</label>
                <select name="filter_year" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="0"<?php echo ($filter_year == 0 ? ' selected' : ''); ?>>Toutes années</option>
                    <?php
                    for ($year = date('Y'); $year >= 2020; $year--) {
                        $selected = ($year == $filter_year) ? ' selected' : '';
                        echo '<option value="'.$year.'"'.$selected.'>'.$year.'</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Filtre type de transaction -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Type transaction</label>
                <select name="filter_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Tous types</option>
                    <?php
                    foreach ($type_labels as $key => $label) {
                        $selected = ($key == $filter_type) ? ' selected' : '';
                        echo '<option value="'.$key.'"'.$selected.'>'.$label.'</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Filtre prévisionnels -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333;">Prévisionnels</label>
                <select name="show_previsionnel" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="yes"<?php echo ($show_previsionnel ? ' selected' : ''); ?>>Inclure</option>
                    <option value="no"<?php echo (!$show_previsionnel ? ' selected' : ''); ?>>Masquer</option>
                </select>
            </div>

            <!-- Bouton filtrer -->
            <div>
                <input type="submit" value="Filtrer" class="button" style="width: 100%;">
                <?php if ($user->admin): ?>
                    <br><small style="margin-top: 5px; display: block;">
                        <?php
                        $current_url = $_SERVER['REQUEST_URI'];
                        $debug_url = $current_url . (strpos($current_url, '?') !== false ? '&' : '?') . 'debug=1';
                        ?>
                        <a href="<?php echo $debug_url; ?>" style="color: #666;">Debug SQL</a>
                    </small>
                <?php endif; ?>
            </div>

        </div>
    </form>
</div>
