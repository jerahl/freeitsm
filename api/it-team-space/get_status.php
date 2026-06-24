<?php
/**
 * API: IT Team Space — live system status from Zabbix host groups.
 * GET — Returns { success, configured, systems:[{name,status,severity}], generated_at }
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
        echo json_encode(['success' => false, 'configured' => false,
            'error' => 'Zabbix is not configured. Set it up in System → Zabbix.']);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'configured'   => true,
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'systems'      => $zbx->getServiceStatus(12),
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'configured' => true, 'error' => $e->getMessage()]);
}
