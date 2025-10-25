#!/bin/bash
# Script de nettoyage du module Revenue Sharing
# Usage: ./clean_project.sh

echo "🧹 Nettoyage du module Revenue Sharing"
echo "======================================"

# Créer le dossier _archive si nécessaire
mkdir -p _archive/{backups,debug-scripts,temp-scripts}

# Compter les fichiers avant nettoyage
echo "📊 Analyse avant nettoyage:"
echo "- Fichiers backup: $(find . -name "*backup*" -type f | wc -l)"
echo "- Fichiers debug: $(find . -name "debug_*.php" -type f | wc -l)" 
echo "- Fichiers .DS_Store: $(find . -name ".DS_Store" -type f | wc -l)"

# Nettoyer les fichiers système
echo "🗂️  Suppression des fichiers système..."
find . -name ".DS_Store" -delete 2>/dev/null
find . -name "Thumbs.db" -delete 2>/dev/null
rm -rf .claude 2>/dev/null

# Déplacer les fichiers de backup
echo "📦 Archivage des fichiers de backup..."
find . -name "*backup*.php" -not -path "./_archive/*" -exec mv {} _archive/backups/ \; 2>/dev/null

# Déplacer les scripts de debug
echo "🐛 Archivage des scripts de debug..."
find . -name "debug_*.php" -not -path "./_archive/*" -exec mv {} _archive/debug-scripts/ \; 2>/dev/null
find . -name "diagnostic_*.php" -not -path "./_archive/*" -exec mv {} _archive/debug-scripts/ \; 2>/dev/null

# Compter les fichiers après nettoyage
echo "✅ Nettoyage terminé!"
echo "📁 Fichiers archivés dans _archive/"
echo "📊 Statistiques:"
echo "- Fichiers principaux: $(find . -name "*.php" -not -path "./_archive/*" -not -path "./admin/*" | wc -l)"
echo "- Fichiers admin: $(find ./admin -name "*.php" | wc -l)"  
echo "- Fichiers archivés: $(find ./_archive -name "*.php" | wc -l)"

echo ""
echo "🎉 Module nettoyé et organisé!"
echo "💡 Vous pouvez maintenant supprimer _archive/ si tout fonctionne correctement."