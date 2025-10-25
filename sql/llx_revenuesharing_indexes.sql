-- ============================================================================
-- SQL Indexes for Performance Optimization
-- Module Revenue Sharing
-- ============================================================================

-- Index pour la table revenuesharing_account_transaction
-- ============================================================================

-- Index sur fk_collaborator (utilisé dans presque toutes les requêtes)
CREATE INDEX IF NOT EXISTS idx_transaction_collaborator
ON llx_revenuesharing_account_transaction(fk_collaborator);

-- Index sur status (filtrage par statut)
CREATE INDEX IF NOT EXISTS idx_transaction_status
ON llx_revenuesharing_account_transaction(status);

-- Index composite pour les recherches par collaborateur + année
CREATE INDEX IF NOT EXISTS idx_transaction_collab_date
ON llx_revenuesharing_account_transaction(fk_collaborator, transaction_date);

-- Index sur fk_facture pour les JOINs avec factures clients
CREATE INDEX IF NOT EXISTS idx_transaction_facture
ON llx_revenuesharing_account_transaction(fk_facture);

-- Index sur fk_facture_fourn pour les JOINs avec factures fournisseurs
CREATE INDEX IF NOT EXISTS idx_transaction_facture_fourn
ON llx_revenuesharing_account_transaction(fk_facture_fourn);

-- Index sur fk_contract pour les JOINs avec contrats
CREATE INDEX IF NOT EXISTS idx_transaction_contract
ON llx_revenuesharing_account_transaction(fk_contract);

-- Index sur transaction_type pour filtrage par type
CREATE INDEX IF NOT EXISTS idx_transaction_type
ON llx_revenuesharing_account_transaction(transaction_type);

-- Index composite pour pagination optimisée
CREATE INDEX IF NOT EXISTS idx_transaction_collab_status_date
ON llx_revenuesharing_account_transaction(fk_collaborator, status, transaction_date DESC);


-- Index pour la table revenuesharing_contract
-- ============================================================================

-- Index sur fk_collaborator
CREATE INDEX IF NOT EXISTS idx_contract_collaborator
ON llx_revenuesharing_contract(fk_collaborator);

-- Index sur fk_facture
CREATE INDEX IF NOT EXISTS idx_contract_facture
ON llx_revenuesharing_contract(fk_facture);

-- Index sur type_contrat (réel/prévisionnel)
CREATE INDEX IF NOT EXISTS idx_contract_type
ON llx_revenuesharing_contract(type_contrat);

-- Index sur status
CREATE INDEX IF NOT EXISTS idx_contract_status
ON llx_revenuesharing_contract(status);

-- Index composite pour recherches par collaborateur + statut
CREATE INDEX IF NOT EXISTS idx_contract_collab_status
ON llx_revenuesharing_contract(fk_collaborator, status);

-- Index sur date_facturation_prevue pour tri/filtrage
CREATE INDEX IF NOT EXISTS idx_contract_date_facture_prevue
ON llx_revenuesharing_contract(date_facturation_prevue);


-- Index pour la table revenuesharing_salary_declaration
-- ============================================================================

-- Index sur fk_collaborator
CREATE INDEX IF NOT EXISTS idx_salary_declaration_collaborator
ON llx_revenuesharing_salary_declaration(fk_collaborator);

-- Index sur declaration_year pour filtrage par année
CREATE INDEX IF NOT EXISTS idx_salary_declaration_year
ON llx_revenuesharing_salary_declaration(declaration_year);

-- Index sur declaration_month pour filtrage par mois
CREATE INDEX IF NOT EXISTS idx_salary_declaration_month
ON llx_revenuesharing_salary_declaration(declaration_month);

-- Index sur status
CREATE INDEX IF NOT EXISTS idx_salary_declaration_status
ON llx_revenuesharing_salary_declaration(status);

-- Index composite pour recherches fréquentes
CREATE INDEX IF NOT EXISTS idx_salary_decl_collab_year_month
ON llx_revenuesharing_salary_declaration(fk_collaborator, declaration_year, declaration_month);

-- Index composite pour tri par année/mois
CREATE INDEX IF NOT EXISTS idx_salary_decl_year_month_desc
ON llx_revenuesharing_salary_declaration(declaration_year DESC, declaration_month DESC);


-- Index pour la table revenuesharing_salary_declaration_detail
-- ============================================================================

-- Index sur fk_declaration
CREATE INDEX IF NOT EXISTS idx_salary_detail_declaration
ON llx_revenuesharing_salary_declaration_detail(fk_declaration);

-- Index sur date_travail pour tri/filtrage
CREATE INDEX IF NOT EXISTS idx_salary_detail_date
ON llx_revenuesharing_salary_declaration_detail(date_travail);

-- Index composite pour agrégations
CREATE INDEX IF NOT EXISTS idx_salary_detail_decl_date
ON llx_revenuesharing_salary_declaration_detail(fk_declaration, date_travail);


-- Index pour la table revenuesharing_collaborator
-- ============================================================================

-- Index sur active pour filtrer les collaborateurs actifs
CREATE INDEX IF NOT EXISTS idx_collaborator_active
ON llx_revenuesharing_collaborator(active);

-- Index sur label pour tri alphabétique
CREATE INDEX IF NOT EXISTS idx_collaborator_label
ON llx_revenuesharing_collaborator(label);

-- Index composite pour recherches actifs triés
CREATE INDEX IF NOT EXISTS idx_collaborator_active_label
ON llx_revenuesharing_collaborator(active, label);


-- ============================================================================
-- ANALYSE DES TABLES après création des index
-- ============================================================================

ANALYZE TABLE llx_revenuesharing_account_transaction;
ANALYZE TABLE llx_revenuesharing_contract;
ANALYZE TABLE llx_revenuesharing_salary_declaration;
ANALYZE TABLE llx_revenuesharing_salary_declaration_detail;
ANALYZE TABLE llx_revenuesharing_collaborator;

-- ============================================================================
-- Notes d'utilisation:
--
-- Ces index améliorent significativement les performances pour:
-- 1. Recherches par collaborateur (index sur fk_collaborator)
-- 2. Filtrages par année/mois (index sur dates)
-- 3. JOINs avec factures et contrats (index sur clés étrangères)
-- 4. Tri et pagination (index composites)
-- 5. Agrégations et GROUP BY (index composites)
--
-- Impact estimé:
-- - Requêtes de balance: 60-80% plus rapides
-- - Recherches paginées: 70-90% plus rapides
-- - Agrégations par type: 50-70% plus rapides
-- ============================================================================
