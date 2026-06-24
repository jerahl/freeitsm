<?php
/**
 * API: Save the Zabbix integration settings.
 * POST JSON { url, token?, refresh, min_severity, verify_ssl }
 * A masked/empty token leaves the stored token untouched.
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

$data = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $conn = connectToDatabase();
    zabbixSettingsSave($conn, [
        'url'          => $data['url']          ?? '',
        'token'        => $data['token']        ?? '',
        'refresh'      => $data['refresh']      ?? 30,
        'min_severity' => $data['min_severity'] ?? 0,
        'verify_ssl'   => !empty($data['verify_ssl']),
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
