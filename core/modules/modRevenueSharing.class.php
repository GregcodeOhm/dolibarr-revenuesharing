<?php
/**
 * Description and activation class for module RevenueSharing
 * Fichier: /htdocs/custom/revenuesharing/core/modules/modRevenueSharing.class.php
 */

// Protection
if (!defined('DOL_BASE_PATH')) {
    define('DOL_BASE_PATH', dirname(__FILE__).'/../../../..');
}

require_once DOL_BASE_PATH.'/core/modules/DolibarrModules.class.php';

class modRevenueSharing extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Id du module (doit être unique)
        $this->numero = 104000;

        // Key text used to identify module
        $this->rights_class = 'revenuesharing';

        // Family
        $this->family = "financial";
        $this->module_position = '90';

        // Module label
        $this->name = "RevenueSharing";
        $this->description = "Gestion du partage de revenus avec les collaborateurs";

        // Possible values for version
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Name of image file used for this module
        $this->picto = 'generic';

        // Data directories to create when module is enabled
        $this->dirs = array("/revenuesharing/temp");

        // Config pages
        $this->config_page_url = array("setup.php@revenuesharing");

        // Dependencies
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("revenuesharing@revenuesharing");
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(11, 0);

        // Constants
        $this->const = array();

        // Permissions
        $this->rights = array();
        $r = 0;

        // Permission lire
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Lire les données de partage de revenus';
        $this->rights[$r][4] = 'revenuesharing';
        $this->rights[$r][5] = 'read';
        $r++;

        // Permission créer/modifier
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Créer/modifier les contrats de partage';
        $this->rights[$r][4] = 'revenuesharing';
        $this->rights[$r][5] = 'write';
        $r++;

        // Permission supprimer
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Supprimer les contrats de partage';
        $this->rights[$r][4] = 'revenuesharing';
        $this->rights[$r][5] = 'delete';
        $r++;

        // Main menu entries
        $this->menu = array();
        $r = 0;

        // Menu principal dans la barre du haut
        $this->menu[$r] = array(
            'fk_menu' => '',              // Menu parent (vide = menu racine)
            'type' => 'top',              // Type de menu
            'titre' => 'Revenue Sharing', // Titre affiché
            'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
            'mainmenu' => 'revenuesharing',
            'leftmenu' => '',
            'url' => '/custom/revenuesharing/index.php',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '1',  // Accessible à tous les utilisateurs connectés
            'target' => '',
            'user' => 2,  // 0=Menu pour utilisateurs internes, 1=Menu pour utilisateurs externes, 2=Menu pour les deux
            'object' => ''
        );
        $r++;

        // Sous-menu Dashboard
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=revenuesharing',
            'type' => 'left',
            'titre' => 'Dashboard',
            'mainmenu' => 'revenuesharing',
            'leftmenu' => 'revenuesharing_dashboard',
            'url' => '/custom/revenuesharing/index.php',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '1',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Sous-menu Collaborateurs
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=revenuesharing',
            'type' => 'left',
            'titre' => 'Collaborateurs',
            'mainmenu' => 'revenuesharing',
            'leftmenu' => 'revenuesharing_collaborators',
            'url' => '/custom/revenuesharing/collaborator_list.php',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '1',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Sous-menu Nouveau Collaborateur
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=revenuesharing,fk_leftmenu=revenuesharing_collaborators',
            'type' => 'left',
            'titre' => 'Nouveau Collaborateur',
            'mainmenu' => 'revenuesharing',
            'leftmenu' => 'revenuesharing_collaborators_new',
            'url' => '/custom/revenuesharing/collaborator_card.php?action=create',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '1',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Sous-menu Contrats
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=revenuesharing',
            'type' => 'left',
            'titre' => 'Contrats',
            'mainmenu' => 'revenuesharing',
            'leftmenu' => 'revenuesharing_contracts',
            'url' => '/custom/revenuesharing/contract_list.php',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '1',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Sous-menu Nouveau Contrat
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=revenuesharing,fk_leftmenu=revenuesharing_contracts',
            'type' => 'left',
            'titre' => 'Nouveau Contrat',
            'mainmenu' => 'revenuesharing',
            'leftmenu' => 'revenuesharing_contracts_new',
            'url' => '/custom/revenuesharing/contract_card.php?action=create',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '1',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Sous-menu Configuration (pour les admins)
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=revenuesharing',
            'type' => 'left',
            'titre' => 'Configuration',
            'mainmenu' => 'revenuesharing',
            'leftmenu' => 'revenuesharing_config',
            'url' => '/custom/revenuesharing/admin/setup.php',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Sous-menu Import Excel (pour les admins)
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=revenuesharing,fk_leftmenu=revenuesharing_config',
            'type' => 'left',
            'titre' => 'Import Excel',
            'mainmenu' => 'revenuesharing',
            'leftmenu' => 'revenuesharing_import',
            'url' => '/custom/revenuesharing/admin/import_excel.php',
            'langs' => 'revenuesharing@revenuesharing',
            'position' => 1000 + $r,
            'enabled' => 'isModEnabled("revenuesharing")',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 2
        );
        $r++;
    }

    /**
     * Function called when module is enabled
     */
    public function init($options = '')
    {
        global $conf, $langs, $user;

        // Load SQL files
        $result = $this->_load_tables('/revenuesharing/sql/');
        if ($result < 0) {
            return -1;
        }

        // Configuration par défaut
        dolibarr_set_const($this->db, "REVENUESHARING_DEFAULT_PERCENTAGE", "60", 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, "REVENUESHARING_AUTO_CREATE_CONTRACT", "1", 'chaine', 0, '', $conf->entity);

        // Création des répertoires
        if (!empty($this->dirs)) {
            foreach ($this->dirs as $dir) {
                $dir_path = DOL_DATA_ROOT.$dir;
                if (!file_exists($dir_path)) {
                    dol_mkdir($dir_path);
                }
            }
        }

        return $this->_init($options);
    }

    /**
     * Function called when module is disabled
     */
    public function remove($options = '')
    {
        global $conf;

        // Suppression des constantes
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'REVENUESHARING_%'";
        $this->db->query($sql);

        return $this->_remove($options);
    }
}
?>
