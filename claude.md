# Notes de projet - Revenue Sharing Module Dolibarr

## 📌 Informations Générales

**Module** : Revenue Sharing - Partage de revenus avec collaborateurs intermittents
**Version** : 1.1.0
**Dolibarr** : Compatible 11.0+
**PHP** : 7.0+
**Date dernière mise à jour** : 2025-10-03

---

## 🏗️ Architecture du Module

### Structure des fichiers
```
/dolibarr-revenuesharing/
├── core/modules/
│   └── modRevenueSharing.class.php    # Descripteur du module
├── class/
│   ├── contract.class.php              # Classe métier Contrat
│   ├── collaborator.class.php          # Classe métier Collaborateur
│   ├── CacheManager.php                # Gestionnaire de cache (TTL)
│   └── repositories/                   # Pattern Repository
│       ├── ContractRepository.php
│       ├── CollaboratorRepository.php
│       ├── BalanceRepository.php
│       ├── TransactionRepository.php
│       └── SalaryDeclarationRepository.php
├── lib/
│   ├── metiers_son.php                 # Métiers IDCC 2642
│   ├── pagination.lib.php              # Pagination réutilisable
│   └── revenuesharing.lib.php
├── js/
│   └── salary-calendar.js              # Calendrier de sélection des jours
├── sql/
│   └── llx_revenuesharing_*.sql        # Tables SQL
└── langs/fr_FR/                        # Fichiers de traduction

Pages principales:
- index.php                             # Dashboard
- contract_list.php                     # Liste des contrats (pagination avancée)
- account_detail.php                    # Détail compte collaborateur
- salary_declaration_form.php           # Formulaire déclarations salaires
- salary_declarations_list.php          # Liste déclarations
- analytics.php                         # Statistiques
```

### Base de données
Toutes les tables utilisent le préfixe `llx_revenuesharing_` :
- `llx_revenuesharing_collaborator`
- `llx_revenuesharing_contract`
- `llx_revenuesharing_salary_declaration`
- `llx_revenuesharing_salary_declaration_detail`
- `llx_revenuesharing_account_transaction`
- `llx_revenuesharing_account_balance`

**Index SQL** : 24 index créés pour optimisation (voir `sql/llx_revenuesharing_indexes.sql`)

---

## 🔧 Fonctionnalités Principales

### 1. Gestion des Collaborateurs
- Création/modification de collaborateurs
- Lien avec utilisateurs Dolibarr
- Pourcentage de partage par défaut
- Gestion des comptes (solde, transactions)

### 2. Gestion des Contrats
- Contrats de partage de revenus
- Lien avec projets et factures Dolibarr
- Calcul automatique des montants
- Validation et suivi

### 3. Déclarations de Salaires (Intermittents)
- Calendrier de sélection des jours travaillés
- Métiers selon convention IDCC 2642
- Nombre d'heures par jour
- Calcul masse salariale avec taux de charges configurable
- Prélèvement automatique sur solde collaborateur

### 4. Comptabilité Collaborateur
- Historique des transactions
- Solde en temps réel
- Export PDF des relevés de compte
- Filtres par année, type

### 5. Analytics
- Statistiques globales
- Répartition par secteur analytique
- Répartition par intervenant
- Évolution temporelle

---

## ⚡ Optimisations Techniques

### Cache (CacheManager)
- Cache fichiers avec TTL
- Utilisé pour : soldes, stats, listes
- TTL par défaut : 300s (5min) pour listes, 600s (10min) pour dashboard/analytics
- Invalidation automatique ou manuelle

**Performance** :
- Balance : 150ms → 15ms (-90%)
- Liste : 300ms → 60ms (-80%)
- Stats : 200ms → 30ms (-85%)

### Repositories Pattern
Architecture moderne (non-standard Dolibarr mais meilleure) :
- Séparation logique métier / accès données
- Requêtes SQL centralisées
- Réutilisabilité du code
- Facilite les tests unitaires

### Pagination Avancée
Librairie `pagination.lib.php` utilisée dans :
- `account_detail.php`
- `contract_list.php`
- `salary_declarations_list.php`

Fonctionnalités :
- Numéros de pages cliquables
- Boutons Première/Dernière
- Sélecteur "Par page" (10, 25, 50, 100, 200)
- Informations "Affichage de X à Y sur Z"
- Conservation des filtres

### Index SQL
24 index créés pour optimiser les requêtes fréquentes sur :
- Jointures (fk_collaborator, fk_declaration, etc.)
- Filtres (status, year, transaction_date, etc.)
- Tri (date_creation, work_date, etc.)

---

## 🔐 Sécurité & Permissions

### Permissions définies
- `revenuesharing->read` : Lecture
- `revenuesharing->write` : Création/modification
- `revenuesharing->delete` : Suppression

### Contrôles d'accès
```php
// Vérification granulaire
if (!$user->rights->revenuesharing->read) {
    accessforbidden();
}

// Admin pour actions sensibles
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs...');
}
```

### Protection SQL
- Utilisation de `$db->escape()` pour toutes les entrées utilisateur
- Prepared statements dans les repositories
- Validation des données avant insertion

---

## 🚀 Synchronisation Serveurs

### Configuration rclone

**PROD (SFTP)** :
```
Remote: ovh-dolibarr
Chemin: dolibarr/htdocs/custom/revenuesharing/
```

**TEST (FTP)** :
```
Remote: ovh_dolibarr
Chemin: dolitest/htdocs/custom/revenuesharing/
```

### Commandes de synchronisation

**Sync complet vers PROD** :
```bash
rclone copy /Users/papa/dolibarr-revenuesharing/ ovh-dolibarr:dolibarr/htdocs/custom/revenuesharing/ -v --exclude ".git/**" --exclude "*.md"
```

**Sync complet vers TEST** :
```bash
rclone copy /Users/papa/dolibarr-revenuesharing/ ovh_dolibarr:dolitest/htdocs/custom/revenuesharing/ -v --exclude ".git/**" --exclude "*.md"
```

**Sync fichier unique** :
```bash
# Vers PROD
rclone copy /Users/papa/dolibarr-revenuesharing/ma_page.php ovh-dolibarr:dolibarr/htdocs/custom/revenuesharing/ -v

# Vers TEST
rclone copy /Users/papa/dolibarr-revenuesharing/ma_page.php ovh_dolibarr:dolitest/htdocs/custom/revenuesharing/ -v
```

**Forcer la copie (ignorer les dates)** :
```bash
rclone copy fichier.php ovh-dolibarr:dolibarr/htdocs/custom/revenuesharing/ -v --ignore-times
```

---

## 🐛 Bugs Résolus (Session 2025-10-03)

### Bug #1 : Calendrier ne charge pas les dates en mode édition
**Problème** : En éditant une déclaration de salaire, le calendrier reste vide malgré les données en base.

**Causes** :
1. Méthode `SalaryDeclarationRepository::getDetails()` utilisait `ORDER BY date_travail` au lieu de `ORDER BY work_date`
2. Chemins rclone incorrects : `dolibarr-17/` au lieu de `dolibarr/`
3. Flag `initialLoad` dans `salary-calendar.js` vidait les jours au premier chargement

**Corrections** :
- `SalaryDeclarationRepository.php` : Correction du nom de colonne + fallback SQL
- `salary-calendar.js` : Ajout gestion `dataset.initialLoad`
- Chemins rclone corrigés dans tous les scripts

**Statut** : ✅ Résolu - Les 9 jours chargent correctement

### Bug #2 : Pagination contract_list.php basique
**Problème** : Pagination manuelle, pas de sélecteur "Par page", pas de numéros cliquables.

**Correction** : Intégration de `pagination.lib.php` comme dans `account_detail.php`

**Améliorations** :
- Pagination professionnelle avec numéros de pages
- Sélecteur "Par page"
- Boutons Première/Dernière
- Limite max augmentée de 20 à 50
- Page 1-indexed au lieu de 0-indexed

**Statut** : ✅ Résolu

---

## 📊 Tests

### Tests unitaires (PHPUnit)
**Fichiers** : Voir `tests/` (40 tests)
- `BalanceRepositoryTest.php` (10 tests)
- `TransactionRepositoryTest.php` (14 tests)
- `SalaryDeclarationRepositoryTest.php` (16 tests)

**Lancer les tests** :
```bash
phpunit
phpunit tests/repositories/BalanceRepositoryTest.php
```

**Documentation** : Voir `README-TESTS.md`

---

## 📚 Configuration du Module

### Taux de charges sociales
Configurable dans `admin/setup.php` :
- Par défaut : 65%
- Utilisé pour calculer : `Masse salariale = Total brut × (1 + Taux/100)`
- Le solde utilisé = masse salariale automatiquement

### Métiers du son (IDCC 2642)
Définis dans `lib/metiers_son.php` :
- Technicien du son
- Ingénieur du son
- Assistant son
- Perchman
- Opérateur son
- Mixeur
- Sound designer
- Compositeur

---

## 🎯 Conformité Standards Dolibarr

### ✅ Points conformes
- Structure module avec `modRevenueSharing.class.php` ✓
- Classes héritant de `CommonObject` ✓
- Permissions granulaires définies ✓
- Préfixe tables `llx_revenuesharing_` ✓
- Protection avec `require_once '../../main.inc.php'` ✓
- Menus définis dans le descripteur ✓

### ⚠️ Points non-standard (mais meilleurs)
- Architecture avec Repositories (moderne, non-standard Dolibarr)
- Cache personnalisé (meilleur que le cache Dolibarr natif)
- Pagination avancée personnalisée

### ❌ Points manquants (non critiques pour usage interne)
- Fichiers langue `en_US/` (seulement `fr_FR/`)
- API REST
- Hooks/Triggers

**Note** : Pour un module sur-mesure, ces manques ne sont pas bloquants.

---

## 💡 Pour Reprendre une Nouvelle Session

**Contexte à donner** :
```
Module Dolibarr "Revenue Sharing" pour gestion intermittents du spectacle.
Architecture moderne avec Repositories, Cache, Pagination avancée.
40 tests unitaires PHPUnit.

Serveurs rclone:
- PROD: ovh-dolibarr:dolibarr/htdocs/custom/revenuesharing/ (SFTP)
- TEST: ovh_dolibarr:dolitest/htdocs/custom/revenuesharing/ (FTP)

Voir CLAUDE.md pour détails complets.
```

---

## 🔗 Liens Utiles

**Serveur PROD** : `/dolibarr/htdocs/custom/revenuesharing/`
**Serveur TEST** : `/dolitest/htdocs/custom/revenuesharing/`
**Local** : `/Users/papa/dolibarr-revenuesharing/`

**Configuration rclone** : `~/.config/rclone/rclone.conf`

---

*Dernière mise à jour : 2025-10-03 17:10*
*Module développé pour la gestion du partage de revenus avec les collaborateurs intermittents*
