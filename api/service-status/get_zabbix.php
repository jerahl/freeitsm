<?php
/**
 * API: Service Status — Zabbix live data
 * GET — Returns current Zabbix problems, per-severity counts and host totals.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/zabbix.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $conn = connectToDatabase();
    $zbx = ZabbixClient::fromSettings($conn);

    if (!$zbx->isConfigured()) {
        echo json_encode([
            'success'    => false,
            'configured' => false,
            'error'      => 'Zabbix integration is not configured. Set ZABBIX_API_URL and ZABBIX_API_TOKEN in config.php.',
        ]);
        exit;
    }

    $data = $zbx->getDashboardData();

    echo json_encode(array_merge(
        ['success' => true, 'configured' => true, 'generated_at' => gmdate('Y-m-d\TH:i:s\Z')],
        $data
    ));

} catch (Exception $e) {
    echo json_encode(['success' => false, 'configured' => true, 'error' => $e->getMessage()]);
}
