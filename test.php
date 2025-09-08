<?php
// Fichier de diagnostic ultra-basique
// À placer dans /htdocs/custom/revenuesharing/test.php
// Accès : https://dolibarr.ohmnibus.com/custom/revenuesharing/test.php

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Diagnostic Revenue Sharing</title></head><body>";
echo "<h1>🔧 Diagnostic Revenue Sharing</h1>";

// Test 1: PHP de base
echo "<h2>1. Test PHP de base</h2>";
echo "✅ PHP fonctionne<br>";
echo "Version PHP: " . phpversion() . "<br>";
echo "Date/heure: " . date('Y-m-d H:i:s') . "<br>";

// Test 2: Répertoire courant
echo "<h2>2. Répertoire et fichiers</h2>";
echo "Répertoire courant: " . __DIR__ . "<br>";
echo "Fichier actuel: " . __FILE__ . "<br>";

// Test 3: Vérification des fichiers du module
echo "<h3>Fichiers du module :</h3>";
$files_to_check = [
    'index.php',
    'collaborator_list.php',
    'admin/setup.php',
    'class/collaborator.class.php',
    'core/modules/modRevenueSharing.class.php'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        $perms = substr(sprintf('%o', fileperms($full_path)), -4);
        echo "✅ $file (taille: $size octets, droits: $perms)<br>";
    } else {
        echo "❌ $file (manquant)<br>";
    }
}

// Test 4: Recherche de main.inc.php
echo "<h2>3. Recherche de Dolibarr</h2>";
$possible_dolibarr_paths = [
    __DIR__ . '/../../..',
    __DIR__ . '/../../../..',
    '/var/www/dolibarr',
    '/var/www/html/dolibarr',
    '/var/www/vhosts/ohmnibus.com/dolibarr',
    $_SERVER['DOCUMENT_ROOT'],
    $_SERVER['DOCUMENT_ROOT'] . '/dolibarr'
];

$dolibarr_found = false;
$dolibarr_path = '';

foreach ($possible_dolibarr_paths as $path) {
    if (file_exists($path . '/main.inc.php')) {
        echo "✅ Dolibarr trouvé: $path/main.inc.php<br>";
        $dolibarr_found = true;
        $dolibarr_path = $path;
        break;
    } else {
        echo "❌ Pas trouvé: $path/main.inc.php<br>";
    }
}

// Test 5: Tentative de chargement Dolibarr
if ($dolibarr_found) {
    echo "<h2>4. Test chargement Dolibarr</h2>";
    try {
        require_once $dolibarr_path . '/main.inc.php';
        echo "✅ main.inc.php chargé avec succès<br>";

        // Vérifier les variables Dolibarr
        if (isset($db)) {
            echo "✅ Base de données disponible<br>";
            echo "Type DB: " . $db->type . "<br>";
        } else {
            echo "❌ Variable \$db non disponible<br>";
        }

        if (isset($user)) {
            echo "✅ Utilisateur disponible<br>";
            echo "User ID: " . $user->id . "<br>";
            echo "User login: " . $user->login . "<br>";
        } else {
            echo "❌ Variable \$user non disponible<br>";
        }

        if (isset($conf)) {
            echo "✅ Configuration disponible<br>";
            echo "Entité: " . $conf->entity . "<br>";
        } else {
            echo "❌ Variable \$conf non disponible<br>";
        }

    } catch (Exception $e) {
        echo "❌ Erreur chargement Dolibarr: " . $e->getMessage() . "<br>";
    } catch (Error $e) {
        echo "❌ Erreur fatale Dolibarr: " . $e->getMessage() . "<br>";
    }
} else {
    echo "<h2>4. ❌ Dolibarr non trouvé</h2>";
    echo "Impossible de localiser main.inc.php<br>";
}

// Test 6: Vérification des tables si DB disponible
if (isset($db)) {
    echo "<h2>5. Test base de données</h2>";

    // Test de requête simple
    $sql = "SHOW TABLES";
    $resql = $db->query($sql);
    if ($resql) {
        $table_count = $db->num_rows($resql);
        echo "✅ Connexion DB OK - $table_count tables trouvées<br>";

        // Vérifier nos tables spécifiques
        $our_tables = [
            MAIN_DB_PREFIX . 'revenuesharing_collaborator',
            MAIN_DB_PREFIX . 'revenuesharing_contract'
        ];

        foreach ($our_tables as $table) {
            $sql_check = "SHOW TABLES LIKE '$table'";
            $resql_check = $db->query($sql_check);
            if ($resql_check && $db->num_rows($resql_check) > 0) {
                // Compter les enregistrements
                $sql_count = "SELECT COUNT(*) as nb FROM $table";
                $resql_count = $db->query($sql_count);
                if ($resql_count) {
                    $obj = $db->fetch_object($resql_count);
                    echo "✅ Table $table existe ($obj->nb enregistrements)<br>";
                } else {
                    echo "✅ Table $table existe (impossible de compter)<br>";
                }
            } else {
                echo "❌ Table $table manquante<br>";
            }
        }
    } else {
        echo "❌ Erreur connexion DB: " . $db->lasterror() . "<br>";
    }
}

// Test 7: Informations serveur
echo "<h2>6. Informations serveur</h2>";
echo "Serveur: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "User agent: " . $_SERVER['HTTP_USER_AGENT'] . "<br>";

// Test 8: Permissions et propriétaire
echo "<h2>7. Permissions</h2>";
$current_user = get_current_user();
$script_owner = fileowner(__FILE__);
$script_group = filegroup(__FILE__);
echo "Utilisateur PHP: $current_user<br>";
echo "Propriétaire fichier: $script_owner<br>";
echo "Groupe fichier: $script_group<br>";

// Test 9: Variables d'environnement importantes
echo "<h2>8. Configuration PHP importante</h2>";
$php_configs = [
    'memory_limit',
    'max_execution_time',
    'error_reporting',
    'display_errors',
    'log_errors',
    'upload_max_filesize'
];

foreach ($php_configs as $config) {
    echo "$config: " . ini_get($config) . "<br>";
}

// Test 10: Test inclusion basique
echo "<h2>9. Test inclusion fichier</h2>";
if (file_exists(__DIR__ . '/admin/setup.php')) {
    echo "setup.php existe - ";
    $content = file_get_contents(__DIR__ . '/admin/setup.php');
    if ($content !== false) {
        echo "lisible (" . strlen($content) . " caractères)<br>";
        // Vérifier les premiers caractères
        $first_chars = substr($content, 0, 10);
        echo "Début du fichier: " . htmlspecialchars($first_chars) . "<br>";
    } else {
        echo "❌ non lisible<br>";
    }
} else {
    echo "❌ setup.php introuvable<br>";
}

echo "<h2>🏁 Fin du diagnostic</h2>";
echo "<p>Si vous voyez ce message, PHP fonctionne au moins partiellement.</p>";

// Proposer des solutions
echo "<h2>💡 Solutions possibles</h2>";
echo "<ul>";
echo "<li>Si Dolibarr n'est pas trouvé → Corriger le chemin dans les fichiers</li>";
echo "<li>Si les tables manquent → Utiliser create_tables.php</li>";
echo "<li>Si erreurs de permissions → Vérifier chmod/chown</li>";
echo "<li>Si erreurs PHP → Vérifier la syntaxe des fichiers</li>";
echo "</ul>";

echo "<div style='margin: 20px 0;'>";
echo "<a href='admin/create_tables.php' style='background: #007cba; color: white; padding: 10px; text-decoration: none;'>🔧 Créer les tables</a> ";
echo "<a href='admin/setup.php' style='background: #28a745; color: white; padding: 10px; text-decoration: none;'>⚙️ Configuration</a>";
echo "</div>";

echo "</body></html>";
?>
