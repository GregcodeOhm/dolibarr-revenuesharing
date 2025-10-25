<?php
/**
 * CacheManager.php
 * Gestionnaire de cache simple pour le module Revenue Sharing
 *
 * @package    RevenueSharing
 * @subpackage Cache
 * @author     Dolibarr Module
 * @license    GPL-3.0+
 */

/**
 * Gestionnaire de cache simple basé sur fichiers
 *
 * Gère le cache des données fréquemment accédées pour améliorer les performances:
 * - Soldes des collaborateurs
 * - Statistiques calculées
 * - Listes de données statiques
 */
class CacheManager
{
    /** @var string Répertoire de cache */
    private $cacheDir;

    /** @var int Durée de vie du cache en secondes (défaut: 5 minutes) */
    private $defaultTtl;

    /** @var bool Active/désactive le cache */
    private $enabled;

    /**
     * Constructeur du gestionnaire de cache
     *
     * @param string $cacheDir Répertoire de cache (défaut: documents/cache/revenuesharing)
     * @param int    $ttl      Durée de vie en secondes (défaut: 300)
     * @param bool   $enabled  Activer le cache (défaut: true)
     */
    public function __construct($cacheDir = null, $ttl = 300, $enabled = true)
    {
        global $conf;

        if ($cacheDir === null) {
            $cacheDir = DOL_DATA_ROOT.'/cache/revenuesharing';
        }

        $this->cacheDir = $cacheDir;
        $this->defaultTtl = $ttl;
        $this->enabled = $enabled;

        // Créer le répertoire de cache si nécessaire
        if ($this->enabled && !is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Récupère une valeur du cache
     *
     * @param string $key Clé de cache
     *
     * @return mixed|false Valeur en cache ou false si expirée/inexistante
     */
    public function get($key)
    {
        if (!$this->enabled) {
            return false;
        }

        $filename = $this->getCacheFilename($key);

        if (!file_exists($filename)) {
            return false;
        }

        $data = @unserialize(file_get_contents($filename));

        if ($data === false) {
            return false;
        }

        // Vérifier l'expiration
        if (isset($data['expires']) && time() > $data['expires']) {
            @unlink($filename);
            return false;
        }

        return isset($data['value']) ? $data['value'] : false;
    }

    /**
     * Stocke une valeur dans le cache
     *
     * @param string $key   Clé de cache
     * @param mixed  $value Valeur à stocker
     * @param int    $ttl   Durée de vie en secondes (optionnel)
     *
     * @return bool True si succès, false sinon
     */
    public function set($key, $value, $ttl = null)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($ttl === null) {
            $ttl = $this->defaultTtl;
        }

        $filename = $this->getCacheFilename($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        return @file_put_contents($filename, serialize($data)) !== false;
    }

    /**
     * Supprime une entrée du cache
     *
     * @param string $key Clé de cache
     *
     * @return bool True si succès, false sinon
     */
    public function delete($key)
    {
        if (!$this->enabled) {
            return false;
        }

        $filename = $this->getCacheFilename($key);

        if (file_exists($filename)) {
            return @unlink($filename);
        }

        return true;
    }

    /**
     * Supprime toutes les entrées de cache correspondant à un pattern
     *
     * @param string $pattern Pattern de clé (ex: "collaborator_*")
     *
     * @return int Nombre de fichiers supprimés
     */
    public function deletePattern($pattern)
    {
        if (!$this->enabled) {
            return 0;
        }

        $count = 0;
        $pattern = str_replace('*', '.*', $pattern);

        if (is_dir($this->cacheDir)) {
            $files = scandir($this->cacheDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $key = str_replace('.cache', '', $file);
                if (preg_match('/'.$pattern.'/', $key)) {
                    if (@unlink($this->cacheDir.'/'.$file)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Vide tout le cache
     *
     * @return int Nombre de fichiers supprimés
     */
    public function clear()
    {
        if (!$this->enabled) {
            return 0;
        }

        $count = 0;

        if (is_dir($this->cacheDir)) {
            $files = scandir($this->cacheDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                if (@unlink($this->cacheDir.'/'.$file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Nettoie les entrées expirées du cache
     *
     * @return int Nombre de fichiers supprimés
     */
    public function cleanExpired()
    {
        if (!$this->enabled) {
            return 0;
        }

        $count = 0;
        $now = time();

        if (is_dir($this->cacheDir)) {
            $files = scandir($this->cacheDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filepath = $this->cacheDir.'/'.$file;
                $data = @unserialize(file_get_contents($filepath));

                if ($data !== false && isset($data['expires']) && $now > $data['expires']) {
                    if (@unlink($filepath)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Récupère ou calcule une valeur avec mise en cache
     *
     * Fonction de commodité qui récupère la valeur du cache si elle existe,
     * sinon exécute le callback, stocke le résultat et le retourne.
     *
     * @param string   $key      Clé de cache
     * @param callable $callback Fonction à exécuter si cache manquant
     * @param int      $ttl      Durée de vie en secondes (optionnel)
     *
     * @return mixed Valeur en cache ou résultat du callback
     */
    public function remember($key, $callback, $ttl = null)
    {
        $value = $this->get($key);

        if ($value !== false) {
            return $value;
        }

        $value = call_user_func($callback);
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Obtient le chemin complet du fichier de cache
     *
     * @param string $key Clé de cache
     *
     * @return string Chemin du fichier
     */
    private function getCacheFilename($key)
    {
        // Nettoyer la clé pour éviter les problèmes de sécurité
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return $this->cacheDir.'/'.$key.'.cache';
    }

    /**
     * Obtient des statistiques sur le cache
     *
     * @return array Statistiques du cache:
     *               - 'total_files' (int): Nombre total de fichiers
     *               - 'total_size' (int): Taille totale en octets
     *               - 'expired_files' (int): Nombre de fichiers expirés
     */
    public function getStats()
    {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'expired_files' => 0
        ];

        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return $stats;
        }

        $now = time();
        $files = scandir($this->cacheDir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $this->cacheDir.'/'.$file;
            $stats['total_files']++;
            $stats['total_size'] += filesize($filepath);

            $data = @unserialize(file_get_contents($filepath));
            if ($data !== false && isset($data['expires']) && $now > $data['expires']) {
                $stats['expired_files']++;
            }
        }

        return $stats;
    }

    /**
     * Active ou désactive le cache
     *
     * @param bool $enabled True pour activer, false pour désactiver
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool)$enabled;
    }

    /**
     * Vérifie si le cache est activé
     *
     * @return bool True si activé, false sinon
     */
    public function isEnabled()
    {
        return $this->enabled;
    }
}
