<?php
// Script de création automatique des tables
// À placer dans /htdocs/custom/revenuesharing/admin/create_tables.php

$dolibarr_main_document_root = dirname(__FILE__).'/../../..';
require_once $dolibarr_main_document_root.'/main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'alpha');

llxHeader('', 'Création des Tables Revenue Sharing', '');

print load_fiche_titre('Création des Tables Revenue Sharing', '', 'generic');

if ($action == 'create') {
    $errors = 0;
    $success = 0;

    // Table collaborateurs
    $sql1 = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."revenuesharing_collaborator (
        rowid INTEGER PRIMARY KEY AUTO_INCREMENT,
        fk_user INTEGER NOT NULL,
        label VARCHAR(255),
        default_percentage DECIMAL(5,2) DEFAULT 60.00,
        cost_per_session DECIMAL(10,2) DEFAULT 0,
        active TINYINT DEFAULT 1,
        note_private TEXT,
        note_public TEXT,
        date_creation DATETIME,
        date_modification DATETIME,
        fk_user_creat INTEGER,
        fk_user_modif INTEGER,
        INDEX idx_fk_user (fk_user),
        INDEX idx_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $resql1 = $db->query($sql1);
    if ($resql1) {
        print '<div class="ok">Table llx_revenuesharing_collaborator créée</div>';
        $success++;
    } else {
        print '<div class="error">Erreur création table collaborateur: '.$db->lasterror().'</div>';
        $errors++;
    }

    // Table contrats
    $sql2 = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."revenuesharing_contract (
        rowid INTEGER PRIMARY KEY AUTO_INCREMENT,
        ref VARCHAR(30) NOT NULL,
        fk_collaborator INTEGER NOT NULL,
        fk_project INTEGER,
        fk_facture INTEGER,
        label VARCHAR(255),
        amount_ht DECIMAL(10,2),
        amount_ttc DECIMAL(10,2),
        collaborator_percentage DECIMAL(5,2),
        collaborator_amount_ht DECIMAL(10,2),
        studio_amount_ht DECIMAL(10,2),
        nb_sessions INTEGER DEFAULT 0,
        cost_per_session DECIMAL(10,2) DEFAULT 0,
        total_costs DECIMAL(10,2) DEFAULT 0,
        net_collaborator_amount DECIMAL(10,2),
        status INTEGER DEFAULT 0,
        note_private TEXT,
        note_public TEXT,
        date_creation DATETIME,
        date_modification DATETIME,
        date_valid DATETIME,
        fk_user_creat INTEGER,
        fk_user_modif INTEGER,
        fk_user_valid INTEGER,
        INDEX idx_ref (ref),
        INDEX idx_fk_collaborator (fk_collaborator),
        INDEX idx_fk_project (fk_project),
        INDEX idx_fk_facture (fk_facture),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $resql2 = $db->query($sql2);
    if ($resql2) {
        print '<div class="ok">Table llx_revenuesharing_contract créée</div>';
        $success++;
    } else {
        print '<div class="error">Erreur création table contrat: '.$db->lasterror().'</div>';
        $errors++;
    }

    // Création collaborateur Tony par défaut
    $sql3 = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."revenuesharing_collaborator
            (fk_user, label, default_percentage, active, date_creation, fk_user_creat)
            VALUES
            (".$user->id.", 'Tony Ohmnibus', 60.00, 1, NOW(), ".$user->id.")";

    $resql3 = $db->query($sql3);
    if ($resql3) {
        print '<div class="ok">Collaborateur Tony créé par défaut</div>';
        $success++;
    } else {
        print '<div class="warning">Collaborateur Tony déjà existant ou erreur: '.$db->lasterror().'</div>';
    }

    if ($errors == 0) {
        print '<br><div class="ok"><strong>🎉 Installation terminée avec succès !</strong><br>';
        print 'Vous pouvez maintenant utiliser le module Revenue Sharing.</div>';
        print '<div class="tabsAction">';
        print '<a href="setup.php" class="butAction">Retour à la configuration</a>';
        print '<a href="../index.php" class="butAction">Voir le Dashboard</a>';
        print '</div>';
    }
}

// Vérification de l'état actuel
print '<h3>État actuel des tables</h3>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Table</td><td>Statut</td><td>Nombre d\'enregistrements</td></tr>';

$tables = [
    'revenuesharing_collaborator' => 'Collaborateurs',
    'revenuesharing_contract' => 'Contrats'
];

foreach ($tables as $table => $desc) {
    $table_name = MAIN_DB_PREFIX.$table;

    // Vérifier si la table existe
    $sql = "SHOW TABLES LIKE '$table_name'";
    $resql = $db->query($sql);

    if ($resql && $db->num_rows($resql) > 0) {
        // Table existe, compter les enregistrements
        $sql_count = "SELECT COUNT(*) as nb FROM $table_name";
        $resql_count = $db->query($sql_count);
        $nb = 0;
        if ($resql_count) {
            $obj = $db->fetch_object($resql_count);
            $nb = $obj->nb;
        }

        print '<tr class="oddeven">';
        print '<td>'.$desc.'</td>';
        print '<td><span style="color: green;">Existe</span></td>';
        print '<td>'.$nb.'</td>';
        print '</tr>';
    } else {
        print '<tr class="oddeven">';
        print '<td>'.$desc.'</td>';
        print '<td><span style="color: red;">N\'existe pas</span></td>';
        print '<td>-</td>';
        print '</tr>';
    }
}

print '</table>';

// Bouton de création si tables manquantes
print '<br>';
if ($action != 'create') {
    print '<div class="tabsAction">';
    print '<a href="'.$_SERVER['PHP_SELF'].'?action=create" class="butAction">Créer les tables manquantes</a>';
    print '<a href="setup.php" class="butAction">← Retour à la configuration</a>';
    print '</div>';
}

print '<div class="info">';
print '<strong>Note :</strong> Cette opération est sécurisée et peut être exécutée plusieurs fois sans risque.';
print '</div>';

llxFooter();
$db->close();
?>

<style>
.ok {
    color: green;
    padding: 10px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 3px;
    margin: 10px 0;
}
.error {
    color: red;
    padding: 10px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 3px;
    margin: 10px 0;
}
.warning {
    color: orange;
    padding: 10px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 3px;
    margin: 10px 0;
}
.info {
    color: blue;
    padding: 10px;
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 3px;
    margin: 10px 0;
}
</style>
