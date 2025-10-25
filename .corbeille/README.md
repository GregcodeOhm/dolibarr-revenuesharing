# Corbeille - Fichiers Obsolètes

Ce dossier contient les fichiers obsolètes du projet qui peuvent être supprimés.

## Fichiers présents

### Fichiers de documentation obsolètes
- `FINAL_ICONS_SUMMARY.md` - Résumé correction icônes (ancien)
- `ICONS_CORRECTION_SUMMARY.md` - Correction icônes (ancien)
- `SELECTOR_FIX_SUMMARY.md` - Fix sélecteurs (ancien)
- `SECURITY_LOGGING.md` - Logs sécurité (ancien)
- `OPTIMIZATIONS_TODO.md` - Plan optimisations (remplacé par CLAUDE.md)
- `next_steps.md` - Prochaines étapes (obsolète)

### Fichiers de debug serveurs (à supprimer manuellement)
Si présents sur les serveurs, supprimer via FTP/SFTP :
- `clear_cache.php` - Script debug OPcache
- `test_sync.php` - Script test synchronisation

## Action

Vous pouvez supprimer tout le dossier `.corbeille/` en toute sécurité :

```bash
rm -rf /Users/papa/dolibarr-revenuesharing/.corbeille/
```

---
*Créé le : 2025-10-03*
