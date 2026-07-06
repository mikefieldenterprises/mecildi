<?php
// 1. Load the raw array from INI
$configFile = __DIR__ . '/config/app.ini';
if (!file_exists($configFile)) {
    die("Config file not found: " . $configFile);
}
$configArray = parse_ini_file($configFile, true, INI_SCANNER_TYPED);

// Load libraries
require_once __DIR__ . '/vendor/autoload.php';

// 2. Load the Class definition
require_once __DIR__ . '/functions/AppConfig.php';

// 3. IMPORTANT: Turn that array into the OBJECT
$config = new AppConfig($configArray);

// 4. Error handling (Use the object syntax now)
$debugMode = $config->debug['debug_mode'] ?? 'off';

ini_set('display_errors', $debugMode === 'on' ? '1' : '0');
ini_set('display_startup_errors', $debugMode === 'on' ? '1' : '0');
error_reporting(E_ALL);

// 5. Load shared functions
require_once __DIR__ . '/functions/utils.php';
require_once __DIR__ . '/functions/db.php';
require_once __DIR__ . '/functions/logger.php';
require_once __DIR__ . '/functions/heuristics.php';
require_once __DIR__ . '/functions/language-detection.php';
require_once __DIR__ . '/functions/excel-conversion.php';
require_once __DIR__ . '/functions/batch-processor.php';

// Load user preferences and define global constants accordingly
$prefFile = __DIR__ . '/config/preferences.json';
$prefs = file_exists($prefFile) ? json_decode(file_get_contents($prefFile), true) : [];
define('SIMPLIFIED_MODE', $prefs['simplified_mode'] ?? false);
define('LOG_ALL_LANG_VALUES', $prefs['log_all_lang_values'] ?? false);

// Load version number into APP_VERSION constant
$versionFile = __DIR__ . '/VERSION';
$versionValue = '1.0.0'; // Fallback value just in case
if (file_exists($versionFile)) {
    $versionValue = trim(file_get_contents($versionFile));
}
define('APP_VERSION', $versionValue);

?>