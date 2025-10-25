<?php
/**
 * Class for Collaborator
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class Collaborator extends CommonObject
{
    public $element = 'collaborator';
    public $table_element = 'revenuesharing_collaborator';
    public $picto = 'user';

    public $rowid;
    public $fk_user;
    public $label;
    public $default_percentage;
    public $cost_per_session;
    public $active;
    public $note_private;
    public $note_public;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create collaborator in database
     */
    public function create($user, $notrigger = false)
    {
        global $conf, $langs;

        $error = 0;

        // Clean parameters
        if (isset($this->label)) $this->label = trim($this->label);
        if (isset($this->default_percentage)) $this->default_percentage = trim($this->default_percentage);
        if (isset($this->cost_per_session)) $this->cost_per_session = trim($this->cost_per_session);
        if (isset($this->active)) $this->active = trim($this->active);
        if (isset($this->note_private)) $this->note_private = trim($this->note_private);
        if (isset($this->note_public)) $this->note_public = trim($this->note_public);

        // Check parameters
        if (!$this->fk_user > 0) {
            $this->errors[] = 'Error: fk_user is required';
            return -1;
        }

        // Insert request
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element."(";
        $sql .= "fk_user,";
        $sql .= "label,";
        $sql .= "default_percentage,";
        $sql .= "cost_per_session,";
        $sql .= "active,";
        $sql .= "note_private,";
        $sql .= "note_public,";
        $sql .= "date_creation,";
        $sql .= "fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= " ".((int) $this->fk_user).",";
        $sql .= " ".(isset($this->label) ? "'".$this->db->escape($this->label)."'" : "null").",";
        $sql .= " ".((float) $this->default_percentage).",";
        $sql .= " ".((float) $this->cost_per_session).",";
        $sql .= " ".((int) $this->active).",";
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
     * Load object in memory from the database
     */
    public function fetch($id, $ref = null)
    {
        global $langs;

        $sql = "SELECT";
        $sql .= " t.rowid,";
        $sql .= " t.fk_user,";
        $sql .= " t.label,";
        $sql .= " t.default_percentage,";
        $sql .= " t.cost_per_session,";
        $sql .= " t.active,";
        $sql .= " t.note_private,";
        $sql .= " t.note_public,";
        $sql .= " t.date_creation,";
        $sql .= " t.date_modification,";
        $sql .= " t.fk_user_creat,";
        $sql .= " t.fk_user_modif";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
        $sql .= " WHERE t.rowid = ".((int) $id);

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->rowid = $obj->rowid;
                $this->fk_user = $obj->fk_user;
                $this->label = $obj->label;
                $this->default_percentage = $obj->default_percentage;
                $this->cost_per_session = $obj->cost_per_session;
                $this->active = $obj->active;
                $this->note_private = $obj->note_private;
                $this->note_public = $obj->note_public;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->date_modification = $this->db->jdate($obj->date_modification);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;
            }

            $this->db->free($resql);

            return 1;
        } else {
            $this->error = "Error ".$this->db->lasterror();
            return -1;
        }
    }
}
?>
