# 🤖 Améliorations Auto-Création de Contrats

**Date :** 10 septembre 2025  
**Module :** Revenue Sharing v22.01  
**Fichier modifié :** `auto_create_contracts.php`  

---

## ✨ Nouvelles Fonctionnalités Ajoutées

### 1. 📅 **Date de Facture Reportée**

**Avant :** La date de création du contrat était toujours `NOW()` (date du jour)

**Après :** La date de la facture liée est reportée sur la date de création du contrat

```php
// Utiliser la date de la facture pour la date de création du contrat
$contract_date = $obj_invoice->datef ? "'".$obj_invoice->datef."'" : "NOW()";
```

**Avantage :** Cohérence temporelle entre la facture et le contrat généré

---

### 2. 📝 **Libellé de Facture Reporté**

**Avant :** Libellé générique `"Contrat auto-créé pour facture FA-XXX - Nom Collaborateur"`

**Après :** Le libellé de la facture devient le libellé du contrat (si disponible)

```php
// Utiliser le libellé de la facture si disponible, sinon format par défaut
$label = $obj_invoice->libelle ? $obj_invoice->libelle : 'Contrat auto-créé pour facture '.$obj_invoice->ref.' - '.$obj_invoice->intervenant;
```

**Avantage :** Libellés plus pertinents et informatifs

---

### 3. 🔍 **Référence Fournisseur en Note Privée**

**Avant :** Aucune trace de la référence fournisseur

**Après :** La référence fournisseur (`ref_supplier`) est automatiquement enregistrée dans la note privée du contrat

```php
// Préparer la note privée avec la ref fournisseur
$note_private = '';
if ($obj_invoice->ref_supplier) {
    $note_private = 'Réf. fournisseur: '.$obj_invoice->ref_supplier;
}
```

**Avantage :** Traçabilité complète entre facture et contrat

---

## 🖥️ Améliorations Interface Utilisateur

### 1. **Colonne Libellé Ajoutée**
- Nouvelle colonne "Libellé" dans le tableau de sélection
- Affichage des libellés de factures (tronqués à 40 caractères)

### 2. **Référence Fournisseur Visible**
- Affichage de la référence fournisseur sous la référence facture
- Format : `FA-2025-001` (ref facture) + `FOURNISSEUR-REF-123` (ref fournisseur) en petit

### 3. **Tableau Ajusté**
- `colspan="6"` au lieu de `colspan="5"` pour les totaux
- Meilleure disposition des informations

---

## 🔒 Corrections de Sécurité Incluses

### 1. **Chemin d'Inclusion Sécurisé**
```php
// AVANT
$dolibarr_main_document_root = '/homez.378/ohmnibus/dolibarr/htdocs';
require_once $dolibarr_main_document_root.'/main.inc.php';

// APRÈS
require_once '../../main.inc.php';
```

### 2. **Requêtes SQL Sécurisées**
```php
// Cast en entier pour sécuriser
$sql_invoice .= " WHERE f.rowid = ".((int) $invoice_id);
$sql_create .= ((int) $matching_collab->rowid).", ";
$sql_create .= ((int) $invoice_id).", ";
```

### 3. **Échappement des Données**
```php
// Échappement systématique
$sql_create .= "'".$db->escape($label)."', ";
$sql_create .= "'".$db->escape($note_private)."', ";
```

---

## 📋 Structure BDD Mise à Jour

### Champs Utilisés dans `llx_revenuesharing_contract`

| Champ | Utilisation | Nouveau |
|-------|-------------|---------|
| `note_private` | Stockage ref fournisseur | Existant |
| `date_creation` | Date de la facture | Existant |
| `label` | Libellé de la facture | Modifié |

---

## 🔄 Workflow Amélioré

### **Ancien Process :**
1. Sélectionner factures avec intervenants
2. Créer contrats avec données génériques
3. Date = aujourd'hui, libellé générique

### **Nouveau Process :**
1. Sélectionner factures avec intervenants
2. **Récupérer libellé et ref fournisseur** de chaque facture
3. **Créer contrats avec données riches :**
   - Date = date de la facture
   - Libellé = libellé de la facture
   - Note privée = référence fournisseur
   - Affichage enrichi dans l'interface

---

## 🧪 Test et Validation

### Script de Test Créé
`test_auto_creation_improvements.php` - Vérifie :
- ✅ Structure BDD compatible
- ✅ Présence des nouvelles données dans les requêtes
- ✅ Logique de mapping correcte
- ✅ Interface utilisateur mise à jour
- ✅ Sécurité des requêtes

### Comment Tester
```bash
# Exécuter le test automatique
php test_auto_creation_improvements.php

# Puis tester manuellement
# 1. Aller sur auto_create_contracts.php
# 2. Sélectionner des factures avec libellé et ref_supplier
# 3. Créer les contrats
# 4. Vérifier dans contract_list.php que les données sont correctes
```

---

## 📁 Fichiers Sauvegardés

Le fichier original a été sauvegardé dans :
`archives/backup-files/auto_create_contracts_YYYYMMDD_HHMMSS.php`

---

## ✅ Résultats Attendus

Après ces améliorations, lors de la création automatique de contrats :

1. **Date cohérente** : Le contrat aura la même date que la facture source
2. **Libellé pertinent** : Le contrat reprendra le libellé descriptif de la facture
3. **Traçabilité** : La référence fournisseur sera visible dans la note privée
4. **Interface riche** : L'écran de sélection affichera plus d'informations utiles

---

## 🎯 Prochaines Évolutions Possibles

### Court terme
- [ ] Export CSV des créations automatiques
- [ ] Historique des auto-créations
- [ ] Validation avant création (confirmation détaillée)

### Moyen terme
- [ ] Règles de mapping personnalisables
- [ ] Auto-création depuis d'autres sources (projets, tâches)
- [ ] Notifications automatiques aux collaborateurs

---

## 📞 Support

En cas de problème avec les nouvelles fonctionnalités :

1. **Rollback possible** : Fichier sauvegardé disponible
2. **Test de diagnostic** : `test_auto_creation_improvements.php`
3. **Vérification BDD** : Contrôler que les champs existent

---

*Rapport généré le 10 septembre 2025*  
*Améliorations prêtes pour utilisation en production*