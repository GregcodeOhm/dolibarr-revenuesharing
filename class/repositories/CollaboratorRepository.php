<?php
/**
 * CollaboratorRepository.php
 * Repository pour la gestion des collaborateurs
 * Module Revenue Sharing - Dolibarr
 */

class CollaboratorRepository
{
    /** @var DoliDB */
    private $db;

    /**
     * Constructor
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Récupère un collaborateur par son ID
     * @param int $id ID du collaborateur
     * @return object|null Collaborateur ou null
     */
    public function findById($id)
    {
        $sql = "SELECT c.*, u.firstname, u.lastname, u.email
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
                WHERE c.rowid = ".(int)$id;

        $resql = $this->db->query($sql);
        if (!$resql || $this->db->num_rows($resql) == 0) {
            return null;
        }

        $result = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $result;
    }

    /**
     * Récupère tous les collaborateurs actifs
     * @return array Liste des collaborateurs
     */
    public function findAllActive()
    {
        $sql = "SELECT rowid, label, default_percentage, cost_per_session
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator
                WHERE active = 1
                ORDER BY label";

        $resql = $this->db->query($sql);
        if (!$resql) {
            return [];
        }

        $collaborators = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $collaborators[] = $obj;
        }
        $this->db->free($resql);

        return $collaborators;
    }

    /**
     * Met à jour le solde d'un collaborateur
     * @param int $collaboratorId ID du collaborateur
     * @param float $totalCredits Total des crédits
     * @param float $totalDebits Total des débits
     * @param float $currentBalance Solde actuel
     * @param string $lastTransactionDate Date de dernière transaction
     * @return bool Succès ou échec
     */
    public function updateBalance($collaboratorId, $totalCredits, $totalDebits, $currentBalance, $lastTransactionDate = null)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_account_balance
            (fk_collaborator, total_credits, total_debits, current_balance, last_transaction_date, date_updated)
            VALUES (
                ".(int)$collaboratorId.",
                ".(float)$totalCredits.",
                ".(float)$totalDebits.",
                ".(float)$currentBalance.",
                ".($lastTransactionDate ? "'".$this->db->escape($lastTransactionDate)."'" : "NULL").",
                NOW()
            )
            ON DUPLICATE KEY UPDATE
            total_credits = ".(float)$totalCredits.",
            total_debits = ".(float)$totalDebits.",
            current_balance = ".(float)$currentBalance.",
            last_transaction_date = ".($lastTransactionDate ? "'".$this->db->escape($lastTransactionDate)."'" : "NULL").",
            date_updated = NOW()";

        return (bool)$this->db->query($sql);
    }

    /**
     * Compte le nombre de collaborateurs
     * @param array $filters Filtres ['active' => 0|1]
     * @return int Nombre de collaborateurs
     */
    public function count($filters = [])
    {
        $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE 1=1";

        if (isset($filters['active'])) {
            $sql .= " AND active = ".(int)$filters['active'];
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            return 0;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return (int)$obj->nb;
    }

    /**
     * Récupère tous les collaborateurs avec filtres
     * @param array $filters Filtres ['active' => 0|1]
     * @return array Liste des collaborateurs
     */
    public function findAll($filters = [])
    {
        $sql = "SELECT c.*, u.firstname, u.lastname, u.email
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
                WHERE 1=1";

        if (isset($filters['active'])) {
            $sql .= " AND c.active = ".(int)$filters['active'];
        }

        $sql .= " ORDER BY c.label";

        $resql = $this->db->query($sql);
        if (!$resql) {
            return [];
        }

        $collaborators = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $collaborators[] = $obj;
        }
        $this->db->free($resql);

        return $collaborators;
    }

    /**
     * Récupère tous les collaborateurs avec leurs soldes
     * @param array $filters Filtres ['active', 'year', 'collaborator']
     * @return array Liste des collaborateurs avec soldes
     */
    public function findAllWithBalances($filters = [])
    {
        // Construire le filtre année basé sur la date de facture (comme BalanceRepository)
        $year_filter_sql = "";
        $prev_year_filter_sql = "";
        if (!empty($filters['year'])) {
            $year_filter_sql = " AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) = ".(int)$filters['year'];
            $prev_year_filter_sql = " AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) = ".((int)$filters['year'] - 1);
        }

        $sql = "SELECT c.rowid, c.label, c.fk_user, u.firstname, u.lastname,
                ab.last_transaction_date,
                -- Total credits (toutes années)
                (SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1) as total_credits,
                -- Total debits (toutes années) incluant salaires
                (SELECT COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1)
                 + (SELECT COALESCE(SUM(sd.solde_utilise), 0)
                    FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
                    WHERE sd.fk_collaborator = c.rowid AND sd.status = 3) as total_debits,
                -- Solde actuel (toutes années) incluant salaires
                (SELECT COALESCE(SUM(t.amount), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1)
                 - (SELECT COALESCE(SUM(sd.solde_utilise), 0)
                    FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
                    WHERE sd.fk_collaborator = c.rowid AND sd.status = 3) as current_balance,
                -- Nb transactions (filtrées par année si nécessaire)
                (SELECT COUNT(*)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                 LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as nb_transactions,
                -- Previous year credits (N-1)
                (SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                 LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1".$prev_year_filter_sql.") as prev_year_credits,
                -- Previous year balance (N-1) incluant salaires
                (SELECT COALESCE(SUM(t.amount), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                 LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1".$prev_year_filter_sql.")
                 - (SELECT COALESCE(SUM(sd.solde_utilise), 0)
                    FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
                    WHERE sd.fk_collaborator = c.rowid AND sd.status = 3"
                    .((!empty($filters['year'])) ? " AND sd.declaration_year = ".((int)$filters['year'] - 1) : "").") as prev_year_balance,
                -- Year credits (filtrés par année si nécessaire)
                (SELECT COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                 LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.") as year_credits,
                -- Year debits (filtrés par année si nécessaire) incluant salaires
                (SELECT COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                 LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.")
                 + (SELECT COALESCE(SUM(sd.solde_utilise), 0)
                    FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
                    WHERE sd.fk_collaborator = c.rowid AND sd.status = 3"
                    .((!empty($filters['year'])) ? " AND sd.declaration_year = ".(int)$filters['year'] : "").") as year_debits,
                -- Year balance (filtrés par année si nécessaire) incluant salaires
                (SELECT COALESCE(SUM(t.amount), 0)
                 FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                 LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                 LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                 WHERE t.fk_collaborator = c.rowid AND t.status = 1".$year_filter_sql.")
                 - (SELECT COALESCE(SUM(sd.solde_utilise), 0)
                    FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
                    WHERE sd.fk_collaborator = c.rowid AND sd.status = 3"
                    .((!empty($filters['year'])) ? " AND sd.declaration_year = ".(int)$filters['year'] : "").") as year_balance";

        $sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_account_balance ab ON ab.fk_collaborator = c.rowid";
        $sql .= " WHERE c.active = 1";

        if (!empty($filters['collaborator'])) {
            $sql .= " AND c.rowid = ".(int)$filters['collaborator'];
        }

        $sql .= " ORDER BY c.label";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $results[] = $obj;
        }
        $this->db->free($resql);

        return $results;
    }

    /**
     * Récupère tous les collaborateurs avec stats de contrats
     * @param array $filters Filtres ['search', 'active', 'limit', 'offset', 'sortfield', 'sortorder']
     * @return array Liste des collaborateurs avec stats
     */
    public function findAllWithContractStats($filters = [])
    {
        $sql = "SELECT c.rowid, c.label, c.default_percentage, c.active, c.date_creation,
                u.firstname, u.lastname, u.email,
                COUNT(rc.rowid) as nb_contracts,
                COALESCE(SUM(rc.net_collaborator_amount), 0) as total_revenue
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract rc ON rc.fk_collaborator = c.rowid AND rc.status >= 1
                WHERE 1=1";

        if (!empty($filters['search'])) {
            $search = $this->db->escape($filters['search']);
            $sql .= " AND (c.label LIKE '%".$search."%' OR u.firstname LIKE '%".$search."%' OR u.lastname LIKE '%".$search."%')";
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $sql .= " AND c.active = ".(int)$filters['active'];
        }

        $sql .= " GROUP BY c.rowid";

        if (!empty($filters['sortfield']) && !empty($filters['sortorder'])) {
            $sql .= $this->db->order($filters['sortfield'], $filters['sortorder']);
        }

        if (!empty($filters['limit'])) {
            $sql .= $this->db->plimit($filters['limit'] + 1, $filters['offset']);
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $results[] = $obj;
        }
        $this->db->free($resql);

        return $results;
    }
}
