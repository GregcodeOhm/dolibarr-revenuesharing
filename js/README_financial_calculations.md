# Calculs Financiers Automatiques - Déclarations de Salaires

## Problème résolu
Les champs financiers ne se mettaient pas à jour automatiquement lors de la sélection des jours dans le calendrier.

## Solution implémentée

### Champs calculés automatiquement

#### 1. **Total cachets**
```javascript
totalCachets = cachetUnitaire × nombreDeJours
```
- Affiché dans la section récapitulatif du calendrier
- Mis à jour en temps réel lors de la sélection/désélection

#### 2. **Masse salariale employeur**
```javascript
masseSalariale = totalCachets × 1.55
```
- Inclut le salaire brut + charges employeur (~55%)
- Calculé selon les taux moyens intermittents du spectacle
- Mis à jour dans le champ "Masse salariale employeur (€)"

#### 3. **Solde utilisé**
```javascript
soldeUtilise = masseSalariale
```
- Montant prélevé du compte collaborateur
- Égal à la masse salariale (coût total employeur)
- Mis à jour dans le champ "Solde utilisé (€)"

### Déclencheurs de calcul

#### Sélection/désélection dans le calendrier
- Appel automatique de `updateCounters()`
- Recalcul de tous les montants
- Mise à jour visuelle immédiate

#### Modification du cachet unitaire
- Listener sur les événements `input` et `change`
- Recalcul automatique dès la saisie
- Validation en temps réel

#### Chargement initial
- Calcul au chargement si cachet déjà renseigné
- Prise en compte des valeurs pré-remplies (mode édition)

### Fonctions ajoutées

#### `updateFinancialFields(totalDays, totalHeures)`
```javascript
function updateFinancialFields(totalDays, totalHeures) {
    const cachetUnitaire = parseFloat(cachetUnitaireElement.value) || 0;

    if (cachetUnitaire > 0 && totalDays > 0) {
        const totalCachets = cachetUnitaire * totalDays;
        const masseSalariale = totalCachets * 1.55;

        // Mise à jour des champs du formulaire
        masseSalarialeElement.value = masseSalariale.toFixed(2);
        soldeUtiliseElement.value = masseSalariale.toFixed(2);
        cachetsElement.textContent = totalCachets.toFixed(2) + ' €';
    }
}
```

#### `updateCounters()` - Améliorée
- Intègre maintenant le calcul financier
- Appelle `updateFinancialFields()` automatiquement
- Logs de debug pour traçabilité

### Taux de charges utilisé

#### 55% de charges employeur
- **Base légale** : Intermittents du spectacle
- **Composition** :
  - Cotisations sociales employeur
  - Assurance chômage spécifique
  - Formation professionnelle
  - Taxe d'apprentissage
  - Autres charges selon convention

#### Calcul détaillé
```
Cachet brut:        150,00 €
Charges employeur:   82,50 € (55%)
Masse salariale:    232,50 €
```

### Validation et sécurité

#### Vérification des valeurs
- Contrôle que `cachetUnitaire > 0`
- Contrôle que `totalDays > 0`
- Gestion des valeurs nulles/NaN

#### Arrondi financier
- Utilisation de `toFixed(2)` pour les montants
- Précision au centime d'euro
- Format cohérent dans l'interface

#### Logs de debug
```javascript
console.log('Calculs financiers:', {
    totalDays: 5,
    cachetUnitaire: 150.00,
    totalCachets: 750.00,
    masseSalariale: 1087.50
});
```

### Exemple concret

#### Scenario : 5 jours à 150€/jour
1. **Sélection** : 5 jours dans le calendrier
2. **Cachet unitaire** : 150,00 €
3. **Calculs automatiques** :
   - Total cachets : 750,00 €
   - Masse salariale : 1 162,50 €
   - Solde utilisé : 1 162,50 €

#### Interface utilisateur
- ✅ Champs mis à jour en temps réel
- ✅ Calculs visibles dans les compteurs
- ✅ Validation avant soumission
- ✅ Cohérence entre affichage et formulaire

### Compatibilité

#### Navigateurs supportés
- Tous navigateurs modernes
- Calculs JavaScript natifs
- Pas de dépendances externes

#### Mode édition
- Calculs initiaux si valeurs existantes
- Préservation des modifications manuelles
- Recalcul lors des changements

### Maintenance

#### Modification du taux de charges
Pour ajuster le taux de charges employeur :
```javascript
const tauxCharges = 1.60; // Pour 60% au lieu de 55%
```

#### Ajout de nouveaux calculs
Les nouveaux champs peuvent être ajoutés dans `updateFinancialFields()` en suivant le même pattern.

#### Debug
Utiliser la console navigateur pour voir les logs de calcul et vérifier les valeurs.