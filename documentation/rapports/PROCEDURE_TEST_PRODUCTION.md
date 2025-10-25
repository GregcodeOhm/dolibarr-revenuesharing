# 🚨 Procédure de Test en Production - IMPORTANT

**Module :** Revenue Sharing v22.01  
**Date :** 10 septembre 2025  
**Criticité :** HAUTE - Environnement de production  

---

## ⚠️ AVERTISSEMENT

Vous êtes sur le point de tester des **corrections de sécurité critiques** directement en **PRODUCTION**.
Les fichiers ont été modifiés pour corriger des vulnérabilités, mais peuvent potentiellement casser le fonctionnement.

---

## 📋 Procédure de Test Sécurisée

### Étape 1 : Vérification Préliminaire (2 min)

1. **Exécuter le script de test automatique** :
```bash
cd /path/to/revenuesharing/
php test_security_fixes.php
```

✅ Si tous les tests passent → Continuer  
❌ Si erreurs → NE PAS continuer, faire le rollback

### Étape 2 : Test Rapide d'une Page (5 min)

1. **Ouvrir votre navigateur en mode privé/incognito**
2. **Se connecter à Dolibarr** avec un compte administrateur
3. **Accéder au module Revenue Sharing**
4. **Tester LA PAGE INDEX en premier** :
   - URL : `https://votre-dolibarr.com/custom/revenuesharing/index.php`
   - ✅ La page doit s'afficher normalement
   - ✅ Les statistiques doivent apparaître
   - ❌ Si erreur 500 ou page blanche → ROLLBACK IMMÉDIAT

### Étape 3 : Test Fonctionnel Minimal (10 min)

**⚠️ ORDRE IMPORTANT - Tester dans cet ordre :**

1. **Liste des collaborateurs** (`collaborator_list.php`)
   - La liste doit s'afficher
   - Les boutons doivent fonctionner

2. **Liste des comptes** (`account_list.php`)
   - Les soldes doivent s'afficher
   - Pas d'erreur SQL

3. **Détail d'un compte** (`account_detail.php?id=X`)
   - Cliquer sur un compte existant
   - L'historique doit s'afficher

4. **Nouvelle opération** (`account_transaction.php`)
   - NE PAS valider, juste vérifier que le formulaire s'affiche
   - Les listes déroulantes doivent se charger

### Étape 4 : Test Complet (si étapes 1-3 OK) (15 min)

1. **Créer une opération TEST** :
   - Montant : 1€
   - Description : "TEST SECURITE - A SUPPRIMER"
   - Type : Autre crédit
   - Valider et vérifier l'enregistrement

2. **Tester les filtres** :
   - Sur la liste des contrats
   - Sur les factures fournisseurs
   - Vérifier que les recherches fonctionnent

3. **Supprimer l'opération TEST**

---

## 🔴 PROCÉDURE DE ROLLBACK D'URGENCE

**Si QUELQUE CHOSE ne fonctionne pas :**

### Rollback Automatique (30 secondes)
```bash
cd /path/to/revenuesharing/
./rollback_security_fixes.sh
# Répondre "oui" pour confirmer
```

### Rollback Manuel (si le script échoue)
```bash
# Restaurer les 4 fichiers principaux
cd /path/to/revenuesharing/
cp archives/backup-security-fixes/account_transaction_*.php account_transaction.php
cp archives/backup-security-fixes/account_detail_*.php account_detail.php
cp archives/backup-security-fixes/account_list_*.php account_list.php
cp archives/backup-security-fixes/contract_card_complete_*.php contract_card_complete.php
```

### Après le rollback :
1. Vérifier que le module fonctionne à nouveau
2. Noter le problème rencontré
3. Contacter le support si nécessaire

---

## ✅ Si Tout Fonctionne

### Actions Post-Test :
1. **Supprimer le script de test** (sécurité) :
```bash
rm test_security_fixes.php
```

2. **Documenter** :
   - Noter dans votre documentation que les corrections ont été appliquées
   - Date et heure de l'application
   - Version du module

3. **Surveiller** pendant 24-48h :
   - Les logs d'erreur PHP
   - Le comportement du module
   - Les retours utilisateurs

---

## 📞 Support et Aide

### En cas de problème majeur :

1. **Rollback immédiat** avec le script fourni
2. **Vérifier les logs** :
   - Logs Apache/Nginx : `/var/log/apache2/error.log`
   - Logs PHP : `/var/log/php/error.log`
   - Logs Dolibarr : `documents/dolibarr.log`

3. **Informations à collecter** en cas d'erreur :
   - Message d'erreur exact
   - Page où l'erreur se produit
   - Actions effectuées avant l'erreur

---

## 🎯 Checklist Finale

- [ ] Script de test exécuté avec succès
- [ ] Page index.php fonctionne
- [ ] Liste des collaborateurs OK
- [ ] Liste des comptes OK
- [ ] Détail d'un compte OK
- [ ] Formulaire de nouvelle opération OK
- [ ] Création d'une opération TEST réussie
- [ ] Suppression de l'opération TEST
- [ ] Script de test supprimé
- [ ] Documentation mise à jour

---

## ⏱️ Temps Total Estimé

- **Test minimal** : 15 minutes
- **Test complet** : 30 minutes
- **Rollback si problème** : 1 minute

---

**RAPPEL IMPORTANT** : Ces corrections corrigent des **vulnérabilités de sécurité critiques** (injections SQL, chemins exposés). Même si un rollback est nécessaire temporairement, il faudra planifier une correction appropriée rapidement.

---

*Document créé le 10 septembre 2025*  
*À conserver jusqu'à validation complète des corrections*