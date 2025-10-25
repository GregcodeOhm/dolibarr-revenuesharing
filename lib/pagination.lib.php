<?php
/**
 * pagination.lib.php
 * Helper pour la génération de la pagination
 * Module Revenue Sharing - Dolibarr
 */

/**
 * Génère le HTML de la pagination
 * @param int $currentPage Page actuelle
 * @param int $totalPages Nombre total de pages
 * @param int $total Nombre total d'éléments
 * @param int $perPage Éléments par page
 * @param string $baseUrl URL de base (sans paramètres)
 * @param array $params Paramètres additionnels à inclure dans l'URL
 * @return string HTML de la pagination
 */
function generatePagination($currentPage, $totalPages, $total, $perPage, $baseUrl, $params = [])
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<div class="pagination-container" style="margin: 20px 0; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 8px;">';

    // Informations
    $start = (($currentPage - 1) * $perPage) + 1;
    $end = min($currentPage * $perPage, $total);

    $html .= '<div class="pagination-info">';
    $html .= '<span style="color: #495057;">Affichage de <strong>'.$start.'</strong> à <strong>'.$end.'</strong> sur <strong>'.$total.'</strong> transactions</span>';
    $html .= '</div>';

    // Navigation
    $html .= '<div class="pagination-nav" style="display: flex; gap: 5px; align-items: center;">';

    // Bouton Première page
    if ($currentPage > 1) {
        $html .= '<a href="'.buildPaginationUrl($baseUrl, $params, 1).'" class="btn-page" style="padding: 6px 12px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;" title="Première page">««</a>';
        $html .= '<a href="'.buildPaginationUrl($baseUrl, $params, $currentPage - 1).'" class="btn-page" style="padding: 6px 12px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;" title="Page précédente">«</a>';
    }

    // Pages
    $range = 2; // Nombre de pages à afficher avant/après la page courante
    $start_page = max(1, $currentPage - $range);
    $end_page = min($totalPages, $currentPage + $range);

    // Première page si on est loin
    if ($start_page > 1) {
        $html .= '<a href="'.buildPaginationUrl($baseUrl, $params, 1).'" class="btn-page" style="padding: 6px 12px; background: white; color: #007cba; text-decoration: none; border: 1px solid #007cba; border-radius: 4px; font-size: 0.9em;">1</a>';
        if ($start_page > 2) {
            $html .= '<span style="padding: 6px 12px;">...</span>';
        }
    }

    // Pages autour de la page courante
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="btn-page active" style="padding: 6px 12px; background: #28a745; color: white; border-radius: 4px; font-weight: bold; font-size: 0.9em;">'.$i.'</span>';
        } else {
            $html .= '<a href="'.buildPaginationUrl($baseUrl, $params, $i).'" class="btn-page" style="padding: 6px 12px; background: white; color: #007cba; text-decoration: none; border: 1px solid #007cba; border-radius: 4px; font-size: 0.9em;">'.$i.'</a>';
        }
    }

    // Dernière page si on est loin
    if ($end_page < $totalPages) {
        if ($end_page < $totalPages - 1) {
            $html .= '<span style="padding: 6px 12px;">...</span>';
        }
        $html .= '<a href="'.buildPaginationUrl($baseUrl, $params, $totalPages).'" class="btn-page" style="padding: 6px 12px; background: white; color: #007cba; text-decoration: none; border: 1px solid #007cba; border-radius: 4px; font-size: 0.9em;">'.$totalPages.'</a>';
    }

    // Bouton Dernière page
    if ($currentPage < $totalPages) {
        $html .= '<a href="'.buildPaginationUrl($baseUrl, $params, $currentPage + 1).'" class="btn-page" style="padding: 6px 12px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;" title="Page suivante">»</a>';
        $html .= '<a href="'.buildPaginationUrl($baseUrl, $params, $totalPages).'" class="btn-page" style="padding: 6px 12px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;" title="Dernière page">»»</a>';
    }

    $html .= '</div>';

    // Sélecteur de nombre d'éléments par page
    $html .= '<div class="pagination-perpage">';
    $html .= '<form method="GET" action="'.$baseUrl.'" style="display: flex; align-items: center; gap: 8px;">';

    // Ajouter tous les paramètres existants sauf 'limit' et 'page'
    foreach ($params as $key => $value) {
        if ($key != 'limit' && $key != 'page' && !empty($value)) {
            $html .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'">';
        }
    }

    $html .= '<label style="font-size: 0.9em; color: #495057;">Par page:</label>';
    $html .= '<select name="limit" onchange="this.form.submit()" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">';

    $perPageOptions = [10, 25, 50, 100, 200];
    foreach ($perPageOptions as $option) {
        $selected = ($option == $perPage) ? ' selected' : '';
        $html .= '<option value="'.$option.'"'.$selected.'>'.$option.'</option>';
    }

    $html .= '</select>';
    $html .= '</form>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}

/**
 * Construit l'URL pour la pagination
 * @param string $baseUrl URL de base
 * @param array $params Paramètres
 * @param int $page Numéro de page
 * @return string URL complète
 */
function buildPaginationUrl($baseUrl, $params, $page)
{
    $params['page'] = $page;
    $queryString = http_build_query($params);
    return $baseUrl . ($queryString ? '?' . $queryString : '');
}
