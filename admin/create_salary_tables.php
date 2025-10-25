<?php
/**
 * Script de création des tables pour les déclarations de salaires
 * Fichier: /htdocs/custom/revenuesharing/admin/create_salary_tables.php
 */

require_once '../../../main.inc.php';

// Security check
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}

llxHeader('', 'Création des tables de déclarations de salaires');

print load_fiche_titre('Création des tables de déclarations de salaires', '', 'generic');

// Table principale pour les déclarations mensuelles
$sql1 = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."revenuesharing_salary_declaration (
    rowid INTEGER PRIMARY KEY AUTO_INCREMENT,
    fk_collaborator INTEGER NOT NULL,
    declaration_month INTEGER NOT NULL,
    declaration_year INTEGER NOT NULL,
    total_days INTEGER DEFAULT 0,
    total_cachets DECIMAL(10,2) DEFAULT 0,
    cachet_brut_unitaire DECIMAL(10,2) DEFAULT 0,
    masse_salariale DECIMAL(10,2) DEFAULT 0,
    solde_utilise DECIMAL(10,2) DEFAULT 0,
    status INTEGER DEFAULT 1,
    note_private TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    fk_user_creat INTEGER,
    date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
    fk_user_modif INTEGER,
    UNIQUE KEY unique_collab_month (fk_collaborator, declaration_month, declaration_year),
    INDEX idx_collaborator (fk_collaborator),
    INDEX idx_date (declaration_year, declaration_month)
)";

// Table pour les détails journaliers
$sql2 = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."revenuesharing_salary_declaration_detail (
    rowid INTEGER PRIMARY KEY AUTO_INCREMENT,
    fk_declaration INTEGER NOT NULL,
    work_date DATE NOT NULL,
    cachet_brut DECIMAL(10,2) DEFAULT 0,
    nb_cachets DECIMAL(5,2) DEFAULT 1,
    type_contrat VARCHAR(50) DEFAULT 'CDDU',
    description TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fk_declaration) REFERENCES ".MAIN_DB_PREFIX."revenuesharing_salary_declaration(rowid) ON DELETE CASCADE,
    INDEX idx_declaration (fk_declaration),
    INDEX idx_work_date (work_date)
)";

print '<div style="margin: 20px 0;">';

// Exécuter les requêtes
$success = true;

print '<h3>Création de la table des déclarations mensuelles</h3>';
$resql1 = $db->query($sql1);
if ($resql1) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">Table revenuesharing_salary_declaration créée avec succès</div>';
} else {
    print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">Erreur lors de la création de la table: ' . $db->lasterror() . '</div>';
    $success = false;
}

print '<h3>Création de la table des détails journaliers</h3>';
$resql2 = $db->query($sql2);
if ($resql2) {
    print '<div style="color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0;">Table revenuesharing_salary_declaration_detail créée avec succès</div>';
} else {
    print '<div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0;">Erreur lors de la création de la table: ' . $db->lasterror() . '</div>';
    $success = false;
}

if ($success) {
    print '<div style="background: #e8f5e8; border: 1px solid #c3e6c3; border-radius: 8px; padding: 20px; margin: 20px 0;">';
    print '<h3 style="color: #2d7d2d; margin-top: 0;">🎉 Tables créées avec succès !</h3>';
    print '<p><strong>Tables créées :</strong></p>';
    print '<ul>';
    print '<li><code>revenuesharing_salary_declaration</code> : Déclarations mensuelles</li>';
    print '<li><code>revenuesharing_salary_declaration_detail</code> : Détails journaliers</li>';
    print '</ul>';
    print '<p><strong>Prochaines étapes :</strong></p>';
    print '<ul>';
    print '<li>Utiliser le menu "Déclarations Salaires" pour créer vos premières déclarations</li>';
    print '<li>Générer les PDF pour transmission au gestionnaire de paie</li>';
    print '</ul>';
    print '</div>';
}

print '</div>';

print '<div class="tabsAction">';
print '<a href="../salary_declarations_list.php" class="butAction">Voir les déclarations</a>';
print '<a href="../index.php" class="butAction">Retour Dashboard</a>';
print '</div>';

llxFooter();
$db->close();
?>