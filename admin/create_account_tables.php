<?php
// Fichier: admin/create_account_tables.php
// Création des tables pour le système de comptes collaborateurs

$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Création Tables Comptes Collaborateurs', '');

print load_fiche_titre('🏗️ Création des Tables - Comptes Collaborateurs', '', 'generic');

$errors = 0;
$success = 0;

// Table pour les transactions de compte
$sql_transactions = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."revenuesharing_account_transaction (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_collaborator INT NOT NULL,
    fk_contract INT NULL,
    fk_facture INT NULL,
    transaction_type ENUM('contract','commission','bonus','interest','advance','fee','refund','adjustment','other_credit','other_debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    fk_user_creat INT NOT NULL,
    note_private TEXT,
    status TINYINT DEFAULT 1,
    INDEX idx_collaborator (fk_collaborator),
    INDEX idx_contract (fk_contract),
    INDEX idx_facture (fk_facture),
    INDEX idx_date (transaction_date),
    INDEX idx_type (transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

print '<h3>🗄️ Création de la table des transactions de compte</h3>';
print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; font-family: monospace; font-size: 0.9em;">';
print nl2br(htmlentities($sql_transactions));
print '</div>';

$resql_transactions = $db->query($sql_transactions);
if ($resql_transactions) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">';
    print 'Table "'.MAIN_DB_PREFIX.'revenuesharing_account_transaction" créée avec succès';
    print '</div>';
    $success++;
} else {
    print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">';
    print 'Erreur lors de la création de la table transactions: '.$db->lasterror();
    print '</div>';
    $errors++;
}

// Table pour les soldes des comptes (pour optimisation)
$sql_balances = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."revenuesharing_account_balance (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_collaborator INT NOT NULL UNIQUE,
    total_credits DECIMAL(10,2) DEFAULT 0.00,
    total_debits DECIMAL(10,2) DEFAULT 0.00,
    current_balance DECIMAL(10,2) DEFAULT 0.00,
    last_transaction_date DATE,
    date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_collaborator (fk_collaborator)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

print '<h3>Création de la table des soldes de compte</h3>';
print '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; font-family: monospace; font-size: 0.9em;">';
print nl2br(htmlentities($sql_balances));
print '</div>';

$resql_balances = $db->query($sql_balances);
if ($resql_balances) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">';
    print 'Table "'.MAIN_DB_PREFIX.'revenuesharing_account_balance" créée avec succès';
    print '</div>';
    $success++;
} else {
    print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">';
    print 'Erreur lors de la création de la table balances: '.$db->lasterror();
    print '</div>';
    $errors++;
}

// Initialisation des soldes pour les collaborateurs existants
print '<h3>Initialisation des soldes existants</h3>';

$sql_init = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."revenuesharing_account_balance (fk_collaborator, current_balance)
SELECT rowid, 0.00 FROM ".MAIN_DB_PREFIX."revenuesharing_collaborator WHERE active = 1";

$resql_init = $db->query($sql_init);
if ($resql_init) {
    $nb_init = $db->affected_rows($resql_init);
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">';
    print ''.$nb_init.' compte(s) collaborateur initialisé(s)';
    print '</div>';
} else {
    print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">';
    print 'Erreur lors de l\'initialisation: '.$db->lasterror();
    print '</div>';
    $errors++;
}

// Résumé
print '<div style="background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0;">';
print '<h4>Résumé de la création</h4>';
print '<ul>';
print '<li><strong>Tables créées avec succès :</strong> '.$success.'</li>';
print '<li><strong>Erreurs rencontrées :</strong> '.$errors.'</li>';
print '</ul>';

if ($errors == 0) {
    print '<div style="color: green; font-weight: bold; margin-top: 15px;">';
    print '🎉 Système de comptes collaborateurs prêt ! Vous pouvez maintenant :';
    print '<ul style="margin-top: 10px;">';
    print '<li>Ajouter des opérations manuelles (commissions, bonus, etc.)</li>';
    print '<li>Suivre les soldes de chaque collaborateur</li>';
    print '<li>Générer des historiques de compte</li>';
    print '</ul>';
    print '</div>';
    
    print '<div style="text-align: center; margin-top: 20px;">';
    print '<a href="../account_list.php" class="button" style="background: #28a745; color: white;">Voir les comptes</a> ';
    print '<a href="../account_transaction.php" class="button" style="background: #fd7e14; color: white;"> Nouvelle opération</a>';
    print '</div>';
}

print '</div>';

print '<div class="tabsAction">';
print '<a href="setup.php" class="butAction">Configuration</a>';
print '<a href="../index.php" class="butAction">Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>