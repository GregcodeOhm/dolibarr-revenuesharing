<?php
/**
 * SalaryDeclarationRepository.php
 * Repository pour la gestion des déclarations de salaires
 *
 * @package    RevenueSharing
 * @subpackage Repositories
 * @author     Dolibarr Module
 * @license    GPL-3.0+
 */

/**
 * Repository pour la gestion des déclarations de salaires
 *
 * Gère toutes les opérations de lecture et calcul sur les déclarations de salaires:
 * - Statistiques par statut (brouillon, validé, payé)
 * - Montants utilisés et provisionnels
 * - Nombre de jours par statut
 */
class SalaryDeclarationRepository
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
     * Récupère les statistiques des déclarations de salaires pour un collaborateur
     *
     * Cette méthode calcule les statistiques des déclarations de salaires selon leur statut:
     * - Statut 1: Brouillon
     * - Statut 2: Validé
     * - Statut 3: Payé
     *
     * @param int   $collaboratorId ID du collaborateur
     * @param array $filters        Filtres optionnels:
     *                              - 'year' (int): Année de filtrage (declaration_year)
     *
     * @return object|false Objet contenant les statistiques ou false en cas d'échec:
     *                      - nb_brouillons (int): Nombre de déclarations brouillons
     *                      - nb_valides (int): Nombre de déclarations validées
     *                      - nb_payes (int): Nombre de déclarations payées
     *                      - montant_brouillons (float): Montant total des brouillons
     *                      - montant_valides (float): Montant total des validées
     *                      - montant_payes (float): Montant total des payées
     *                      - montant_previsionnel (float): Montant prévisionnel (brouillons + validées)
     *                      - jours_brouillons (float): Nombre de jours en brouillon
     *                      - jours_valides (float): Nombre de jours validés
     *                      - jours_payes (float): Nombre de jours payés
     */
    public function getSalaryStatistics($collaboratorId, $filters = [])
    {
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;

        $sql = "SELECT
            COUNT(CASE WHEN status = 1 THEN 1 END) as nb_brouillons,
            COUNT(CASE WHEN status = 2 THEN 1 END) as nb_valides,
            COUNT(CASE WHEN status = 3 THEN 1 END) as nb_payes,
            COALESCE(SUM(CASE WHEN status = 1 THEN solde_utilise ELSE 0 END), 0) as montant_brouillons,
            COALESCE(SUM(CASE WHEN status = 2 THEN solde_utilise ELSE 0 END), 0) as montant_valides,
            COALESCE(SUM(CASE WHEN status = 3 THEN solde_utilise ELSE 0 END), 0) as montant_payes,
            COALESCE(SUM(CASE WHEN status IN (1,2) THEN solde_utilise ELSE 0 END), 0) as montant_previsionnel,
            COALESCE(SUM(CASE WHEN status = 1 THEN total_days ELSE 0 END), 0) as jours_brouillons,
            COALESCE(SUM(CASE WHEN status = 2 THEN total_days ELSE 0 END), 0) as jours_valides,
            COALESCE(SUM(CASE WHEN status = 3 THEN total_days ELSE 0 END), 0) as jours_payes
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
            WHERE fk_collaborator = ".(int)$collaboratorId;

        if ($filter_year > 0) {
            $sql .= " AND declaration_year = ".$filter_year;
        }

        $resql = $this->db->query($sql);

        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return $obj;
        }

        return false;
    }

    /**
     * Récupère la liste complète des déclarations de salaires pour un collaborateur
     *
     * @param int   $collaboratorId ID du collaborateur
     * @param array $filters        Filtres optionnels:
     *                              - 'year' (int): Année de filtrage
     *                              - 'status' (int): Statut de filtrage (1=brouillon, 2=validé, 3=payé)
     *                              - 'order_by' (string): Champ de tri (défaut: 'declaration_year')
     *                              - 'order_dir' (string): Direction du tri (défaut: 'DESC')
     *
     * @return array|false Tableau des déclarations ou false en cas d'échec
     */
    public function findByCollaborator($collaboratorId, $filters = [])
    {
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;
        $filter_status = isset($filters['status']) ? (int)$filters['status'] : 0;
        $order_by = isset($filters['order_by']) ? $filters['order_by'] : 'declaration_year';
        $order_dir = isset($filters['order_dir']) ? $filters['order_dir'] : 'DESC';

        // Sécurité sur les champs de tri
        $allowed_order_by = ['rowid', 'declaration_year', 'declaration_month', 'status', 'date_creation', 'total_days', 'solde_utilise'];
        if (!in_array($order_by, $allowed_order_by)) {
            $order_by = 'declaration_year';
        }

        $allowed_order_dir = ['ASC', 'DESC'];
        if (!in_array($order_dir, $allowed_order_dir)) {
            $order_dir = 'DESC';
        }

        $sql = "SELECT
            sd.rowid,
            sd.fk_collaborator,
            sd.declaration_year,
            sd.declaration_month,
            sd.status,
            sd.total_days,
            sd.solde_utilise,
            sd.note_private,
            sd.date_creation,
            sd.date_modification,
            sd.fk_user_creat,
            sd.fk_user_modif
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration sd
            WHERE sd.fk_collaborator = ".(int)$collaboratorId;

        if ($filter_year > 0) {
            $sql .= " AND sd.declaration_year = ".$filter_year;
        }

        if ($filter_status > 0) {
            $sql .= " AND sd.status = ".$filter_status;
        }

        $sql .= " ORDER BY sd.".$order_by." ".$order_dir;

        $resql = $this->db->query($sql);

        if ($resql) {
            $declarations = [];
            while ($obj = $this->db->fetch_object($resql)) {
                $declarations[] = $obj;
            }
            $this->db->free($resql);
            return $declarations;
        }

        return false;
    }

    /**
     * Récupère une déclaration de salaire par son ID avec nom collaborateur
     *
     * @param int $declarationId ID de la déclaration
     *
     * @return object|false Objet déclaration ou false si non trouvée
     */
    public function findById($declarationId)
    {
        $sql = "SELECT d.*, c.label as collaborator_name
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = d.fk_collaborator
            WHERE d.rowid = ".(int)$declarationId;

        $resql = $this->db->query($sql);

        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return $obj;
        }

        return false;
    }

    /**
     * Récupère les détails d'une déclaration
     *
     * @param int $declarationId ID de la déclaration
     *
     * @return array Tableau des détails
     */
    public function getDetails($declarationId)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail
                WHERE fk_declaration = ".(int)$declarationId."
                ORDER BY work_date";

        $resql = $this->db->query($sql);

        if (!$resql) {
            // Log l'erreur SQL
            if (function_exists('dol_syslog')) {
                dol_syslog("SalaryDeclarationRepository::getDetails ERROR: ".$this->db->lasterror(), LOG_ERR);
            }
            // Essayer sans ORDER BY au cas où la colonne n'existe pas
            $sql2 = "SELECT * FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail
                    WHERE fk_declaration = ".(int)$declarationId;
            $resql = $this->db->query($sql2);
            if (!$resql) {
                return [];
            }
        }

        $details = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $details[] = $obj;
        }
        $this->db->free($resql);

        return $details;
    }

    /**
     * Crée une nouvelle déclaration de salaire
     *
     * @param array $data Données de la déclaration
     *
     * @return int|false ID de la déclaration créée ou false
     */
    public function create($data)
    {
        // Validation
        if (empty($data['fk_collaborator']) || empty($data['declaration_month']) || empty($data['declaration_year'])) {
            return false;
        }

        // Vérifier unicité
        if ($this->exists($data['fk_collaborator'], $data['declaration_month'], $data['declaration_year'])) {
            return false;
        }

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                (fk_collaborator, declaration_month, declaration_year, total_days, total_cachets,
                 cachet_brut_unitaire, masse_salariale, solde_utilise, note_private, status, fk_user_creat, date_creation)
                VALUES (
                    ".(int)$data['fk_collaborator'].",
                    ".(int)$data['declaration_month'].",
                    ".(int)$data['declaration_year'].",
                    ".(isset($data['total_days']) ? (int)$data['total_days'] : 0).",
                    ".(isset($data['total_cachets']) ? (float)$data['total_cachets'] : 0).",
                    ".(isset($data['cachet_brut_unitaire']) ? (float)$data['cachet_brut_unitaire'] : 0).",
                    ".(isset($data['masse_salariale']) ? (float)$data['masse_salariale'] : 0).",
                    ".(isset($data['solde_utilise']) ? (float)$data['solde_utilise'] : 0).",
                    '".$this->db->escape(isset($data['note_private']) ? $data['note_private'] : '')."',
                    ".(isset($data['status']) ? (int)$data['status'] : 1).",
                    ".(isset($data['fk_user_creat']) ? (int)$data['fk_user_creat'] : 0).",
                    NOW()
                )";

        if ($this->db->query($sql)) {
            return $this->db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_salary_declaration");
        }

        return false;
    }

    /**
     * Met à jour une déclaration de salaire
     *
     * @param int   $declarationId ID de la déclaration
     * @param array $data          Données à mettre à jour
     *
     * @return bool True si succès
     */
    public function update($declarationId, $data)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."revenuesharing_salary_declaration SET ";
        $updates = [];

        if (isset($data['total_days'])) $updates[] = "total_days = ".(int)$data['total_days'];
        if (isset($data['total_cachets'])) $updates[] = "total_cachets = ".(float)$data['total_cachets'];
        if (isset($data['cachet_brut_unitaire'])) $updates[] = "cachet_brut_unitaire = ".(float)$data['cachet_brut_unitaire'];
        if (isset($data['masse_salariale'])) $updates[] = "masse_salariale = ".(float)$data['masse_salariale'];
        if (isset($data['solde_utilise'])) $updates[] = "solde_utilise = ".(float)$data['solde_utilise'];
        if (isset($data['note_private'])) $updates[] = "note_private = '".$this->db->escape($data['note_private'])."'";
        if (isset($data['status'])) $updates[] = "status = ".(int)$data['status'];
        if (isset($data['fk_user_modif'])) $updates[] = "fk_user_modif = ".(int)$data['fk_user_modif'];

        $updates[] = "date_modification = NOW()";

        if (empty($updates)) {
            return false;
        }

        $sql .= implode(", ", $updates);
        $sql .= " WHERE rowid = ".(int)$declarationId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Supprime une déclaration et ses détails
     *
     * @param int $declarationId ID de la déclaration
     *
     * @return bool True si succès
     */
    public function delete($declarationId)
    {
        $this->db->begin();

        // Supprimer les détails
        $sql_details = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail
                        WHERE fk_declaration = ".(int)$declarationId;

        if (!$this->db->query($sql_details)) {
            $this->db->rollback();
            return false;
        }

        // Supprimer la déclaration
        $sql_main = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                     WHERE rowid = ".(int)$declarationId;

        if (!$this->db->query($sql_main)) {
            $this->db->rollback();
            return false;
        }

        $this->db->commit();
        return true;
    }

    /**
     * Ajoute un détail à une déclaration
     *
     * @param int   $declarationId ID de la déclaration
     * @param array $detail        Données du détail
     *
     * @return int|false ID du détail créé ou false
     */
    public function addDetail($declarationId, $detail)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail
                (fk_declaration, date_travail, metier, cachet_brut, nb_heures)
                VALUES (
                    ".(int)$declarationId.",
                    '".$this->db->escape($detail['date_travail'])."',
                    '".$this->db->escape($detail['metier'])."',
                    ".(isset($detail['cachet_brut']) ? (float)$detail['cachet_brut'] : 0).",
                    ".(isset($detail['nb_heures']) ? (float)$detail['nb_heures'] : 8.0)."
                )";

        if ($this->db->query($sql)) {
            return $this->db->last_insert_id(MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail");
        }

        return false;
    }

    /**
     * Supprime tous les détails d'une déclaration
     *
     * @param int $declarationId ID de la déclaration
     *
     * @return bool True si succès
     */
    public function deleteDetails($declarationId)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail
                WHERE fk_declaration = ".(int)$declarationId;

        return (bool)$this->db->query($sql);
    }

    /**
     * Vérifie si une déclaration existe pour un collaborateur/période
     *
     * @param int $collaboratorId ID du collaborateur
     * @param int $month          Mois
     * @param int $year           Année
     * @param int $excludeId      ID à exclure (pour update)
     *
     * @return bool True si existe
     */
    public function exists($collaboratorId, $month, $year, $excludeId = 0)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration
                WHERE fk_collaborator = ".(int)$collaboratorId."
                AND declaration_month = ".(int)$month."
                AND declaration_year = ".(int)$year;

        if ($excludeId > 0) {
            $sql .= " AND rowid != ".(int)$excludeId;
        }

        $resql = $this->db->query($sql);

        if ($resql) {
            $exists = $this->db->num_rows($resql) > 0;
            $this->db->free($resql);
            return $exists;
        }

        return false;
    }

    /**
     * Récupère le total des montants prévisionnels (brouillons + validés) pour un collaborateur
     *
     * @param int   $collaboratorId ID du collaborateur
     * @param array $filters        Filtres optionnels:
     *                              - 'year' (int): Année de filtrage
     *
     * @return float Montant total prévisionnel
     */
    public function getTotalPrevisionnel($collaboratorId, $filters = [])
    {
        $stats = $this->getSalaryStatistics($collaboratorId, $filters);
        return $stats ? (float)$stats->montant_previsionnel : 0.0;
    }

    /**
     * Récupère le total des jours par statut pour un collaborateur
     *
     * @param int   $collaboratorId ID du collaborateur
     * @param array $filters        Filtres optionnels:
     *                              - 'year' (int): Année de filtrage
     *
     * @return object|false Objet contenant les totaux de jours par statut:
     *                      - jours_brouillons (float): Total jours brouillons
     *                      - jours_valides (float): Total jours validés
     *                      - jours_payes (float): Total jours payés
     *                      - jours_total (float): Total de tous les jours
     */
    public function getTotalDays($collaboratorId, $filters = [])
    {
        $stats = $this->getSalaryStatistics($collaboratorId, $filters);

        if ($stats) {
            return (object)[
                'jours_brouillons' => (float)$stats->jours_brouillons,
                'jours_valides' => (float)$stats->jours_valides,
                'jours_payes' => (float)$stats->jours_payes,
                'jours_total' => (float)($stats->jours_brouillons + $stats->jours_valides + $stats->jours_payes)
            ];
        }

        return false;
    }

    /**
     * Récupère toutes les déclarations avec détails agrégés (pour liste)
     *
     * @param array $filters Filtres optionnels:
     *                       - 'collaborator' (int): ID du collaborateur
     *                       - 'year' (int): Année de déclaration
     *                       - 'month' (int): Mois de déclaration
     *                       - 'status' (int): Statut (1=brouillon, 2=validé, 3=payé)
     *                       - 'page' (int): Numéro de page (défaut: 1)
     *                       - 'limit' (int): Nombre par page (défaut: 50)
     *
     * @return array Tableau associatif avec 'declarations', 'total', 'pages', 'current_page', 'per_page'
     */
    public function findAllWithDetails($filters = [])
    {
        $filter_collaborator = isset($filters['collaborator']) ? (int)$filters['collaborator'] : 0;
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;
        $filter_month = isset($filters['month']) ? (int)$filters['month'] : 0;
        $filter_status = isset($filters['status']) ? (int)$filters['status'] : 0;
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;

        // Construire la clause WHERE
        $where = "WHERE d.status IN (1, 2, 3)";

        if ($filter_collaborator > 0) {
            $where .= " AND d.fk_collaborator = ".$filter_collaborator;
        }
        if ($filter_year > 0) {
            $where .= " AND d.declaration_year = ".$filter_year;
        }
        if ($filter_month > 0) {
            $where .= " AND d.declaration_month = ".$filter_month;
        }
        if ($filter_status > 0) {
            $where .= " AND d.status = ".$filter_status;
        }

        // Compter le nombre total de déclarations
        $sql_count = "SELECT COUNT(DISTINCT d.rowid) as total
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
            ".$where;

        $resql_count = $this->db->query($sql_count);
        $total = 0;
        if ($resql_count) {
            $obj_count = $this->db->fetch_object($resql_count);
            $total = $obj_count ? (int)$obj_count->total : 0;
            $this->db->free($resql_count);
        }

        // Requête principale avec détails agrégés
        $sql = "SELECT d.*,
            c.label as collaborator_name,
            COUNT(det.rowid) as nb_days_worked,
            SUM(det.cachet_brut) as total_cachets_bruts,
            SUM(COALESCE(det.nb_heures, 8.00)) as total_heures
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_collaborator c ON c.rowid = d.fk_collaborator
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail det ON det.fk_declaration = d.rowid
            ".$where."
            GROUP BY d.rowid
            ORDER BY d.declaration_year DESC, d.declaration_month DESC, c.label ASC";

        // Ajouter la pagination
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT ".$limit." OFFSET ".$offset;

        $resql = $this->db->query($sql);

        if (!$resql) {
            return [
                'declarations' => [],
                'total' => 0,
                'pages' => 0,
                'current_page' => $page,
                'per_page' => $limit
            ];
        }

        $declarations = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $declarations[] = $obj;
        }
        $this->db->free($resql);

        $pages = $total > 0 ? ceil($total / $limit) : 0;

        return [
            'declarations' => $declarations,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page,
            'per_page' => $limit
        ];
    }

    /**
     * Récupère les statistiques agrégées des déclarations
     *
     * @param array $filters Mêmes filtres que findAllWithDetails
     *
     * @return object|false Objet avec totaux ou false
     */
    public function getAggregatedStats($filters = [])
    {
        $filter_collaborator = isset($filters['collaborator']) ? (int)$filters['collaborator'] : 0;
        $filter_year = isset($filters['year']) ? (int)$filters['year'] : 0;
        $filter_month = isset($filters['month']) ? (int)$filters['month'] : 0;
        $filter_status = isset($filters['status']) ? (int)$filters['status'] : 0;

        $where = "WHERE d.status IN (1, 2, 3)";

        if ($filter_collaborator > 0) {
            $where .= " AND d.fk_collaborator = ".$filter_collaborator;
        }
        if ($filter_year > 0) {
            $where .= " AND d.declaration_year = ".$filter_year;
        }
        if ($filter_month > 0) {
            $where .= " AND d.declaration_month = ".$filter_month;
        }
        if ($filter_status > 0) {
            $where .= " AND d.status = ".$filter_status;
        }

        // Requête principale sans JOIN pour éviter la multiplication des montants
        $sql = "SELECT
            COUNT(d.rowid) as total_declarations,
            COALESCE(SUM(d.masse_salariale), 0) as total_masse,
            COALESCE(SUM(d.solde_utilise), 0) as total_solde
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
            ".$where;

        // Deuxième requête pour obtenir le total des cachets
        $sql_cachets = "SELECT COALESCE(SUM(det.cachet_brut), 0) as total_cachets
            FROM ".MAIN_DB_PREFIX."revenuesharing_salary_declaration d
            LEFT JOIN ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail det ON det.fk_declaration = d.rowid
            ".$where;

        $resql = $this->db->query($sql);

        if (!$resql) {
            return false;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        if (!$obj) {
            return false;
        }

        // Exécuter la deuxième requête pour obtenir le total des cachets
        $resql_cachets = $this->db->query($sql_cachets);

        if ($resql_cachets) {
            $obj_cachets = $this->db->fetch_object($resql_cachets);
            if ($obj_cachets) {
                $obj->total_cachets = $obj_cachets->total_cachets;
            }
            $this->db->free($resql_cachets);
        }

        return $obj;
    }
}
?>
