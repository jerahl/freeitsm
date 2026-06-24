<?php
/**
 * Configuration file for Service Desk Ticketing System
 *
 * Mailbox settings (Azure AD credentials, OAuth tokens, etc.) are now stored
 * in the target_mailboxes database table and managed via Settings > Mailboxes.
 */

// Load database credentials from secure location (outside web root)
// Update this path to match your db_config.php location
$db_config_path = 'C:\wamp64\db_config.php';
require_once($db_config_path);

// Timezone
date_default_timezone_set('America/New_York');

// SSL Certificate Verification
// WARNING: Setting this to false is INSECURE and should ONLY be used for testing!
// For production, configure php.ini with proper CA certificate bundle
// Download from: https://curl.se/ca/cacert.pem
define('SSL_VERIFY_PEER', false);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Zabbix integration (Service Status > Zabbix dashboard)
 * -----------------------------------------------------------------------------
 * Fill these in to pull live problems/severity from your Zabbix server via the
 * JSON-RPC API. Requires Zabbix 6.0+ (Bearer API token auth).
 *
 *   1. In Zabbix: Users > API tokens > Create API token (assign to a user with
 *      read access to the hosts/problems you want surfaced).
 *   2. ZABBIX_API_URL  — your frontend URL WITHOUT the trailing api_jsonrpc.php,
 *      e.g. 'https://zabbix.example.com' (the client appends /api_jsonrpc.php).
 *   3. ZABBIX_API_TOKEN — the generated API token string.
 *
 * Leave ZABBIX_API_URL empty to keep the integration disabled; the dashboard
 * page then shows a "not configured" message instead of erroring.
 */
if (!defined('ZABBIX_API_URL'))   define('ZABBIX_API_URL', '');   // e.g. 'https://zabbix.example.com'
if (!defined('ZABBIX_API_TOKEN')) define('ZABBIX_API_TOKEN', ''); // e.g. 'a1b2c3...'
// Auto-refresh interval (seconds) for the dashboard. 0 disables auto-refresh.
if (!defined('ZABBIX_REFRESH_SECONDS')) define('ZABBIX_REFRESH_SECONDS', 30);
// Minimum severity to show (0=Not classified … 5=Disaster). 0 shows everything.
if (!defined('ZABBIX_MIN_SEVERITY')) define('ZABBIX_MIN_SEVERITY', 0);

/**
 * BASE_URL — absolute URL path prefix for the app's deployment root.
 *
 * Examples:
 *   App served at http://localhost/freeitsm-app/ → BASE_URL = '/freeitsm-app/'
 *   App served at https://itsm.company.com/      → BASE_URL = '/'
 *
 * Used everywhere we build internal links so we don't have to fiddle with
 * $path_prefix or '../' on every page. Auto-detected from the filesystem
 * location of this config.php relative to the web server's DOCUMENT_ROOT.
 */
if (!defined('BASE_URL')) {
    $__appRoot = str_replace('\\', '/', realpath(__DIR__));
    $__docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $__rel = '';
    if ($__docRoot && strpos($__appRoot, $__docRoot) === 0) {
        $__rel = substr($__appRoot, strlen($__docRoot));
    }
    $__rel = '/' . trim($__rel, '/') . '/';
    if ($__rel === '//') $__rel = '/'; // app deployed at document root
    define('BASE_URL', $__rel);
    unset($__appRoot, $__docRoot, $__rel);
}
?>
