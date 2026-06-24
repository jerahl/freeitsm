<?php
/**
 * API: Get the UI-safe Zabbix integration settings.
 * GET — Returns { success, url, refresh, min_severity, verify_ssl, has_token, masked_token }
 * Never returns the plaintext API token.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/zabbix_settings.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $conn = connectToDatabase();
    echo json_encode(['success' => true] + zabbixSettingsForUi($conn));
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
