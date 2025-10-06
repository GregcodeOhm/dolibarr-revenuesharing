# Module Revenue Sharing - Dolibarr

Module de gestion du partage de revenus pour les travailleurs intermittents du spectacle.

## 🎯 Fonctionnalités principales

- **Gestion des collaborateurs** : Création et suivi des intermittents
- **Contrats prévisionnels et réels** : Estimation puis transformation en contrats facturés
- **Compte collaborateur** : Suivi des revenus, avances, commissions
- **Déclarations de salaires** : Gestion des feuilles de paie avec barèmes
- **Exports** : PDF et Excel pour les relevés de compte

## 📁 Structure des fichiers

### Fichiers principaux
- `index.php` - Page d'accueil du module
- `collaborator_list.php` / `collaborator_card.php` - Gestion des collaborateurs
- `contract_list.php` / `contract_card_complete.php` - Gestion des contrats
- `contract_previsionnel_form.php` - Création de contrats prévisionnels
- `transform_previsionnel.php` - Transformation prévisionnel → réel
- `account_list.php` / `account_detail.php` - Comptes collaborateurs
- `account_transaction.php` - Transactions comptables

### Déclarations de salaires
- `salary_declarations_list.php` - Liste des déclarations
- `salary_declaration_form.php` - Création/édition
- `salary_declaration_detail.php` - Détail d'une déclaration
- `salary_declaration_export.php` - Export feuille de paie

### Exports et utilitaires
- `export_account.php` - Export compte collaborateur
- `export_all_accounts.php` - Export global
- `transaction_documents.php` - Gestion des pièces jointes

### API/AJAX
- `get_*.php` - APIs pour autocomplétion et données dynamiques
- `ajax_*.php` - Endpoints AJAX

### 📁 Dossiers
- `admin/` - Scripts d'administration et configuration
- `class/` - Classes PHP du module
- `core/` - Configuration du module Dolibarr
- `sql/` - Scripts de création des tables
- `langs/` - Fichiers de traduction
- `lib/` - Bibliothèques utilitaires
- `documentation/` - Documentation technique et fonctionnelle

## 🚀 Installation

1. Copier le dossier dans `/htdocs/custom/revenuesharing/`
2. Activer le module dans Configuration > Modules
3. Exécuter les scripts d'admin si nécessaire

## 📋 Tables créées

- `revenuesharing_collaborator` - Collaborateurs
- `revenuesharing_contract` - Contrats
- `revenuesharing_account_transaction` - Transactions comptables
- `revenuesharing_salary_declaration` - Déclarations de salaires
- `revenuesharing_salary_heures` - Barème horaire
- `revenuesharing_salary_metiers` - Barème métiers

## 🔧 Configuration

Voir `/admin/setup.php` pour la configuration du module.

---
*Module développé pour la gestion des revenus partagés - Version production*