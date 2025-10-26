<?php
/**
 * BalanceRepository.php
 * Repository pour les calculs de soldes et statistiques financières
 *
 * @package    RevenueSharing
 * @subpackage Repositories
 * @author     Dolibarr Module
 * @license    GPL-3.0+
 */

/**
 * Repository pour les calculs de soldes et statistiques financières
 *
 * Gère tous les calculs financiers liés aux collaborateurs:
 * - Soldes et balances des comptes
 * - Chiffres d'affaires et répartitions
 * - Statistiques par type de transaction
 */
class BalanceRepository
{
    /** @var DoliDB Instance de connexion à la base de données */
    private $db;

    /** @var CacheManager|null Instance du gestionnaire de cache */
    private $cache;

    /**
     * Constructeur du repository
     *
     * @param DoliDB       $db    Instance de la base de données Dolibarr
     * @param CacheManager $cache Instance du gestionnaire de cache (optionnel)
     */
    public function __construct($db, $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Récupère le solde et les statistiques pour un collaborateur
     *
     * @param int $collaboratorId ID du collaborateur
     * @param array $filters Filtres optionnels (year, show_previsionnel)
     * @return object|false Objet avec year_credits, year_debits, year_balance, nb_transactions, last_transaction_date, previous_balance
     */
    public function getBalance($collaboratorId, $filters = [])
    {
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;
        $show_previsionnel = isset($filters['show_previsionnel']) ? $filters['show_previsionnel'] : true;

        // Essayer de récupérer depuis le cache
        if ($this->cache) {
            $cacheKey = "balance_{$collaboratorId}_{$filter_year}_".($show_previsionnel ? '1' : '0');
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $previous_balance = 0;

        // Si année filtrée, calculer le solde reporté des années précédentes
        if ($filter_year > 0) {
            $sql_previous = "SELECT
                COALESCE(SUM(t.amount), 0) as previous_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
                WHERE t.fk_collaborator = ".(int)$collaboratorId." AND t.status = 1
                AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) < ".$filter_year;

            if (!$show_previsionnel) {
                $sql_previous .= " AND (c.type_contrat IS NULL OR c.type_contrat != 'previsionnel')";
            }

            $resql_previous = $this->db->query($sql_previous);
            if ($resql_previous) {
                $obj = $this->db->fetch_object($resql_previous);
                $previous_balance = $obj ? $obj->previous_balance : 0;
                $this->db->free($resql_previous);
            }

            // Soustraire les salaires payés des années précédentes
            $sql_previous_salaries = "SELECT
                COALESCE(SUM(solde_utilise), 0) as previous_salaries
                FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                WHERE fk_collaborator = ".(int)$collaboratorId." AND status = 3
                AND declaration_year < ".$filter_year;

            $resql_previous_salaries = $this->db->query($sql_previous_salaries);
            if ($resql_previous_salaries) {
                $obj_sal = $this->db->fetch_object($resql_previous_salaries);
                $previous_salaries = $obj_sal ? $obj_sal->previous_salaries : 0;
                $previous_balance -= $previous_salaries;
                $this->db->free($resql_previous_salaries);
            }
        }

        // Requête principale pour les statistiques de l'année (ou globales)
        $sql_balance = "SELECT
            COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) as year_credits,
            COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0) as year_debits,
            COALESCE(SUM(t.amount), 0) as year_balance,
            COUNT(*) as nb_transactions,
            MAX(COALESCE(f.datef, ff.datef, t.transaction_date)) as last_transaction_date
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
            LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
            WHERE t.fk_collaborator = ".(int)$collaboratorId." AND t.status = 1";

        if ($filter_year > 0) {
            $sql_balance .= " AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) = ".$filter_year;
        }

        if (!$show_previsionnel) {
            $sql_balance .= " AND (c.type_contrat IS NULL OR c.type_contrat != 'previsionnel')";
        }

        $resql_balance = $this->db->query($sql_balance);
        if (!$resql_balance) {
            return false;
        }

        $balance_info = $this->db->fetch_object($resql_balance);
        $this->db->free($resql_balance);

        // Ajouter les salaires payés (status = 3) comme des débits
        $sql_salaries = "SELECT
            COALESCE(SUM(solde_utilise), 0) as total_salaries_paid
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
            WHERE fk_collaborator = ".(int)$collaboratorId." AND status = 3";

        if ($filter_year > 0) {
            $sql_salaries .= " AND declaration_year = ".$filter_year;
        }

        $resql_salaries = $this->db->query($sql_salaries);
        if ($resql_salaries) {
            $obj_salaries = $this->db->fetch_object($resql_salaries);
            $total_salaries_paid = $obj_salaries->total_salaries_paid ? $obj_salaries->total_salaries_paid : 0;
            $this->db->free($resql_salaries);

            // Ajouter les salaires aux débits et soustraire du solde
            if ($balance_info) {
                $balance_info->year_debits += $total_salaries_paid;
                $balance_info->year_balance -= $total_salaries_paid;
            }
        }

        // Ajouter le solde reporté
        if ($balance_info) {
            $balance_info->previous_balance = $previous_balance;
        }

        // Mettre en cache le résultat (5 minutes)
        if ($this->cache && $balance_info) {
            $cacheKey = "balance_{$collaboratorId}_{$filter_year}_".($show_previsionnel ? '1' : '0');
            $this->cache->set($cacheKey, $balance_info, 300);
        }

        return $balance_info;
    }

    /**
     * Récupère le chiffre d'affaires et la répartition pour un collaborateur
     *
     * @param int $collaboratorId ID du collaborateur
     * @param array $filters Filtres optionnels (year, show_previsionnel)
     * @return object|false Objet avec les statistiques de CA
     */
    public function getTurnover($collaboratorId, $filters = [])
    {
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;
        $show_previsionnel = isset($filters['show_previsionnel']) ? $filters['show_previsionnel'] : true;

        $sql_ca = "SELECT
            -- CA réel (basé sur les factures)
            COALESCE(SUM(CASE WHEN f.rowid IS NOT NULL AND c.type_contrat != 'previsionnel' THEN f.total_ht ELSE 0 END), 0) as ca_reel_ht,
            COALESCE(SUM(CASE WHEN f.rowid IS NOT NULL AND c.type_contrat != 'previsionnel' THEN f.total_ttc ELSE 0 END), 0) as ca_reel_ttc,

            -- CA prévisionnel (basé sur les contrats prévisionnels)
            COALESCE(SUM(CASE WHEN c.type_contrat = 'previsionnel' THEN c.amount_ht ELSE 0 END), 0) as ca_previsionnel_ht,

            -- Totaux combinés
            COALESCE(SUM(CASE WHEN f.rowid IS NOT NULL AND c.type_contrat != 'previsionnel' THEN f.total_ht ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN c.type_contrat = 'previsionnel' THEN c.amount_ht ELSE 0 END), 0) as ca_total_ht,

            -- Parts collaborateur séparées
            COALESCE(SUM(CASE WHEN c.type_contrat != 'previsionnel' THEN c.collaborator_amount_ht ELSE 0 END), 0) as collaborator_reel_ht,
            COALESCE(SUM(CASE WHEN c.type_contrat = 'previsionnel' THEN c.collaborator_amount_ht ELSE 0 END), 0) as collaborator_previsionnel_ht,
            COALESCE(SUM(c.collaborator_amount_ht), 0) as collaborator_total_ht,

            -- Parts structure séparées
            COALESCE(SUM(CASE WHEN c.type_contrat != 'previsionnel' THEN c.studio_amount_ht ELSE 0 END), 0) as studio_reel_ht,
            COALESCE(SUM(CASE WHEN c.type_contrat = 'previsionnel' THEN c.studio_amount_ht ELSE 0 END), 0) as studio_previsionnel_ht,
            COALESCE(SUM(c.studio_amount_ht), 0) as studio_total_ht,

            -- Pourcentages moyens
            AVG(c.collaborator_percentage) as avg_percentage,

            -- Comptages
            COUNT(DISTINCT c.rowid) as nb_contrats,
            COUNT(DISTINCT c.rowid) as nb_contrats_total,
            COUNT(DISTINCT CASE WHEN c.type_contrat != 'previsionnel' THEN c.rowid END) as nb_contrats_reels,
            COUNT(DISTINCT CASE WHEN c.type_contrat = 'previsionnel' THEN c.rowid END) as nb_contrats_previsionnel,
            COUNT(DISTINCT f.rowid) as nb_factures_clients

            FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = c.fk_facture AND f.fk_statut IN (1,2)
            WHERE c.fk_collaborator = ".(int)$collaboratorId." AND c.status IN (0, 1)";

        // Filtre année
        if ($filter_year > 0) {
            // Pour les contrats réels : utiliser la date de facture
            // Pour les prévisionnels : utiliser date_prestation_prevue ou date_facturation_prevue, sinon date_creation
            $sql_ca .= " AND (
                (c.type_contrat != 'previsionnel' AND YEAR(f.datef) = ".$filter_year.") OR
                (c.type_contrat = 'previsionnel' AND (
                    YEAR(c.date_prestation_prevue) = ".$filter_year." OR
                    YEAR(c.date_facturation_prevue) = ".$filter_year." OR
                    (c.date_prestation_prevue IS NULL AND c.date_facturation_prevue IS NULL AND YEAR(c.date_creation) = ".$filter_year.")
                ))
            )";
        }

        // Filtre prévisionnel
        if (!$show_previsionnel) {
            $sql_ca .= " AND (c.type_contrat IS NULL OR c.type_contrat != 'previsionnel')";
        }

        $resql_ca = $this->db->query($sql_ca);
        if (!$resql_ca) {
            return false;
        }

        $ca_info = $this->db->fetch_object($resql_ca);
        $this->db->free($resql_ca);

        return $ca_info;
    }

    /**
     * Récupère les statistiques par type de transaction
     *
     * @param int $collaboratorId ID du collaborateur
     * @param array $filters Filtres optionnels (year, show_previsionnel)
     * @return array Tableau d'objets avec les statistiques par type
     */
    public function getStatisticsByType($collaboratorId, $filters = [])
    {
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;
        $show_previsionnel = isset($filters['show_previsionnel']) ? $filters['show_previsionnel'] : true;

        $sql_stats = "SELECT
            t.transaction_type,
            COUNT(*) as nb_operations,
            SUM(t.amount) as total_amount
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
            LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
            WHERE t.fk_collaborator = ".(int)$collaboratorId." AND t.status = 1";

        if ($filter_year > 0) {
            $sql_stats .= " AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) = ".$filter_year;
        }

        if (!$show_previsionnel) {
            $sql_stats .= " AND (c.type_contrat IS NULL OR c.type_contrat != 'previsionnel')";
        }

        $sql_stats .= " GROUP BY t.transaction_type ORDER BY SUM(t.amount) DESC";

        $resql_stats = $this->db->query($sql_stats);
        if (!$resql_stats) {
            return [];
        }

        $statistics = [];
        while ($obj = $this->db->fetch_object($resql_stats)) {
            $statistics[] = $obj;
        }
        $this->db->free($resql_stats);

        // Ajouter les salaires payés (status = 3) comme un type de transaction
        $sql_salaries_stats = "SELECT
            'salary' as transaction_type,
            COUNT(*) as nb_operations,
            -COALESCE(SUM(solde_utilise), 0) as total_amount
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
            WHERE fk_collaborator = ".(int)$collaboratorId." AND status = 3";

        if ($filter_year > 0) {
            $sql_salaries_stats .= " AND declaration_year = ".$filter_year;
        }

        $resql_salaries_stats = $this->db->query($sql_salaries_stats);
        if ($resql_salaries_stats) {
            $obj_salaries = $this->db->fetch_object($resql_salaries_stats);
            // Ajouter seulement si il y a des salaires payés
            if ($obj_salaries && $obj_salaries->nb_operations > 0) {
                $statistics[] = $obj_salaries;
            }
            $this->db->free($resql_salaries_stats);
        }

        return $statistics;
    }

    /**
     * Invalide le cache pour un collaborateur
     *
     * À appeler après modification de transactions/contrats pour ce collaborateur
     *
     * @param int $collaboratorId ID du collaborateur
     *
     * @return int Nombre d'entrées de cache supprimées
     */
    public function clearCache($collaboratorId)
    {
        if (!$this->cache) {
            return 0;
        }

        return $this->cache->deletePattern("balance_{$collaboratorId}_*");
    }

    /**
     * Statistiques globales (tous collaborateurs)
     *
     * @param array $filters Filtres optionnels ['year']
     * @return object|false Objet avec nb_collaborators, total_all_credits, total_all_debits, total_balance
     */
    public function getGlobalStats($filters = [])
    {
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;

        // Essayer de récupérer depuis le cache
        if ($this->cache) {
            $cacheKey = "global_stats_{$filter_year}";
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        if ($filter_year > 0) {
            // Statistiques filtrées par année
            $sql_stats = "SELECT
                COUNT(DISTINCT c.rowid) as nb_collaborators,
                COALESCE(SUM((SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0)
                             FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                             WHERE t.fk_collaborator = c.rowid AND t.status = 1 AND YEAR(t.transaction_date) = ".(int)$filter_year.")), 0) as total_all_credits,
                COALESCE(SUM((SELECT COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0)
                             FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                             WHERE t.fk_collaborator = c.rowid AND t.status = 1 AND YEAR(t.transaction_date) = ".(int)$filter_year.")), 0) as total_all_debits,
                COALESCE(SUM((SELECT COALESCE(SUM(t.amount), 0)
                             FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                             WHERE t.fk_collaborator = c.rowid AND t.status = 1 AND YEAR(t.transaction_date) = ".(int)$filter_year.")), 0) as total_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                WHERE c.active = 1";
        } else {
            // Statistiques globales (non filtrées)
            $sql_stats = "SELECT
                COUNT(DISTINCT c.rowid) as nb_collaborators,
                COALESCE(SUM(ab.total_credits), 0) as total_all_credits,
                COALESCE(SUM(ab.total_debits), 0) as total_all_debits,
                COALESCE(SUM(ab.current_balance), 0) as total_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_balance ab ON ab.fk_collaborator = c.rowid
                WHERE c.active = 1";
        }

        $resql_stats = $this->db->query($sql_stats);
        if (!$resql_stats) {
            return false;
        }

        $stats = $this->db->fetch_object($resql_stats);
        $this->db->free($resql_stats);

        // Mettre en cache le résultat (5 minutes)
        if ($this->cache && $stats) {
            $cacheKey = "global_stats_{$filter_year}";
            $this->cache->set($cacheKey, $stats, 300);
        }

        return $stats;
    }
}
