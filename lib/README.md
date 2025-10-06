# Bibliothèque de fonctions Revenue Sharing

Ce dossier contient les fonctions utilitaires centralisées du module Revenue Sharing.

## Fichiers

### revenuesharing.lib.php
Bibliothèque principale contenant toutes les fonctions utilitaires.

## Fonctions disponibles

### Formatage et utilitaires

- `formatBytes($size, $precision = 2)` - Formate une taille en octets en format lisible
- `formatPrice($amount, $decimals = 2)` - Formate un prix selon les standards Dolibarr
- `cleanContractRef($ref)` - Nettoie et valide une référence de contrat

### Calculs métier

- `calculateContractAmounts($contract)` - Calcule les montants d'un contrat Revenue Sharing
- `getNextContractRef($db, $prefix = null)` - Génère la prochaine référence de contrat
- `validateCollaboratorData($data)` - Valide et nettoie les données d'un collaborateur

### Statistiques et données

- `getRevenueSharingStats($db, $year = null)` - Génère les statistiques pour le dashboard
- `checkRevenueSharingPermission($user, $permission = 'read')` - Vérifie les permissions utilisateur

### Logging

- `logRevenueSharingAction($db, $user, $action, $object_type, $object_id, $description = '')` - Log des actions importantes

## Utilisation

Pour utiliser ces fonctions dans un fichier PHP du module :

```php
require_once __DIR__.'/lib/revenuesharing.lib.php';

// Exemple d'utilisation
$formatted_size = formatBytes(1048576); // "1,00 Mo"
$next_ref = getNextContractRef($db); // "RC2024-0001"
$stats = getRevenueSharingStats($db, 2024);
```

## Migration des fonctions

Les fonctions suivantes ont été centralisées depuis les fichiers individuels :

- `formatBytes()` : supplier_invoice_link.php, transaction_documents.php → lib/revenuesharing.lib.php
- `calculateAmounts()` : contract_card_complete.php → `calculateContractAmounts()`
- `getNextRef()` : contract_card_complete.php → `getNextContractRef()`

## Avantages de la centralisation

1. **Évite la duplication de code** - Une seule implémentation par fonction
2. **Facilite la maintenance** - Corrections centralisées
3. **Améliore la consistance** - Comportement identique partout
4. **Simplifie les tests** - Tests centralisés des fonctions utilitaires
5. **Meilleure documentation** - Fonctions documentées en un seul endroit