# Roadmap - Édition des Salaires et Charges

## 🎯 Objectif Global
Développer une interface permettant d'éditer directement les salaires et autres charges (débits) depuis l'interface web du module Revenue Sharing.

## 📋 Analyse des Besoins

### Contexte Actuel
- Les transactions sont actuellement créées via import ou saisie manuelle
- Pas d'interface dédiée pour modifier les montants existants
- Les salaires et charges sont des éléments critiques nécessitant une édition précise

### Fonctionnalités Requises
- **Édition en ligne** : Modification directe depuis la liste des transactions
- **Validation des données** : Contrôles de cohérence et de sécurité
- **Historique des modifications** : Traçabilité des changements
- **Gestion des permissions** : Accès restreint aux administrateurs

## 🗺️ Plan de Développement

### Phase 1 : Analyse Technique
**Durée estimée** : 1-2 heures

#### 1.1 Étude de l'Existant
- [ ] **Analyser la structure des transactions**
  - Table `revenuesharing_account_transaction`
  - Champs modifiables : `amount`, `description`, `note_private`
  - Contraintes et relations
  
- [ ] **Étudier les interfaces existantes**
  - `account_detail.php` : Liste des transactions
  - `account_transaction.php` : Formulaire de saisie
  - Possibilités de réutilisation de code

#### 1.2 Identification des Défis Techniques
- [ ] **Gestion de la sécurité**
  - Tokens CSRF pour les modifications
  - Permissions utilisateur (admin uniquement ?)
  - Validation côté serveur

- [ ] **Impact sur les soldes**
  - Recalcul automatique après modification
  - Mise à jour de `revenuesharing_account_balance`
  - Gestion des statistiques

### Phase 2 : Conception de l'Interface
**Durée estimée** : 2-3 heures

#### 2.1 Design UX/UI
- [ ] **Mode édition en ligne**
  - Icône "✏️ Éditer" sur chaque transaction
  - Basculement en mode édition (champs input)
  - Boutons "Sauvegarder" / "Annuler"

- [ ] **Interface alternative : Modal**
  - Pop-up d'édition avec formulaire complet
  - Validation en temps réel
  - Historique des modifications visible

#### 2.2 Wireframes des Écrans
- [ ] **Liste des transactions avec édition**
  ```
  Date | Type | Description | Montant | Actions
  01/09 | Salaire | [Input: Salaire mensuel] | [Input: 2500.00] | [💾] [❌]
  ```

- [ ] **Modal d'édition détaillée**
  ```
  ┌─ Édition Transaction ─────────────┐
  │ Type: [Dropdown: Salaire      ▼] │
  │ Description: [Input: __________ ] │
  │ Montant: [Input: _____] €        │
  │ Note privée: [Textarea: _______ ] │
  │ [Sauvegarder] [Annuler]         │
  └───────────────────────────────────┘
  ```

### Phase 3 : Développement Backend
**Durée estimée** : 3-4 heures

#### 3.1 Contrôleur d'Édition
- [ ] **Nouveau fichier : `edit_transaction.php`**
  - Gestion des actions : `edit`, `update`, `delete`
  - Validation des données d'entrée
  - Contrôles de sécurité (CSRF, permissions)

#### 3.2 Fonctions de Mise à Jour
- [ ] **Fonction `updateTransaction($id, $data)`**
  ```php
  function updateTransaction($id, $amount, $description, $note) {
      // Validation
      // Mise à jour en base
      // Recalcul des soldes
      // Log des modifications
  }
  ```

- [ ] **Recalcul automatique des soldes**
  - Mise à jour de `revenuesharing_account_balance`
  - Rafraîchissement des statistiques
  - Cohérence des totaux

#### 3.3 Historique des Modifications
- [ ] **Nouvelle table : `revenuesharing_transaction_log`**
  ```sql
  CREATE TABLE revenuesharing_transaction_log (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_transaction INT,
    old_amount DECIMAL(10,2),
    new_amount DECIMAL(10,2),
    old_description TEXT,
    new_description TEXT,
    fk_user_modif INT,
    date_modification DATETIME,
    reason TEXT
  )
  ```

### Phase 4 : Développement Frontend
**Durée estimée** : 2-3 heures

#### 4.1 JavaScript d'Édition en Ligne
- [ ] **Script `transaction-editor.js`**
  - Basculement mode lecture/édition
  - Validation côté client
  - Appels AJAX pour sauvegardes

#### 4.2 Interfaces Responsives
- [ ] **Adaptation mobile**
  - Édition en modal sur petit écran
  - Boutons tactiles adaptés
  - Validation ergonomique

#### 4.3 Indicateurs Visuels
- [ ] **États de l'édition**
  - Champs en cours de modification (surbrillance)
  - Indicateurs de sauvegarde (spinner, checkmark)
  - Messages d'erreur contextuels

### Phase 5 : Tests et Sécurité
**Durée estimée** : 1-2 heures

#### 5.1 Tests Fonctionnels
- [ ] **Scénarios de test**
  - Édition normale d'un salaire
  - Modification de plusieurs champs
  - Annulation de modifications
  - Sauvegarde avec erreurs de validation

#### 5.2 Tests de Sécurité
- [ ] **Validations**
  - Injection SQL (requêtes préparées)
  - CSRF (tokens valides)
  - XSS (échappement des données)
  - Permissions (accès restreint)

#### 5.3 Tests de Cohérence
- [ ] **Vérification des soldes**
  - Recalcul correct après modification
  - Cohérence entre tous les affichages
  - Synchronisation des exports

## 🛠️ Détails Techniques

### Tables Impactées
```sql
-- Table principale (existante)
revenuesharing_account_transaction
  - amount (modifiable)
  - description (modifiable) 
  - note_private (modifiable)

-- Table des soldes (mise à jour automatique)
revenuesharing_account_balance
  - total_credits (recalculé)
  - total_debits (recalculé)
  - current_balance (recalculé)

-- Nouvelle table pour l'historique
revenuesharing_transaction_log (à créer)
```

### Fichiers à Créer/Modifier
```
Nouveaux fichiers :
- edit_transaction.php (contrôleur)
- js/transaction-editor.js (frontend)
- admin/create_transaction_log_table.php (installation)

Modifications :
- account_detail.php (ajout boutons édition)
- account_list.php (possibilité d'édition depuis la liste)
- css/revenuesharing.css (styles d'édition)
```

## 🎛️ Options de Configuration

### Permissions
- **Administrateur seul** : Édition restreinte aux admins
- **Propriétaire + Admin** : Le collaborateur peut modifier ses propres données
- **Audit trail** : Log obligatoire de toutes les modifications

### Validations Métier
- **Montants limités** : Plafond de sécurité (ex: max 10,000€)
- **Types de transactions** : Restriction des types modifiables
- **Périodes verrouillées** : Interdiction de modifier les années clôturées

## 📈 Évolutions Futures

### Court terme (après implémentation de base)
- **Modification en lot** : Éditer plusieurs transactions simultanément
- **Import/Export** : Modification via fichier Excel
- **Notifications** : Alertes automatiques des modifications importantes

### Moyen terme
- **Workflow de validation** : Approbation des modifications importantes
- **Intégration comptable** : Synchronisation avec module comptabilité Dolibarr
- **Reporting avancé** : Rapports de modifications et écarts

## ⚠️ Points d'Attention

### Criticité des Données
- Les salaires sont des données sensibles
- Modifications impactent les calculs fiscaux et sociaux
- Nécessité d'un backup avant modification

### Performance
- Recalcul des soldes peut être coûteux sur gros volumes
- Optimiser les requêtes de mise à jour
- Considérer une approche asynchrone si nécessaire

### Formation Utilisateur
- Documentation des nouvelles fonctionnalités
- Guide des bonnes pratiques d'édition
- Procédures de vérification post-modification

---

**Estimation totale** : 8-12 heures de développement  
**Complexité** : Moyenne-Haute (gestion des soldes + sécurité)  
**Priorité** : Haute (fonctionnalité demandée)  

*Roadmap créée le 10 septembre 2025*