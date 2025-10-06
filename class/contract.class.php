<?php
/**
 * Class for Revenue Sharing Contract
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class RevenueContract extends CommonObject
{
    public $element = 'revenuecontract';
    public $table_element = 'revenuesharing_contract';
    public $picto = 'contract';

    public $rowid;
    public $ref;
    public $fk_collaborator;
    public $fk_project;
    public $fk_facture;
    public $label;
    public $amount_ht;
    public $amount_ttc;
    public $collaborator_percentage;
    public $collaborator_amount_ht;
    public $studio_amount_ht;
    public $nb_sessions;
    public $cost_per_session;
    public $total_costs;
    public $net_collaborator_amount;
    public $status;
    public $note_private;
    public $note_public;

    public function __construct($db)
    {
        $this->db = $db;
        $this->status = 0; // Brouillon
    }

    /**
     * Create contract in database
     */
    public function create($user, $notrigger = false)
    {
        global $conf, $langs;

        $error = 0;

        // Generate ref
        if (empty($this->ref)) {
            $this->ref = $this->getNextNumRef();
        }

        // Calculate amounts
        $this->calculateAmounts();

        // Clean parameters
        if (isset($this->ref)) $this->ref = trim($this->ref);
        if (isset($this->label)) $this->label = trim($this->label);
        if (isset($this->note_private)) $this->note_private = trim($this->note_private);
        if (isset($this->note_public)) $this->note_public = trim($this->note_public);

        // Check parameters
        if (!$this->fk_collaborator > 0) {
            $this->errors[] = 'Error: fk_collaborator is required';
            return -1;
        }

        // Insert request
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element."(";
        $sql .= "ref,";
        $sql .= "fk_collaborator,";
        $sql .= "fk_project,";
        $sql .= "fk_facture,";
        $sql .= "label,";
        $sql .= "amount_ht,";
        $sql .= "amount_ttc,";
        $sql .= "collaborator_percentage,";
        $sql .= "collaborator_amount_ht,";
        $sql .= "studio_amount_ht,";
        $sql .= "nb_sessions,";
        $sql .= "cost_per_session,";
        $sql .= "total_costs,";
        $sql .= "net_collaborator_amount,";
        $sql .= "status,";
        $sql .= "note_private,";
        $sql .= "note_public,";
        $sql .= "date_creation,";
        $sql .= "fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= " '".$this->db->escape($this->ref)."',";
        $sql .= " ".((int) $this->fk_collaborator).",";
        $sql .= " ".(isset($this->fk_project) ? ((int) $this->fk_project) : "null").",";
        $sql .= " ".(isset($this->fk_facture) ? ((int) $this->fk_facture) : "null").",";
        $sql .= " ".(isset($this->label) ? "'".$this->db->escape($this->label)."'" : "null").",";
        $sql .= " ".((float) $this->amount_ht).",";
        $sql .= " ".((float) $this->amount_ttc).",";
        $sql .= " ".((float) $this->collaborator_percentage).",";
        $sql .= " ".((float) $this->collaborator_amount_ht).",";
        $sql .= " ".((float) $this->studio_amount_ht).",";
        $sql .= " ".((int) $this->nb_sessions).",";
        $sql .= " ".((float) $this->cost_per_session).",";
        $sql .= " ".((float) $this->total_costs).",";
        $sql .= " ".((float) $this->net_collaborator_amount).",";
        $sql .= " ".((int) $this->status).",";
        $sql .= " ".(isset($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "null").",";
        $sql .= " ".(isset($this->note_public) ? "'".$this->db->escape($this->note_public)."'" : "null").",";
        $sql .= " '".$this->db->idate(dol_now())."',";
        $sql .= " ".((int) $user->id);
        $sql .= ")";

        $this->db->begin();

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            $this->rowid = $this->id;
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
                $this->error .= ($this->error ? ', '.$errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return $this->id;
        }
    }

    /**
     * Calculate amounts based on percentage and costs
     */
    public function calculateAmounts()
    {
        if ($this->amount_ht > 0 && $this->collaborator_percentage > 0) {
            // Calcul du montant brut collaborateur
            $this->collaborator_amount_ht = ($this->amount_ht * $this->collaborator_percentage) / 100;

            // Calcul du montant studio
            $this->studio_amount_ht = $this->amount_ht - $this->collaborator_amount_ht;

            // Calcul des coûts totaux
            $this->total_costs = $this->nb_sessions * $this->cost_per_session;

            // Calcul du montant net collaborateur (après déduction des coûts)
            $this->net_collaborator_amount = $this->collaborator_amount_ht - $this->total_costs;
        }
    }

    /**
     * Get next reference number
     */
    public function getNextNumRef()
    {
        global $conf;

        $year = date('Y');
        $prefix = 'RC'.$year.'-';

        $sql = "SELECT MAX(CAST(SUBSTRING(ref, LENGTH('".$prefix."')+1) AS UNSIGNED)) as max_num";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE ref LIKE '".$prefix."%'";

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $num = $obj->max_num ? $obj->max_num + 1 : 1;
            return $prefix.sprintf('%04d', $num);
        }

        return $prefix.'0001';
    }
}
?>
