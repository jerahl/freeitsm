<?php
/**
 * API: Test the Zabbix connection.
 * POST JSON { url, token?, verify_ssl } — tests the entered values without
 * saving. A masked/empty token falls back to the stored one, so an admin can
 * re-test an existing config without re-typing the secret.
 * Returns { success, version } on success.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/encryption.php';
require_once '../../includes/zabbix_settings.php';
require_once '../../includes/zabbix.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $conn = connectToDatabase();
    $stored = zabbixSettingsLoad($conn);

    $url = rtrim(trim((string) ($data['url'] ?? '')), '/');
    if ($url === '') $url = $stored['url'];

    $token = $data['token'] ?? '';
    if (isMaskedNoChangeValue($token)) {
        $token = $stored['token']; // keep existing secret
    } else {
        $token = trim((string) $token);
    }

    $verifySsl = array_key_exists('verify_ssl', $data)
        ? (!empty($data['verify_ssl']) && (defined('SSL_VERIFY_PEER') ? (bool) SSL_VERIFY_PEER : true))
        : $stored['verify_ssl'];

    if ($url === '' || $token === '') {
        echo json_encode(['success' => false, 'error' => 'Enter a Zabbix URL and API token first.']);
        exit;
    }

    $zbx = new ZabbixClient($url, $token, $stored['min_severity'], $verifySsl);
    $version = $zbx->apiVersion();
    echo json_encode(['success' => true, 'version' => $version]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
