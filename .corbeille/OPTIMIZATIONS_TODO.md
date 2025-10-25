# Optimisations à Appliquer sur Toutes les Pages

## Modèle d'Optimisation (basé sur account_detail.php)

### 1. contract_list.php ⚠️ (Partiellement fait - BUGS à corriger)

**🐛 BUG PAGINATION:**
Lignes 386-404 - La pagination actuelle est cassée car:
- `$num` = count($contracts) qui est limité à $limit
- Devrait récupérer le TOTAL réel via COUNT(*) dans ContractRepository
- Solution: Modifier `findAllWithDetails()` pour retourner `['contracts' => [], 'total' => 125]`

**À ajouter:**
```php
// En haut du fichier
require_once __DIR__.'/class/CacheManager.php';

// Après initialisation DB
$cache = new CacheManager(null, 300, true);

// Wrapper les appels repository dans try/catch
try {
    $contracts = $contractRepo->findAllWithDetails([...]);
    $collaborators = $collaboratorRepo->findAll(['active' => 1]);
} catch (Exception $e) {
    print '<div style="background: #f8d7da; border: 1px solid #dc3545; color: #721c24; padding: 15px; margin: 20px 0; border-radius: 4px;">';
    print '<strong>⚠️ Erreur:</strong> '.htmlspecialchars($e->getMessage());
    print '</div>';
    llxFooter();
    $db->close();
    exit;
}

// Cache pour les stats (lignes 452-498)
$cacheKey = "contract_stats_{$search_collaborator}_{$filter_stats_collaborator}_{$year}";
$stats = $cache->remember($cacheKey, function() use ($db, $sql_total) {
    $resql_total = $db->query($sql_total);
    return $db->fetch_object($resql_total);
}, 300);

// FIX PAGINATION: Modifier ContractRepository::findAllWithDetails()
// Pour retourner ['contracts' => [...], 'total' => 125]
// Comme dans SalaryDeclarationRepository::findAllWithDetails()
```

### 2. account_list.php ❌ (Pas fait)
**À faire:**
```php
// Remplacer toutes les requêtes SQL directes par:
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';
require_once __DIR__.'/class/repositories/BalanceRepository.php';
require_once __DIR__.'/class/CacheManager.php';

$cache = new CacheManager(null, 300, true);
$collaboratorRepo = new CollaboratorRepository($db);
$balanceRepo = new BalanceRepository($db, $cache);

try {
    // Ligne 156: Liste collaborateurs
    $collaborators = $collaboratorRepo->findAll(['active' => 1]);

    // Lignes 95-110: Soldes
    foreach ($collaborators as $collab) {
        $balance = $balanceRepo->getBalance($collab->rowid, ['year' => $filter_year]);
    }
} catch (Exception $e) {
    // Gestion erreur
}
```

### 3. analytics.php ❌ (Pas fait)
**À faire:**
- Créer `class/repositories/AnalyticsRepository.php`
- Méthodes: `getKPIs($filters)`, `getByAnalyticsSector($filters)`, `getByIntervenant($filters)`, `getEvolution($filters)`
- Cache pour toutes les stats
- Try/catch complet

### 4. index.php ❌ (Pas fait)
**À faire:**
```php
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';
require_once __DIR__.'/class/repositories/ContractRepository.php';
require_once __DIR__.'/class/CacheManager.php';

$cache = new CacheManager(null, 600, true); // 10 min pour dashboard
$collaboratorRepo = new CollaboratorRepository($db);
$contractRepo = new ContractRepository($db);

try {
    // Ligne 42-49: Nb collaborateurs
    $nb_collaborators = $collaboratorRepo->count(['active' => 1]);

    // Lignes 52-84: Stats contrats
    $cacheKey = "dashboard_stats_{$year}_{$filter_collaborator}";
    $stats = $cache->remember($cacheKey, function() use ($contractRepo, $year, $filter_collaborator) {
        return $contractRepo->getYearStats($year, $filter_collaborator);
    }, 600);

    // Lignes 286-296: Top collaborateurs
    $top_collabs = $contractRepo->getTopCollaborators($year, 5);
} catch (Exception $e) {
    // Gestion erreur
}
```

### 5. collaborator_list.php ❌ (Pas fait)
**À faire:**
```php
require_once __DIR__.'/class/repositories/CollaboratorRepository.php';
require_once __DIR__.'/class/repositories/ContractRepository.php';
require_once __DIR__.'/class/CacheManager.php';

$cache = new CacheManager(null, 300, true);
$collaboratorRepo = new CollaboratorRepository($db);
$contractRepo = new ContractRepository($db);

try {
    // Lignes 97-115: Liste collaborateurs avec stats
    $collaborators = $collaboratorRepo->findAllWithContractStats([
        'search' => $search_user,
        'active' => $search_active,
        'limit' => $limit,
        'offset' => $offset,
        'sortfield' => $sortfield,
        'sortorder' => $sortorder
    ]);
} catch (Exception $e) {
    // Gestion erreur
}
```

### 6. calculateur.php ✅ (Pas besoin d'optimisation)
- Aucune requête DB intensive
- Uniquement calculs côté serveur

## Méthodes Repository à Ajouter

### CollaboratorRepository
```php
public function count($filters = []) // Pour index.php
public function findAllWithContractStats($filters = []) // Pour collaborator_list.php
```

### ContractRepository
```php
public function getYearStats($year, $collaboratorId = null) // Pour index.php
public function getTopCollaborators($year, $limit = 5) // Pour index.php
```

### AnalyticsRepository (NOUVEAU)
```php
public function getKPIs($filters = [])
public function getByAnalyticsSector($filters = [])
public function getByIntervenant($filters = [])
public function getEvolution($filters = [])
public function getMatrix($filters = [])
```

## Résumé Token Usage

**Tokens disponibles:** 144k
**Estimation par fichier:**
- account_list.php: ~15k tokens
- analytics.php + AnalyticsRepository: ~25k tokens
- index.php: ~10k tokens
- collaborator_list.php: ~10k tokens
- Méthodes repository: ~10k tokens
- Documentation claude.md: ~10k tokens
- Sync rclone: ~2k tokens

**Total estimé: ~82k tokens** (dans la limite ✅)

## Ordre d'Exécution

1. ✅ contract_list.php - Compléter cache + try/catch
2. ❌ Ajouter méthodes à CollaboratorRepository
3. ❌ Ajouter méthodes à ContractRepository
4. ❌ account_list.php - Full refactor
5. ❌ index.php - Full refactor
6. ❌ collaborator_list.php - Full refactor
7. ❌ Créer AnalyticsRepository
8. ❌ analytics.php - Full refactor
9. ❌ Mettre à jour claude.md
10. ❌ Sync tous les fichiers PROD + TEST
