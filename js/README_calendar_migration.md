# Migration du Calendrier de Déclaration de Salaires

## Problème résolu
Le calendrier pour sélectionner les jours de travail dans `salary_declaration_form.php` ne s'affichait plus après le déplacement des fonctions JavaScript vers le dossier `js`.

## Solution appliquée

### 1. Extraction du JavaScript inline
- **Avant** : Code JavaScript directement intégré dans le fichier PHP (500+ lignes)
- **Après** : Code JavaScript externalisé dans `js/salary-calendar.js`

### 2. Fichiers modifiés

#### `js/salary-calendar.js` (NOUVEAU)
Contient toutes les fonctions du calendrier :
- `generateCalendar()` : Génération de la grille calendaire
- `toggleDay()` : Sélection/désélection des jours
- `selectAllDays()` / `clearAllDays()` : Actions groupées
- `updateMetiersDetails()` : Gestion des détails par jour
- `updateCounters()` : Calcul des totaux
- `debugSelectedDays()` : Outils de debug
- `forceRegenerateCalendar()` : Force la régénération

#### `salary_declaration_form.php` (MODIFIÉ)
- Supprimé : 500+ lignes de JavaScript inline
- Ajouté : `<script src="js/salary-calendar.js"></script>`
- Conservé : Script inline minimal pour le chargement du solde

### 3. Fonctionnalités du calendrier

#### Interface utilisateur
- **Grille calendaire** : 7 colonnes (Lun-Dim)
- **Jours sélectionnés** : Fond vert avec détails métier/heures
- **Week-ends** : Fond orange (jaune non sélectionné)
- **Navigation clavier** : ↑↓ Enter Escape (pour les futures améliorations)

#### Sélection des jours
- Clic sur un jour pour le sélectionner/désélectionner
- Application automatique du métier et heures par défaut
- Mise à jour en temps réel des compteurs

#### Paramètres par défaut
- **Métier** : Sélectionnable via dropdown (IDCC 2642)
- **Heures** : 2h, 4h, 6h, 8h (défaut), 10h, 12h
- **Application** : Bouton "Appliquer défauts à tous"

#### Gestion avancée
- **Personnalisation par jour** : Section détails avec sélecteurs individuels
- **Validation** : Vérification qu'au moins un jour est sélectionné
- **Debug** : Outils de diagnostic intégrés

### 4. Données transmises au serveur

Le formulaire génère automatiquement les champs cachés :
```html
<input type="hidden" name="selected_dates[]" value="15">
<input type="hidden" name="metiers[15]" value="technicien_son">
<input type="hidden" name="heures[15]" value="8.0">
```

### 5. Outils de debug ajoutés

#### Test visuel au chargement
- Message vert "🗓️ CALENDRIER TEST OK" pendant 1 seconde
- Puis génération du vrai calendrier

#### Bouton de force
- Bouton rouge "🔄 Forcer calendrier"
- Régénération manuelle avec diagnostic

#### Logs console
```javascript
🗓️ Initialisation du calendrier...
Container calendrier trouvé: true
Calendrier généré pour: { month: 12, year: 2024, days: 31 }
```

### 6. Avantages de la migration

#### Organisation du code
- **Séparation des responsabilités** : JS séparé du PHP
- **Maintenabilité** : Code JavaScript centralisé
- **Réutilisabilité** : Calendrier utilisable ailleurs

#### Performance
- **Cache navigateur** : Fichier JS mis en cache
- **Chargement parallèle** : JS chargé en parallèle du HTML
- **Débogage** : Plus facile avec fichiers séparés

#### Développement
- **Coloration syntaxique** : Meilleure dans fichiers .js
- **Outils de développement** : Source maps, breakpoints
- **Versionning** : Modifications JS trackées séparément

### 7. Compatibilité

#### Navigateurs supportés
- **Modernes** : Chrome, Firefox, Safari, Edge
- **Fonctionnalités** : ES6+ (Map, fetch, template literals)
- **Graceful degradation** : Messages d'erreur explicites

#### Dolibarr
- **Version** : Compatible toutes versions récentes
- **Thèmes** : Styles CSS autonomes
- **Permissions** : Respect des droits utilisateur

### 8. Maintenance future

#### Ajout de fonctionnalités
- Modifier uniquement `js/salary-calendar.js`
- Tester avec le fichier HTML standalone si besoin

#### Debug
- Utiliser les outils de debug intégrés
- Consulter la console navigateur
- Utiliser le bouton "🔄 Forcer calendrier"

#### Mise à jour
- Le fichier JS est autonome
- Pas de dépendances externes (jQuery supprimé)
- Compatible avec les futures versions Dolibarr

## Résultat
✅ Le calendrier s'affiche maintenant correctement
✅ Code mieux organisé et maintenable
✅ Outils de debug pour diagnostics futurs
✅ Performance améliorée