# 📊 Guide d'utilisation - Exports des comptes collaborateurs

**Module :** Revenue Sharing v22.01  
**Fonctionnalité :** Export PDF et Excel des relevés de compte  
**Date :** 10 septembre 2025  

---

## 🎯 Vue d'ensemble

Le module dispose désormais de fonctionnalités complètes d'export des comptes collaborateurs :

### **Types d'exports disponibles :**
1. **📄 Export PDF** - Relevé de compte individuel détaillé
2. **📊 Export Excel (CSV)** - Relevé de compte individuel avec données
3. **📊 Export Global CSV** - Liste complète de tous les comptes

---

## 🔍 Export individuel d'un compte

### **Accès :**
- Depuis `account_detail.php?id=X` (détail d'un collaborateur)
- Section "📊 Export du relevé de compte"

### **Fonctionnalités :**

#### **Respect des filtres actifs :**
- ✅ Les exports prennent en compte les filtres de **type** et **année** appliqués
- ✅ Indication visuelle des filtres actifs dans l'interface
- ✅ Exportation limitée aux transactions filtrées

#### **Format PDF :**
```
📄 Export PDF
├── En-tête avec nom du collaborateur
├── Résumé financier (solde, crédits, débits)
├── Tableau détaillé des transactions
└── Pagination automatique
```

#### **Format Excel (CSV) :**
```
📊 Export Excel
├── Métadonnées (collaborateur, date export)
├── Résumé financier
├── Données transactions (7 colonnes)
└── Encodage UTF-8 avec BOM
```

### **Sécurité :**
- ✅ Protection CSRF avec tokens
- ✅ Accès restreint aux administrateurs
- ✅ Validation des paramètres
- ✅ Échappement des données

---

## 📋 Export global des comptes

### **Accès :**
- Depuis `account_list.php` (liste des comptes)
- Section "📊 Export global des comptes"

### **Contenu du fichier CSV :**
```csv
ID; Nom collaborateur; Prénom; Nom de famille; Email; % défaut; Statut; Solde actuel (€); Total crédits (€); Total débits (€); Nb transactions; Dernière MAJ
```

### **Données exportées :**
- **Métadonnées :** Date, module, version
- **Comptes actifs uniquement**
- **Soldes calculés en temps réel**
- **Statistiques par collaborateur**

---

## 🛠️ Architecture technique

### **Fichiers créés :**
```
/custom/revenuesharing/
├── class/export_account.class.php    # Classe d'export principale
├── export_account.php                # Traitement exports individuels  
└── export_all_accounts.php          # Export global des comptes
```

### **Classe ExportAccount :**
```php
class ExportAccount
{
    public function loadCollaboratorData($id)     # Charge données collaborateur
    public function loadTransactions($filters)    # Charge transactions filtrées
    public function exportToPDF($filters)         # Génère PDF avec TCPDF
    public function exportToExcel($filters)       # Génère CSV UTF-8
}
```

### **Intégration Dolibarr :**
- ✅ Utilise `pdf_getInstance()` (TCPDF natif)
- ✅ Fonctions Dolibarr : `price()`, `dol_print_date()`, `newToken()`
- ✅ Gestion des erreurs avec `setEventMessages()`
- ✅ Sécurité avec `accessforbidden()`

---

## 📊 Détail des exports

### **PDF - Structure complète :**

#### **En-tête :**
```
RELEVÉ DE COMPTE COLLABORATEUR
Collaborateur: [Nom]
Nom complet: [Prénom Nom]  
Email: [email@domain.com]
Date: [Date du jour]
```

#### **Résumé financier :**
```
RÉSUMÉ FINANCIER
Solde actuel: [Montant] €
Total crédits: [Montant] €  
Total débits: [Montant] €
Nombre de transactions: [Nombre]
```

#### **Tableau des transactions :**
| Date | Type | Description | Montant | Référence |
|------|------|-------------|---------|-----------|
| JJ/MM/AAAA | Contrats | Description transaction | ±XXX.XX € | CONT-XXX |

#### **Pagination :**
- Automatique si > 250mm de hauteur
- Répétition des en-têtes de tableau
- Pied de page avec informations de génération

### **Excel (CSV) - Structure :**

#### **Métadonnées :**
```csv
RELEVÉ DE COMPTE COLLABORATEUR;
;
Collaborateur;[Nom];
Nom complet;[Prénom Nom];
Email;[Email];
Date d'export;[Date];
```

#### **Résumé financier :**
```csv
RÉSUMÉ FINANCIER;
Solde actuel;[Montant] €;
Total crédits;[Montant] €;
Total débits;[Montant] €;
```

#### **Données transactions :**
```csv
HISTORIQUE DES TRANSACTIONS;
Date;Type;Description;Montant (€);Note privée;Référence liée;Créé par;
```

---

## 🎨 Interface utilisateur

### **Boutons d'export individuels :**
```html
📄 Export PDF     # Rouge (#dc3545)
📊 Export Excel   # Vert (#28a745)
```

### **Bouton export global :**
```html
📊 Export Excel (CSV)   # Vert (#28a745)
```

### **Indicateurs visuels :**
- **Zone bleue** avec icône pour les exports
- **Indication des filtres actifs** sous les boutons
- **Messages d'état** en cas d'erreur

---

## 🔒 Sécurité implémentée

### **Contrôles d'accès :**
```php
if (!$user->admin) {
    accessforbidden('Seuls les administrateurs peuvent accéder à cette page');
}
```

### **Protection CSRF :**
```php
if (!checkCSRFToken(GETPOST('token', 'alpha'))) {
    setEventMessages('Token de sécurité invalide', null, 'errors');
}
```

### **Validation des données :**
```php
$id = (int) GETPOST('id', 'int');           # Cast sécurisé
$filter_type = $db->escape($filter_type);   # Échappement SQL
$filename = dol_sanitizeFileName($filename); # Nom fichier sécurisé
```

### **Gestion d'erreurs :**
```php
try {
    $result = $export->exportToPDF();
} catch (Exception $e) {
    setEventMessages('Erreur: '.$e->getMessage(), null, 'errors');
    header('Location: account_detail.php?id='.$id);
}
```

---

## 🚀 Utilisation pratique

### **Workflow export individuel :**
1. **Accéder** au détail d'un collaborateur
2. **Appliquer** les filtres souhaités (type, année)
3. **Cliquer** sur "📄 Export PDF" ou "📊 Export Excel"
4. **Télécharger** le fichier généré

### **Workflow export global :**
1. **Accéder** à la liste des comptes
2. **Cliquer** sur "📊 Export Excel (CSV)"
3. **Télécharger** le fichier avec tous les comptes

### **Noms de fichiers générés :**
```
releve_compte_[NomCollaborateur]_[AAAAMMJJ].pdf
releve_compte_[NomCollaborateur]_[AAAAMMJJ].csv
export_comptes_collaborateurs_[AAAAMMJJ].csv
```

---

## ⚡ Performance et limitations

### **Limites actuelles :**
- **PDF** : 500 transactions max par export
- **Excel** : 500 transactions max par export individuel
- **Global** : Tous les comptes actifs (pas de limite)

### **Optimisations :**
- **Requêtes SQL optimisées** avec LEFT JOIN
- **Pagination automatique** PDF
- **Gestion mémoire** avec `php://output`
- **Encodage UTF-8** avec BOM pour Excel

---

## 🐛 Dépannage

### **Erreurs courantes :**

#### **"Token de sécurité invalide"**
- **Cause :** Session expirée ou CSRF
- **Solution :** Actualiser la page et recommencer

#### **"Erreur lors de l'export"**
- **Cause :** Données collaborateur manquantes
- **Solution :** Vérifier que le collaborateur existe

#### **"Fichier PDF vide"**
- **Cause :** Problème TCPDF ou mémoire
- **Solution :** Augmenter `memory_limit` PHP

#### **"Caractères mal encodés dans Excel"**
- **Cause :** BOM UTF-8 manquant
- **Solution :** Vérifier `fputs($output, "\xEF\xBB\xBF")`

### **Vérifications :**
1. **Permissions PHP** : `write` sur répertoire temp
2. **Extensions PHP** : GD, ZIP (pour TCPDF)
3. **Mémoire PHP** : Minimum 128MB
4. **Tables BDD** : Vérifier structure avec diagnostic

---

## 📈 Évolutions futures

### **Améliorations possibles :**
1. **Export par période** personnalisée
2. **Graphiques** dans les PDF (avec Chart.js)
3. **Templates PDF** personnalisables
4. **Export planifié** automatique
5. **Format Excel natif** (XLSX) avec PhpSpreadsheet
6. **Signature numérique** PDF
7. **Watermark** sur les documents

### **Optimisations techniques :**
1. **Cache** des exports fréquents
2. **Export asynchrone** pour gros volumes
3. **Compression** des fichiers
4. **API REST** pour exports programmatiques

---

## 🎉 Conclusion

Les fonctionnalités d'export sont désormais **opérationnelles** et **sécurisées** :

- ✅ **Export PDF** professionnel avec pagination
- ✅ **Export Excel** compatible avec filtres  
- ✅ **Export global** pour synthèse complète
- ✅ **Sécurité renforcée** avec CSRF et validation
- ✅ **Interface intuitive** intégrée dans Dolibarr

**Les collaborateurs disposent maintenant d'outils complets pour suivre et exporter leurs comptes !**

---

*Documentation générée le 10 septembre 2025*  
*Module Revenue Sharing - Développé avec Claude Code*