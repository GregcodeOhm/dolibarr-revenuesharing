# Tests Unitaires - Revenue Sharing Module

Ce module utilise **PHPUnit** pour les tests unitaires des repositories.

## Installation de PHPUnit

```bash
# Via Composer (recommandé)
composer require --dev phpunit/phpunit ^9.5

# Ou installation globale
wget https://phar.phpunit.de/phpunit-9.phar
chmod +x phpunit-9.phar
mv phpunit-9.phar /usr/local/bin/phpunit
```

## Exécution des Tests

### Tous les tests
```bash
# Depuis la racine du module
phpunit

# Ou avec Composer
composer test
```

### Tests spécifiques
```bash
# Un fichier de test spécifique
phpunit tests/repositories/BalanceRepositoryTest.php

# Une méthode de test spécifique
phpunit --filter testGetBalanceReturnsValidData
```

### Avec rapport de couverture (nécessite Xdebug)
```bash
phpunit --coverage-html coverage/
```

## Structure des Tests

```
tests/
├── bootstrap.php                          # Configuration et mocks
├── repositories/
│   ├── BalanceRepositoryTest.php         # 10 tests
│   ├── TransactionRepositoryTest.php     # 14 tests
│   └── SalaryDeclarationRepositoryTest.php # 16 tests
```

## Tests Couverts

### BalanceRepositoryTest (10 tests)
- ✅ Récupération du solde avec/sans filtre année
- ✅ Calcul du chiffre d'affaires
- ✅ Exclusion des prévisionnels
- ✅ Statistiques par type de transaction
- ✅ Gestion des erreurs de base de données

### TransactionRepositoryTest (14 tests)
- ✅ Pagination des résultats
- ✅ Filtres (année, type, prévisionnels)
- ✅ Filtres combinés multiples
- ✅ CRUD complet (create, read, update, delete)
- ✅ Calcul correct du nombre de pages
- ✅ Validation des champs requis

### SalaryDeclarationRepositoryTest (16 tests)
- ✅ Statistiques par statut (brouillon, validé, payé)
- ✅ Filtres année et statut
- ✅ Tri personnalisé avec sécurité SQL
- ✅ Total prévisionnel et jours par statut
- ✅ Gestion des valeurs nulles/zéro
- ✅ Protection contre injection SQL

## Configuration

Le fichier `phpunit.xml` configure :
- Bootstrap : `tests/bootstrap.php`
- Répertoire de tests : `tests/`
- Couverture de code : `class/`

## Mocks

Le fichier `tests/bootstrap.php` fournit :
- **DoliDB mock** : Simule la base de données Dolibarr
- **Constantes Dolibarr** : `MAIN_DB_PREFIX`, etc.
- **Autoloader** : Charge automatiquement les repositories

## Exemple d'Utilisation

```php
// Arrange
$mockBalance = (object)[
    'year_credits' => 5000.00,
    'year_debits' => 3000.00,
];
$this->db->setMockResult('balance_query', $mockBalance);

// Act
$result = $this->repository->getBalance(123, ['year' => 2024]);

// Assert
$this->assertEquals(5000.00, $result->year_credits);
```

## Résultats Attendus

Tous les tests devraient passer :
```
PHPUnit 9.5.x

............................................  40 / 40 (100%)

Time: 00:00.123, Memory: 6.00 MB

OK (40 tests, 120 assertions)
```

## Bonnes Pratiques

1. **AAA Pattern** : Arrange → Act → Assert
2. **Noms descriptifs** : `testGetBalanceReturnsValidData()`
3. **Un test = un concept** : Ne teste qu'une seule chose
4. **Isolation** : Chaque test est indépendant
5. **Mocks clairs** : Données de test réalistes

## Prochaines Étapes

- [ ] Tests d'intégration avec vraie base de données
- [ ] Tests de performance
- [ ] Tests end-to-end (E2E)
- [ ] CI/CD avec GitHub Actions
