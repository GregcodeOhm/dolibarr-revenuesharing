<?php
/**
 * TransactionRepository.php
 * Repository pour la gestion des transactions du compte collaborateur
 *
 * @package    RevenueSharing
 * @subpackage Repositories
 * @author     Dolibarr Module
 * @license    GPL-3.0+
 */

/**
 * Repository pour la gestion des transactions
 *
 * Gère toutes les opérations CRUD sur les transactions des collaborateurs
 * incluant le filtrage, la pagination et les liaisons avec contrats/factures
 */
class TransactionRepository
{
    /** @var DoliDB Instance de connexion à la base de données */
    private $db;

    /**
     * Constructeur du repository
     *
     * @param DoliDB $db Instance de la base de données Dolibarr
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Récupère les transactions d'un collaborateur avec filtres et pagination
     *
     * @param int   $collaboratorId ID du collaborateur
     * @param array $filters        Filtres optionnels:
     *                              - 'year' (int): Année de filtrage
     *                              - 'type' (string): Type de transaction
     *                              - 'show_previsionnel' (bool): Inclure les prévisionnels
     *                              - 'page' (int): Numéro de page (défaut: 1)
     *                              - 'limit' (int): Nombre d'éléments par page (défaut: 50)
     *
     * @return array Tableau associatif contenant:
     *               - 'transactions' (array): Liste des objets transactions
     *               - 'total' (int): Nombre total de transactions
     *               - 'pages' (int): Nombre total de pages
     *               - 'current_page' (int): Page actuelle
     *               - 'per_page' (int): Nombre d'éléments par page
     */
    public function findByCollaborator($collaboratorId, $filters = [])
    {
        // Pagination
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = ($page - 1) * $limit;

        // Construction de la clause WHERE pour le count et la requête principale
        $whereClause = "WHERE t.fk_collaborator = ".(int)$collaboratorId." AND t.status = 1";

        // Filtre année (utiliser COALESCE comme dans l'affichage)
        if (!empty($filters['year'])) {
            $whereClause .= " AND YEAR(COALESCE(f.datef, ff.datef, t.transaction_date)) = ".(int)$filters['year'];
        }

        // Filtre type de transaction
        if (!empty($filters['type'])) {
            // Si on filtre sur 'salary', on ne comptera que les salaires
            if ($filters['type'] === 'salary') {
                $whereClause .= " AND 1=0"; // Aucune transaction classique ne match
            } else {
                $whereClause .= " AND t.transaction_type = '".$this->db->escape($filters['type'])."'";
            }
        }

        // Filtre prévisionnels
        if (isset($filters['show_previsionnel']) && !$filters['show_previsionnel']) {
            $whereClause .= " AND (c.type_contrat IS NULL OR c.type_contrat != 'previsionnel')";
        }

        // Compter le total des transactions classiques
        $sql_count = "SELECT COUNT(*) as total
                      FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                      LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
                      LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
                      LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
                      ".$whereClause;

        $resql_count = $this->db->query($sql_count);
        $total = 0;
        if ($resql_count) {
            $obj_count = $this->db->fetch_object($resql_count);
            $total = (int)$obj_count->total;
            $this->db->free($resql_count);
        }

        // Compter les salaires payés
        $sql_salary_count = "SELECT COUNT(*) as total
                            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                            WHERE fk_collaborator = ".(int)$collaboratorId." AND status = 3";

        if (!empty($filters['year'])) {
            $sql_salary_count .= " AND declaration_year = ".(int)$filters['year'];
        }

        // Si on filtre sur un type autre que 'salary', ne pas compter les salaires
        if (!empty($filters['type']) && $filters['type'] !== 'salary') {
            $sql_salary_count .= " AND 1=0";
        }

        $resql_salary_count = $this->db->query($sql_salary_count);
        if ($resql_salary_count) {
            $obj_salary_count = $this->db->fetch_object($resql_salary_count);
            $total += (int)$obj_salary_count->total;
            $this->db->free($resql_salary_count);
        }

        // Récupérer les transactions avec pagination en utilisant UNION
        $sql = "SELECT * FROM (
            SELECT
                t.rowid,
                t.fk_collaborator,
                t.transaction_type,
                t.amount,
                t.description,
                t.label,
                t.transaction_date,
                t.date_creation,
                t.status,
                t.fk_contract,
                t.fk_facture,
                t.fk_facture_fourn,
                t.fk_user_creat,
                t.note_private,
                c.label as contract_label,
                c.ref as contract_ref,
                c.status as contract_status,
                c.type_contrat as contract_type_contrat,
                f.ref as facture_ref,
                f.datef as facture_date,
                ff.ref as facture_fourn_ref,
                ff.datef as facture_fourn_date,
                ff.libelle as facture_fourn_label,
                u.login as user_login,
                COALESCE(f.datef, ff.datef, t.transaction_date) as display_date,
                NULL as salary_declaration_id,
                NULL as declaration_year,
                NULL as declaration_month,
                NULL as total_days
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = t.fk_facture
            LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = t.fk_facture_fourn
            LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = t.fk_user_creat
            ".$whereClause."

            UNION ALL

            SELECT
                -sd.rowid as rowid,
                sd.fk_collaborator,
                'salary' as transaction_type,
                -sd.solde_utilise as amount,
                CONCAT('Salaire ',
                    CASE sd.declaration_month
                        WHEN 1 THEN 'Janvier'
                        WHEN 2 THEN 'Février'
                        WHEN 3 THEN 'Mars'
                        WHEN 4 THEN 'Avril'
                        WHEN 5 THEN 'Mai'
                        WHEN 6 THEN 'Juin'
                        WHEN 7 THEN 'Juillet'
                        WHEN 8 THEN 'Août'
                        WHEN 9 THEN 'Septembre'
                        WHEN 10 THEN 'Octobre'
                        WHEN 11 THEN 'Novembre'
                        WHEN 12 THEN 'Décembre'
                    END,
                    ' ', sd.declaration_year,
                    ' (', sd.total_days, ' jours)'
                ) as description,
                CONCAT('Déclaration #', sd.rowid) as label,
                DATE(CONCAT(sd.declaration_year, '-', LPAD(sd.declaration_month, 2, '0'), '-01')) as transaction_date,
                sd.date_creation,
                sd.status,
                NULL as fk_contract,
                NULL as fk_facture,
                NULL as fk_facture_fourn,
                sd.fk_user_creat,
                sd.note_private,
                NULL as contract_label,
                NULL as contract_ref,
                NULL as contract_status,
                NULL as contract_type_contrat,
                NULL as facture_ref,
                NULL as facture_date,
                NULL as facture_fourn_ref,
                NULL as facture_fourn_date,
                NULL as facture_fourn_label,
                u.login as user_login,
                DATE(CONCAT(sd.declaration_year, '-', LPAD(sd.declaration_month, 2, '0'), '-01')) as display_date,
                sd.rowid as salary_declaration_id,
                sd.declaration_year,
                sd.declaration_month,
                sd.total_days
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
            LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = sd.fk_user_creat
            WHERE sd.fk_collaborator = ".(int)$collaboratorId."
            AND sd.status = 3"
            .(!empty($filters['year']) ? " AND sd.declaration_year = ".(int)$filters['year'] : "")
            .(!empty($filters['type']) && $filters['type'] !== 'salary' ? " AND 1=0" : "")."
        ) AS combined_transactions
        ORDER BY display_date DESC, date_creation DESC
        LIMIT ".$limit." OFFSET ".$offset;

        $resql = $this->db->query($sql);
        if (!$resql) {
            // Debug: afficher l'erreur SQL
            if (!empty($GLOBALS['conf']->global->MAIN_MODULE_REVENUESHARING_DEBUG)) {
                dol_syslog("TransactionRepository::findByCollaborator SQL Error: ".$this->db->lasterror(), LOG_ERR);
                dol_syslog("TransactionRepository::findByCollaborator SQL: ".$sql, LOG_DEBUG);
            }
            return [
                'transactions' => [],
                'total' => 0,
                'pages' => 0,
                'current_page' => 1,
                'per_page' => $limit,
                'error' => $this->db->lasterror(),
                'sql' => $sql
            ];
        }

        $transactions = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $transactions[] = $obj;
        }
        $this->db->free($resql);

        return [
            'transactions' => $transactions,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page,
            'per_page' => $limit
        ];
    }

    /**
     * Calcule le solde d'un collaborateur
     * @param int $collaboratorId ID du collaborateur
     * @param array $filters Filtres optionnels (year, show_previsionnel)
     * @return object Objet contenant total_credits, total_debits, balance
     */
    public function calculateBalance($collaboratorId, $filters = [])
    {
        $sql = "SELECT
            COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) as total_credits,
            COALESCE(SUM(CASE WHEN t.amount < 0 THEN ABS(t.amount) ELSE 0 END), 0) as total_debits,
            COALESCE(SUM(t.amount), 0) as balance
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
            WHERE t.fk_collaborator = ".(int)$collaboratorId."
            AND t.status = 1";

        // Filtre année
        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(t.transaction_date) = ".(int)$filters['year'];
        }

        // Filtre prévisionnels
        if (isset($filters['show_previsionnel']) && !$filters['show_previsionnel']) {
            $sql .= " AND (c.is_previsionnel IS NULL OR c.is_previsionnel = 0)";
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            return (object)[
                'total_credits' => 0,
                'total_debits' => 0,
                'balance' => 0
            ];
        }

        $result = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $result;
    }

    /**
     * Calcule le solde reporté (avant une année donnée)
     * @param int $collaboratorId ID du collaborateur
     * @param int $year Année de référence
     * @param bool $showPrevisionnel Inclure les prévisionnels
     * @return float Solde reporté
     */
    public function calculatePreviousBalance($collaboratorId, $year, $showPrevisionnel = true)
    {
        $sql = "SELECT COALESCE(SUM(t.amount), 0) as previous_balance
                FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
                WHERE t.fk_collaborator = ".(int)$collaboratorId."
                AND t.status = 1
                AND YEAR(t.transaction_date) < ".(int)$year;

        if (!$showPrevisionnel) {
            $sql .= " AND (c.is_previsionnel IS NULL OR c.is_previsionnel = 0)";
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            return 0;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return (float)$obj->previous_balance;
    }

    /**
     * Récupère une transaction par son ID
     * @param int $id ID de la transaction
     * @return object|null Transaction ou null
     */
    public function findById($id)
    {
        $sql = "SELECT t.*, c.label as contract_label
                FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
                WHERE t.rowid = ".(int)$id;

        $resql = $this->db->query($sql);
        if (!$resql || $this->db->num_rows($resql) == 0) {
            return null;
        }

        $result = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $result;
    }

    /**
     * Crée une nouvelle transaction
     *
     * @param array $data Données de la transaction:
     *                    - 'fk_collaborator' (int): ID du collaborateur
     *                    - 'transaction_date' (string): Date de la transaction
     *                    - 'amount' (float): Montant de la transaction
     *                    - 'transaction_type' (string): Type de transaction
     *                    - 'description' (string): Description
     *                    - 'note_private' (string): Note privée (optionnel)
     *                    - 'fk_contract' (int): ID du contrat lié (optionnel)
     *                    - 'fk_facture' (int): ID de la facture liée (optionnel)
     *                    - 'fk_facture_fourn' (int): ID de la facture fournisseur liée (optionnel)
     *                    - 'fk_user_creat' (int): ID de l'utilisateur créateur (optionnel)
     *                    - 'status' (int): Statut (optionnel, défaut: 1)
     *
     * @return int|false ID de la transaction créée ou false en cas d'échec
     */
    public function create($data)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_account_transaction (
            fk_collaborator,
            transaction_date,
            amount,
            transaction_type,
            description,
            note_private,
            fk_contract,
            fk_facture,
            fk_facture_fourn,
            fk_user_creat,
            status
        ) VALUES (
            ".(int)$data['fk_collaborator'].",
            '".$this->db->escape($data['transaction_date'])."',
            ".(float)$data['amount'].",
            '".$this->db->escape($data['transaction_type'])."',
            '".$this->db->escape($data['description'])."',
            '".$this->db->escape($data['note_private'] ?? '')."',
            ".(int)($data['fk_contract'] ?? 0).",
            ".(int)($data['fk_facture'] ?? 0).",
            ".(int)($data['fk_facture_fourn'] ?? 0).",
            ".(int)($data['fk_user_creat'] ?? 0).",
            ".(int)($data['status'] ?? 1)."
        )";

        if ($this->db->query($sql)) {
            return $this->db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_account_transaction");
        }

        return false;
    }

    /**
     * Met à jour une transaction
     * @param int $id ID de la transaction
     * @param array $data Données à mettre à jour
     * @return bool Succès ou échec
     */
    public function update($id, $data)
    {
        $fields = [];

        if (isset($data['amount'])) {
            $fields[] = "amount = ".(float)$data['amount'];
        }
        if (isset($data['description'])) {
            $fields[] = "description = '".$this->db->escape($data['description'])."'";
        }
        if (isset($data['note_private'])) {
            $fields[] = "note_private = '".$this->db->escape($data['note_private'])."'";
        }
        if (isset($data['transaction_type'])) {
            $fields[] = "transaction_type = '".$this->db->escape($data['transaction_type'])."'";
        }
        if (isset($data['fk_contract'])) {
            $fields[] = "fk_contract = ".(int)$data['fk_contract'];
        }
        if (isset($data['fk_facture_fourn'])) {
            $fields[] = "fk_facture_fourn = ".(int)$data['fk_facture_fourn'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction
                SET ".implode(', ', $fields)."
                WHERE rowid = ".(int)$id;

        return (bool)$this->db->query($sql);
    }

    /**
     * Supprime (soft delete) une transaction
     * @param int $id ID de la transaction
     * @return bool Succès ou échec
     */
    public function delete($id)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction
                SET status = 0
                WHERE rowid = ".(int)$id;

        return (bool)$this->db->query($sql);
    }

    /**
     * Lie une transaction à un contrat
     * @param int $transactionId ID de la transaction
     * @param int $contractId ID du contrat
     * @return bool Succès ou échec
     */
    public function linkToContract($transactionId, $contractId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction
                SET fk_contract = ".(int)$contractId."
                WHERE rowid = ".(int)$transactionId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Lie une transaction à une facture fournisseur
     * @param int $transactionId ID de la transaction
     * @param int $invoiceId ID de la facture fournisseur
     * @return bool Succès ou échec
     */
    public function linkToSupplierInvoice($transactionId, $invoiceId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction
                SET fk_facture_fourn = ".(int)$invoiceId."
                WHERE rowid = ".(int)$transactionId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Délie une transaction d'un contrat
     * @param int $transactionId ID de la transaction
     * @return bool Succès ou échec
     */
    public function unlinkFromContract($transactionId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction
                SET fk_contract = NULL
                WHERE rowid = ".(int)$transactionId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Délie une transaction d'une facture fournisseur
     * @param int $transactionId ID de la transaction
     * @return bool Succès ou échec
     */
    public function unlinkFromSupplierInvoice($transactionId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_account_transaction
                SET fk_facture_fourn = NULL
                WHERE rowid = ".(int)$transactionId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Calcule les statistiques des transactions
     * @param int $collaboratorId ID du collaborateur
     * @param array $filters Filtres optionnels
     * @return object Statistiques
     */
    public function getStatistics($collaboratorId, $filters = [])
    {
        $sql = "SELECT
            COUNT(*) as total_transactions,
            COUNT(CASE WHEN amount > 0 THEN 1 END) as total_credits_count,
            COUNT(CASE WHEN amount < 0 THEN 1 END) as total_debits_count,
            COALESCE(AVG(CASE WHEN amount > 0 THEN amount END), 0) as avg_credit,
            COALESCE(AVG(CASE WHEN amount < 0 THEN ABS(amount) END), 0) as avg_debit,
            MIN(transaction_date) as first_transaction_date,
            MAX(transaction_date) as last_transaction_date
            FROM ".MAIN_DB_PREFIX."revenuesharing_account_transaction t
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract c ON c.rowid = t.fk_contract
            WHERE t.fk_collaborator = ".(int)$collaboratorId."
            AND t.status = 1";

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(t.transaction_date) = ".(int)$filters['year'];
        }

        if (isset($filters['show_previsionnel']) && !$filters['show_previsionnel']) {
            $sql .= " AND (c.is_previsionnel IS NULL OR c.is_previsionnel = 0)";
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            return (object)[];
        }

        $result = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $result;
    }
}
