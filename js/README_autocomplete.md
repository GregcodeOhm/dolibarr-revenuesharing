# Documentation de l'autocomplétion unifiée

## Vue d'ensemble

Le fichier `autocomplete.js` contient une classe JavaScript unifiée `RevenueAutocomplete` qui remplace les différentes implémentations d'autocomplétion présentes dans le module Revenue Sharing.

## Migration effectuée

### Avant
- `transform_previsionnel.php` : jQuery UI Autocomplete
- `contract_card_complete.php` : Classe `AutoComplete` personnalisée
- Code dupliqué et approches différentes

### Après
- **Classe unifiée** : `RevenueAutocomplete`
- **Fonctions helper** : `createFactureAutocomplete()`, `createPropalAutocomplete()`
- **CSS unifié** et réutilisable
- **Interface cohérente** dans tout le module

## Utilisation

### Inclusion du script
```php
print '<script src="js/autocomplete.js"></script>';
```

### Autocomplétion pour factures
```javascript
const factureAutocomplete = createFactureAutocomplete("facture_search", {
    endpoint: "contract_card_complete.php",
    onSelect: function(item, input) {
        // Comportement personnalisé lors de la sélection
        console.log("Facture sélectionnée:", item);
    }
});
```

### Autocomplétion pour devis
```javascript
const propalAutocomplete = createPropalAutocomplete("propal_search");
```

### Utilisation avancée
```javascript
const autocomplete = new RevenueAutocomplete("mon_input", {
    searchType: 'factures',
    minLength: 3,
    placeholder: 'Rechercher une facture...',
    endpoint: 'mon_endpoint.php',
    onSelect: function(item, input) {
        // Logique personnalisée
    }
});
```

## Options disponibles

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `searchType` | string | 'factures' | Type de recherche ('factures', 'propals', 'collaborators') |
| `minLength` | number | 2 | Nombre minimum de caractères pour déclencher la recherche |
| `endpoint` | string | window.location.pathname | URL de l'endpoint AJAX |
| `placeholder` | string | 'Tapez pour rechercher...' | Texte d'aide dans le champ |
| `onSelect` | function | null | Callback personnalisé lors de la sélection |

## Fonctionnalités

### Navigation au clavier
- ↑/↓ : Navigation dans les résultats
- Enter : Sélection de l'élément actuel
- Escape : Fermeture de la liste

### Comportement par défaut
Pour les factures et devis, la classe gère automatiquement :
- Mise à jour des champs cachés (`fk_facture`, `fk_propal`)
- Remplissage des montants HT/TTC
- Suggestion du libellé de contrat
- Nettoyage des champs concurrents
- Déclenchement du recalcul des montants

### Styles CSS
Les styles sont automatiquement injectés et utilisent le préfixe `revenue-autocomplete-` pour éviter les conflits.

## Méthodes publiques

### Instance de RevenueAutocomplete

```javascript
// Définir une valeur
autocomplete.setValue("FA2023-001");

// Vider le champ
autocomplete.clear();

// Détruire l'instance
autocomplete.destroy();
```

## Endpoints AJAX attendus

Les endpoints doivent retourner un JSON avec la structure suivante :

```json
[
    {
        "value": 123,
        "label": "FA2023-001 - 1500.00 HT (Client ABC)",
        "ref": "FA2023-001",
        "total_ht": 1500.00,
        "total_ttc": 1800.00,
        "client": "Client ABC"
    }
]
```

## Migration des fichiers existants

### contract_card_complete.php
- ✅ Remplacé la classe `AutoComplete` par `RevenueAutocomplete`
- ✅ Utilisé les fonctions helper
- ✅ Conservé les fonctions de calcul existantes

### transform_previsionnel.php
- ✅ Supprimé jQuery UI Autocomplete
- ✅ Utilisé `createFactureAutocomplete()` avec callback personnalisé
- ✅ Conservé la logique spécifique de transformation

## Avantages de l'unification

1. **Code réutilisable** : Une seule classe pour tous les besoins d'autocomplétion
2. **Maintenance simplifiée** : Corrections et améliorations centralisées
3. **Interface cohérente** : Même look & feel partout
4. **Performance** : CSS injecté une seule fois
5. **Extensibilité** : Facile d'ajouter de nouveaux types de recherche
6. **Accessibilité** : Navigation clavier standardisée