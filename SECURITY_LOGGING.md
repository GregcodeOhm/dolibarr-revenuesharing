# Bonnes pratiques de logging sécurisé

## Problème résolu
Les logs de debug exposaient des informations sensibles dans `salary_declaration_form.php:297` et autres emplacements.

## Corrections appliquées

### 1. Protection par condition de debug
```php
// ❌ AVANT - Toujours actif
error_log('SQL insertion principale: '.$sql);

// ✅ APRÈS - Seulement en mode debug
if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
    error_log('Insertion déclaration salaire - Collaborateur ID: '.$collaborator_id);
}
```

### 2. Suppression des données sensibles
```php
// ❌ AVANT - Expose les requêtes SQL complètes
error_log('Erreur insertion détail: '.$db->lasterror().' - SQL: '.$sql_detail);

// ✅ APRÈS - Informations contextuelles sans données sensibles
if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
    error_log('Erreur insertion détail déclaration - Jour: '.$day.', Erreur: '.$db->lasterror());
}
```

### 3. Messages d'erreur utilisateur sécurisés
```php
// ❌ AVANT - Expose les détails SQL à l'utilisateur
setEventMessages('Erreur SQL détaillée : '.$db->lasterror(), null, 'errors');

// ✅ APRÈS - Message générique pour l'utilisateur
setEventMessages('Erreur lors de la sauvegarde de la déclaration', null, 'errors');
```

### 4. Debug JavaScript conditionnel
```php
// ❌ AVANT - Bouton debug toujours visible
print '<button onclick="debugSelectedDays()">🐛 Debug heures</button>';

// ✅ APRÈS - Seulement en mode développement
if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
    print '<button onclick="debugSelectedDays()">🐛 Debug heures</button>';
}
```

## Bonnes pratiques générales

### 1. Niveaux de logging
- **Production** : Erreurs critiques uniquement, sans détails sensibles
- **Debug** : Informations détaillées, protégées par `DOLIBARR_DEBUG`
- **Utilisateur** : Messages génériques compréhensibles

### 2. Informations à éviter dans les logs
- ❌ Requêtes SQL complètes avec données
- ❌ Mots de passe ou tokens
- ❌ Données personnelles (salaires, noms complets)
- ❌ Structures de base de données
- ❌ Chemins de fichiers système

### 3. Informations acceptables
- ✅ IDs d'entités (sans données liées)
- ✅ Types d'opérations
- ✅ Codes d'erreur
- ✅ Timestamps
- ✅ Statuts d'opération

### 4. Modèles de logging sécurisé

#### Log d'opération réussie
```php
if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
    error_log("Opération [TYPE] réussie - ID: $id, Utilisateur: $user->id");
}
```

#### Log d'erreur
```php
if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
    error_log("Erreur [CONTEXTE] - Code: ".$db->errno().", Utilisateur: $user->id");
}
```

#### Log de validation
```php
if (defined('DOLIBARR_DEBUG') && DOLIBARR_DEBUG) {
    error_log("Validation [ENTITÉ] - Résultat: ".($valid ? 'OK' : 'KO').", Champs: $fieldCount");
}
```

### 5. Configuration de debug
```php
// Dans conf.php ou configuration Dolibarr
// Mode production
define('DOLIBARR_DEBUG', false);

// Mode développement
define('DOLIBARR_DEBUG', true);
```

### 6. Rotation des logs
- Configurer la rotation automatique des logs
- Limiter la taille des fichiers de log
- Purger les anciens logs régulièrement

## Vérification de sécurité

### Commandes de vérification
```bash
# Rechercher les logs potentiellement problématiques
grep -r "error_log.*\$" . --include="*.php"
grep -r "var_dump\|print_r" . --include="*.php"
grep -r "console\.log.*\$" . --include="*.php"
```

### Points de contrôle
- [ ] Aucun log ne contient de requêtes SQL complètes
- [ ] Les logs de debug sont protégés par conditions
- [ ] Les messages utilisateur sont génériques
- [ ] Pas d'exposition de données personnelles
- [ ] Boutons de debug cachés en production

## Impact de la correction

### Avant
- Exposition des requêtes SQL dans les logs
- Données sensibles visibles en production
- Risque de fuite d'informations

### Après
- Logs conditionnels sécurisés
- Messages d'erreur génériques pour les utilisateurs
- Debug disponible uniquement en développement
- Conformité aux standards de sécurité