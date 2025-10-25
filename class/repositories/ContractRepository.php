<?php
/**
 * ContractRepository.php
 * Repository pour la gestion des contrats
 * Module Revenue Sharing - Dolibarr
 */

class ContractRepository
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
     * Récupère les contrats d'un collaborateur
     * @param int $collaboratorId ID du collaborateur
     * @return array Liste des contrats
     */
    public function findByCollaborator($collaboratorId)
    {
        $sql = "SELECT rowid, ref, type_contrat, status, collaborator_amount_ht
                FROM ".MAIN_DB_PREFIX."revenuesharing_contract
                WHERE fk_collaborator = ".(int)$collaboratorId."
                ORDER BY date_creation DESC";

        $resql = $this->db->query($sql);
        if (!$resql) {
            return [];
        }

        $contracts = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $contracts[] = $obj;
        }
        $this->db->free($resql);

        return $contracts;
    }

    /**
     * Récupère les contrats disponibles pour liaison
     * @return array Liste des contrats disponibles
     */
    public function findAvailableContracts()
    {
        $sql = "SELECT c.rowid as id, c.label, c.amount_ht, collab.label as collaborator
                FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator collab ON collab.rowid = c.fk_collaborator
                WHERE c.status IN (1, 2)
                ORDER BY c.date_creation DESC
                LIMIT 100";

        $resql = $this->db->query($sql);
        if (!$resql) {
            return [];
        }

        $contracts = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $contracts[] = $obj;
        }
        $this->db->free($resql);

        return $contracts;
    }

    /**
     * Récupère les données d'un contrat par son ID
     * @param int $id ID du contrat
     * @return object|null Contrat ou null
     */
    public function findById($id)
    {
        $sql = "SELECT c.*,
                f.ref as facture_ref,
                f.total_ht as facture_amount,
                s.nom as facture_client,
                p.ref as propal_ref,
                p.total_ht as propal_amount,
                sp.nom as propal_client
                FROM ".MAIN_DB_PREFIX."revenuesharing_contract c
                LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = c.fk_facture
                LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc
                LEFT JOIN ".MAIN_DB_PREFIX."propal p ON p.rowid = c.fk_propal
                LEFT JOIN ".MAIN_DB_PREFIX."societe sp ON sp.rowid = p.fk_soc
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
     * Récupère tous les contrats avec filtres et pagination
     *
     * @param array $filters Filtres: ref, collaborator, status, type, sortfield, sortorder, limit, offset
     * @return array ['contracts' => array, 'total' => int, 'pages' => int]
     */
    public function findAllWithDetails($filters = [])
    {
        $search_ref = isset($filters['ref']) ? $filters['ref'] : '';
        $search_collaborator = isset($filters['collaborator']) ? (int)$filters['collaborator'] : 0;
        $search_status = isset($filters['status']) ? $filters['status'] : '';
        $search_type = isset($filters['type']) ? $filters['type'] : '';
        $sortfield = isset($filters['sortfield']) ? $filters['sortfield'] : 'rc.date_creation';
        $sortorder = isset($filters['sortorder']) ? $filters['sortorder'] : 'DESC';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 25;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        // Requête COUNT pour le total
        $sql_count = "SELECT COUNT(*) as total";
        $sql_count .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract rc";
        $sql_count .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = rc.fk_collaborator";
        $sql_count .= " WHERE 1=1";

        if ($search_ref) {
            $sql_count .= " AND rc.ref LIKE '%".$this->db->escape($search_ref)."%'";
        }
        if ($search_collaborator > 0) {
            $sql_count .= " AND rc.fk_collaborator = ".$search_collaborator;
        }
        if ($search_status !== '') {
            $sql_count .= " AND rc.status = ".(int)$search_status;
        }
        if ($search_type !== '') {
            $sql_count .= " AND rc.type_contrat = '".$this->db->escape($search_type)."'";
        }

        $resql_count = $this->db->query($sql_count);
        $total = 0;
        if ($resql_count) {
            $obj_count = $this->db->fetch_object($resql_count);
            $total = (int)$obj_count->total;
            $this->db->free($resql_count);
        }

        // Requête SELECT pour les données
        $sql = "SELECT rc.rowid, rc.ref, rc.type_contrat, rc.label, rc.amount_ht, rc.amount_ttc, rc.collaborator_percentage,";
        $sql .= " rc.collaborator_amount_ht, rc.net_collaborator_amount, rc.status, rc.date_creation, rc.fk_collaborator,";
        $sql .= " c.label as collaborator_label,";
        $sql .= " u.firstname, u.lastname,";
        $sql .= " p.ref as project_ref, p.title as project_title,";
        $sql .= " f.ref as facture_ref";
        $sql .= " FROM ".MAIN_DB_PREFIX."revenuesharing_contract rc";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = rc.fk_collaborator";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet p ON p.rowid = rc.fk_project";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = rc.fk_facture";
        $sql .= " WHERE 1=1";

        if ($search_ref) {
            $sql .= " AND rc.ref LIKE '%".$this->db->escape($search_ref)."%'";
        }
        if ($search_collaborator > 0) {
            $sql .= " AND rc.fk_collaborator = ".$search_collaborator;
        }
        if ($search_status !== '') {
            $sql .= " AND rc.status = ".(int)$search_status;
        }
        if ($search_type !== '') {
            $sql .= " AND rc.type_contrat = '".$this->db->escape($search_type)."'";
        }

        $sql .= $this->db->order($sortfield, $sortorder);
        $sql .= $this->db->plimit($limit, $offset);

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL findAllWithDetails: ".$this->db->lasterror());
        }

        $contracts = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $contracts[] = $obj;
        }
        $this->db->free($resql);

        return [
            'contracts' => $contracts,
            'total' => $total,
            'pages' => $limit > 0 ? ceil($total / $limit) : 1
        ];
    }

    /**
     * Met à jour le statut d'un contrat
     *
     * @param int $contractId ID du contrat
     * @param int $status Nouveau statut
     * @return bool True si succès
     */
    public function updateStatus($contractId, $status)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_contract
                SET status = ".(int)$status."
                WHERE rowid = ".(int)$contractId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Supprime un contrat
     *
     * @param int $contractId ID du contrat
     * @return bool True si succès
     */
    public function delete($contractId)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_contract
                WHERE rowid = ".(int)$contractId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Statistiques annuelles des contrats
     * @param int $year Année
     * @param int|null $collaboratorId ID collaborateur (optionnel)
     * @return array Statistiques (nb_contracts, nb_draft, nb_valid, total_ht, total_collaborator, total_studio)
     */
    public function getYearStats($year, $collaboratorId = null)
    {
        $sql = "SELECT COUNT(*) as nb_contracts,
                COUNT(CASE WHEN status = 0 THEN 1 END) as nb_draft,
                COUNT(CASE WHEN status >= 1 THEN 1 END) as nb_valid,
                COALESCE(SUM(CASE WHEN status >= 1 THEN amount_ht ELSE 0 END), 0) as total_ht,
                COALESCE(SUM(CASE WHEN status >= 1 THEN net_collaborator_amount ELSE 0 END), 0) as total_collaborator
                FROM ".MAIN_DB_PREFIX."revenuesharing_contract
                WHERE YEAR(date_creation) = ".(int)$year;

        if ($collaboratorId) {
            $sql .= " AND fk_collaborator = ".(int)$collaboratorId;
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL getYearStats: ".$this->db->lasterror());
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return [
            'nb_contracts' => $obj->nb_contracts,
            'nb_draft' => $obj->nb_draft,
            'nb_valid' => $obj->nb_valid,
            'total_ht' => $obj->total_ht,
            'total_collaborator' => $obj->total_collaborator,
            'total_studio' => $obj->total_ht - $obj->total_collaborator
        ];
    }

    /**
     * Historique 5 dernières années
     * @param int|null $collaboratorId ID collaborateur (optionnel)
     * @return array Historique par année
     */
    public function get5YearsHistory($collaboratorId = null)
    {
        $history = [];
        for ($y = date('Y'); $y >= date('Y') - 4; $y--) {
            $history[$y] = $this->getYearStats($y, $collaboratorId);
        }
        return $history;
    }

    /**
     * Top N collaborateurs par revenus
     * @param int $year Année
     * @param int $limit Nombre de résultats
     * @return array Liste des top collaborateurs
     */
    public function getTopCollaborators($year, $limit = 5)
    {
        $sql = "SELECT c.rowid, c.label, u.firstname, u.lastname,
                COUNT(rc.rowid) as nb_contracts,
                COALESCE(SUM(rc.net_collaborator_amount), 0) as total_revenue
                FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator c
                LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user
                LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_contract rc ON rc.fk_collaborator = c.rowid
                WHERE rc.status >= 1 AND YEAR(rc.date_creation) = ".(int)$year."
                GROUP BY c.rowid
                HAVING total_revenue > 0
                ORDER BY total_revenue DESC
                LIMIT ".(int)$limit;

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur SQL getTopCollaborators: ".$this->db->lasterror());
        }

        $results = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $results[] = $obj;
        }
        $this->db->free($resql);

        return $results;
    }
}
