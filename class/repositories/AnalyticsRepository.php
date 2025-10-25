<?php
/**
 * AnalyticsRepository.php
 * Repository pour les analyses statistiques et KPIs
 * Module Revenue Sharing - Dolibarr
 */

class AnalyticsRepository
{
    /** @var DoliDB */
    private $db;

    /** @var CacheManager|null */
    private $cache;

    /**
     * Constructor
     * @param DoliDB $db Database handler
     * @param CacheManager|null $cache Cache manager (optional)
     */
    public function __construct($db, $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * KPIs principaux (CA, nb factures, panier moyen)
     * @param array $filters ['year', 'analytique', 'intervenant']
     * @return object|false Objet avec les KPIs
     */
    public function getKPIs($filters = [])
    {
        $cacheKey = "analytics_kpis_".md5(serialize($filters));
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) return $cached;
        }

        $sql = "SELECT COUNT(DISTINCT f.rowid) as nb_factures,
                COALESCE(SUM(f.total_ht), 0) as ca_total_ht,
                COALESCE(AVG(f.total_ht), 0) as ca_moyen,
                COUNT(DISTINCT fe.intervenant) as nb_intervenants,
                COUNT(DISTINCT fe.analytique) as nb_secteurs
                FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE f.fk_statut IN (1,2)";

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(f.datef) = ".(int)$filters['year'];
        }

        if (!empty($filters['analytique'])) {
            $sql .= " AND fe.analytique = '".$this->db->escape($filters['analytique'])."'";
        }

        if (!empty($filters['intervenant'])) {
            $sql .= " AND fe.intervenant = '".$this->db->escape($filters['intervenant'])."'";
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL KPIs: ".$this->db->lasterror());
        }

        $data = $this->db->fetch_object($resql);
        $this->db->free($resql);

        if ($this->cache && $data) {
            $this->cache->set($cacheKey, $data, 600); // 10min
        }

        return $data;
    }

    /**
     * Répartition par secteur analytique
     * @param array $filters ['year', 'intervenant']
     * @return array Liste des secteurs avec stats
     */
    public function getByAnalyticsSector($filters = [])
    {
        $cacheKey = "analytics_sectors_".md5(serialize($filters));
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) return $cached;
        }

        $sql = "SELECT fe.analytique,
                COUNT(DISTINCT f.rowid) as nb_factures,
                COALESCE(SUM(f.total_ht), 0) as ca_ht,
                COALESCE(AVG(f.total_ht), 0) as ca_moyen
                FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE f.fk_statut IN (1,2)
                AND fe.analytique IS NOT NULL AND fe.analytique != ''";

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(f.datef) = ".(int)$filters['year'];
        }

        if (!empty($filters['intervenant'])) {
            $sql .= " AND fe.intervenant = '".$this->db->escape($filters['intervenant'])."'";
        }

        $sql .= " GROUP BY fe.analytique
                  ORDER BY ca_ht DESC";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL secteurs: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $results[] = $obj;
        }
        $this->db->free($resql);

        if ($this->cache) {
            $this->cache->set($cacheKey, $results, 600);
        }

        return $results;
    }

    /**
     * Répartition par intervenant
     * @param array $filters ['year', 'analytique']
     * @return array Liste des intervenants avec stats
     */
    public function getByIntervenant($filters = [])
    {
        $cacheKey = "analytics_intervenants_".md5(serialize($filters));
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) return $cached;
        }

        $sql = "SELECT fe.intervenant,
                COUNT(DISTINCT f.rowid) as nb_factures,
                COALESCE(SUM(f.total_ht), 0) as ca_ht,
                COALESCE(AVG(f.total_ht), 0) as ca_moyen
                FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE f.fk_statut IN (1,2)
                AND fe.intervenant IS NOT NULL AND fe.intervenant != ''";

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(f.datef) = ".(int)$filters['year'];
        }

        if (!empty($filters['analytique'])) {
            $sql .= " AND fe.analytique = '".$this->db->escape($filters['analytique'])."'";
        }

        $sql .= " GROUP BY fe.intervenant
                  ORDER BY ca_ht DESC";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL intervenants: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $results[] = $obj;
        }
        $this->db->free($resql);

        if ($this->cache) {
            $this->cache->set($cacheKey, $results, 600);
        }

        return $results;
    }

    /**
     * Évolution temporelle (mois/trimestre/année)
     * @param array $filters ['year', 'period_type' => 'month'|'quarter'|'year', 'analytique', 'intervenant']
     * @return array Liste des périodes avec stats
     */
    public function getEvolution($filters = [])
    {
        $cacheKey = "analytics_evolution_".md5(serialize($filters));
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) return $cached;
        }

        $period_type = isset($filters['period_type']) ? $filters['period_type'] : 'month';

        // Définir le format de période selon le type
        if ($period_type == 'month') {
            $period_select = "DATE_FORMAT(f.datef, '%Y-%m') as periode, DATE_FORMAT(f.datef, '%Y-%m') as periode_label";
            $period_group = "DATE_FORMAT(f.datef, '%Y-%m')";
        } elseif ($period_type == 'quarter') {
            $period_select = "CONCAT(YEAR(f.datef), '-Q', QUARTER(f.datef)) as periode, CONCAT(YEAR(f.datef), '-Q', QUARTER(f.datef)) as periode_label";
            $period_group = "YEAR(f.datef), QUARTER(f.datef)";
        } else { // year
            $period_select = "YEAR(f.datef) as periode, YEAR(f.datef) as periode_label";
            $period_group = "YEAR(f.datef)";
        }

        $sql = "SELECT ".$period_select.",
                COUNT(DISTINCT f.rowid) as nb_factures,
                COALESCE(SUM(f.total_ht), 0) as ca_ht,
                COALESCE(AVG(f.total_ht), 0) as ca_moyen
                FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE f.fk_statut IN (1,2)";

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(f.datef) = ".(int)$filters['year'];
        }

        if (!empty($filters['analytique'])) {
            $sql .= " AND fe.analytique = '".$this->db->escape($filters['analytique'])."'";
        }

        if (!empty($filters['intervenant'])) {
            $sql .= " AND fe.intervenant = '".$this->db->escape($filters['intervenant'])."'";
        }

        $sql .= " GROUP BY ".$period_group."
                  ORDER BY periode";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL évolution: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $results[] = $obj;
        }
        $this->db->free($resql);

        if ($this->cache) {
            $this->cache->set($cacheKey, $results, 600);
        }

        return $results;
    }

    /**
     * Matrice croisée Secteur × Intervenant
     * @param array $filters ['year']
     * @return array Matrice avec secteurs et intervenants
     */
    public function getMatrix($filters = [])
    {
        $cacheKey = "analytics_matrix_".md5(serialize($filters));
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) return $cached;
        }

        $sql = "SELECT fe.analytique, fe.intervenant,
                COUNT(DISTINCT f.rowid) as nb_factures,
                COALESCE(SUM(f.total_ht), 0) as ca_ht
                FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE f.fk_statut IN (1,2)
                AND fe.analytique IS NOT NULL AND fe.analytique != ''
                AND fe.intervenant IS NOT NULL AND fe.intervenant != ''";

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(f.datef) = ".(int)$filters['year'];
        }

        $sql .= " GROUP BY fe.analytique, fe.intervenant
                  ORDER BY fe.analytique, ca_ht DESC";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL matrice: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            if (!isset($results[$obj->analytique])) {
                $results[$obj->analytique] = [];
            }
            $results[$obj->analytique][$obj->intervenant] = $obj;
        }
        $this->db->free($resql);

        if ($this->cache) {
            $this->cache->set($cacheKey, $results, 600);
        }

        return $results;
    }

    /**
     * Top clients par CA
     * @param array $filters ['year', 'analytique', 'intervenant', 'limit']
     * @return array Liste des clients avec stats
     */
    public function getTopClients($filters = [])
    {
        $cacheKey = "analytics_top_clients_".md5(serialize($filters));
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) return $cached;
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 10;

        $sql = "SELECT s.rowid, s.nom as client_name,
                COUNT(DISTINCT f.rowid) as nb_factures,
                COALESCE(SUM(f.total_ht), 0) as ca_ht,
                COALESCE(AVG(f.total_ht), 0) as ca_moyen
                FROM ".MAIN_DB_PREFIX."facture f
                LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
                LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fe ON fe.fk_object = f.rowid
                WHERE f.fk_statut IN (1,2)";

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(f.datef) = ".(int)$filters['year'];
        }

        if (!empty($filters['analytique'])) {
            $sql .= " AND fe.analytique = '".$this->db->escape($filters['analytique'])."'";
        }

        if (!empty($filters['intervenant'])) {
            $sql .= " AND fe.intervenant = '".$this->db->escape($filters['intervenant'])."'";
        }

        $sql .= " GROUP BY s.rowid
                  ORDER BY ca_ht DESC
                  LIMIT ".$limit;

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL top clients: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $results[] = $obj;
        }
        $this->db->free($resql);

        if ($this->cache) {
            $this->cache->set($cacheKey, $results, 600);
        }

        return $results;
    }

    /**
     * Statistiques comparatives année N vs N-1
     * @param array $filters ['year', 'analytique', 'intervenant']
     * @return object Objet avec comparaison année N vs N-1
     */
    public function getYearComparison($filters = [])
    {
        $cacheKey = "analytics_comparison_".md5(serialize($filters));
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) return $cached;
        }

        $year = isset($filters['year']) ? (int)$filters['year'] : date('Y');
        $previous_year = $year - 1;

        // Année N
        $filters_n = $filters;
        $filters_n['year'] = $year;
        $kpi_n = $this->getKPIs($filters_n);

        // Année N-1
        $filters_n1 = $filters;
        $filters_n1['year'] = $previous_year;
        $kpi_n1 = $this->getKPIs($filters_n1);

        // Calculer les évolutions
        $comparison = new stdClass();
        $comparison->year = $year;
        $comparison->previous_year = $previous_year;

        $comparison->ca_ht_n = $kpi_n->ca_total_ht;
        $comparison->ca_ht_n1 = $kpi_n1->ca_total_ht;
        $comparison->ca_ht_evolution = $kpi_n1->ca_total_ht > 0 ?
            round((($kpi_n->ca_total_ht - $kpi_n1->ca_total_ht) / $kpi_n1->ca_total_ht) * 100, 1) : 0;

        $comparison->nb_factures_n = $kpi_n->nb_factures;
        $comparison->nb_factures_n1 = $kpi_n1->nb_factures;
        $comparison->nb_factures_evolution = $kpi_n1->nb_factures > 0 ?
            round((($kpi_n->nb_factures - $kpi_n1->nb_factures) / $kpi_n1->nb_factures) * 100, 1) : 0;

        $comparison->ca_moyen_n = $kpi_n->ca_moyen;
        $comparison->ca_moyen_n1 = $kpi_n1->ca_moyen;
        $comparison->ca_moyen_evolution = $kpi_n1->ca_moyen > 0 ?
            round((($kpi_n->ca_moyen - $kpi_n1->ca_moyen) / $kpi_n1->ca_moyen) * 100, 1) : 0;

        if ($this->cache) {
            $this->cache->set($cacheKey, $comparison, 600);
        }

        return $comparison;
    }
}
