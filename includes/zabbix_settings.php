<?php
/**
 * Zabbix integration settings — DB-backed, managed from System > Zabbix.
 *
 * Config lives in system_settings rows:
 *   zabbix_api_url          frontend URL (no trailing /api_jsonrpc.php)
 *   zabbix_api_token        encrypted + masked (Users > API tokens in Zabbix)
 *   zabbix_refresh_seconds  dashboard auto-refresh ('0' disables)
 *   zabbix_min_severity     0–5 (lowest severity surfaced)
 *   zabbix_verify_ssl       '1' | '0' (ANDed with the global SSL_VERIFY_PEER)
 *
 * Backward compatibility: when a row is absent, the ZABBIX_* constants from
 * config.php (or Docker env) are used as the default, so existing deployments
 * keep working until an admin saves from the UI.
 */

require_once __DIR__ . '/encryption.php';

/** The system_settings keys this module owns. */
function zabbixSettingsKeys(): array
{
    return [
        'url'          => 'zabbix_api_url',
        'token'        => 'zabbix_api_token',
        'refresh'      => 'zabbix_refresh_seconds',
        'min_severity' => 'zabbix_min_severity',
        'verify_ssl'   => 'zabbix_verify_ssl',
    ];
}

/**
 * Load the effective config (token decrypted). DB rows win; missing rows fall
 * back to the ZABBIX_* constants, then to hardcoded defaults.
 *
 * @return array{url:string,token:string,refresh:int,min_severity:int,verify_ssl:bool}
 */
function zabbixSettingsLoad(PDO $conn): array
{
    $keys = zabbixSettingsKeys();
    $rows = [];
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (?,?,?,?,?)");
    $stmt->execute(array_values($keys));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[$r['setting_key']] = $r['setting_value'];
    }

    // Constant fallbacks (config.php / Docker env).
    $cUrl     = defined('ZABBIX_API_URL')         ? ZABBIX_API_URL         : '';
    $cToken   = defined('ZABBIX_API_TOKEN')       ? ZABBIX_API_TOKEN       : '';
    $cRefresh = defined('ZABBIX_REFRESH_SECONDS') ? (int) ZABBIX_REFRESH_SECONDS : 30;
    $cMinSev  = defined('ZABBIX_MIN_SEVERITY')    ? (int) ZABBIX_MIN_SEVERITY    : 0;

    $url = $rows[$keys['url']] ?? '';
    if ($url === '' || $url === null) $url = $cUrl;

    $token = '';
    if (!empty($rows[$keys['token']])) {
        $token = (string) decryptValue($rows[$keys['token']]);
    } else {
        $token = $cToken;
    }

    $refresh = isset($rows[$keys['refresh']]) && $rows[$keys['refresh']] !== ''
        ? (int) $rows[$keys['refresh']] : $cRefresh;
    if ($refresh < 0) $refresh = 0;

    $minSev = isset($rows[$keys['min_severity']]) && $rows[$keys['min_severity']] !== ''
        ? (int) $rows[$keys['min_severity']] : $cMinSev;
    $minSev = max(0, min(5, $minSev));

    // Verify SSL: per-setting toggle (default on) ANDed with the global switch,
    // matching the AI settings behaviour so dev boxes without a CA bundle work.
    $globalVerify = defined('SSL_VERIFY_PEER') ? (bool) SSL_VERIFY_PEER : true;
    $rowVerify = true;
    if (array_key_exists($keys['verify_ssl'], $rows) && $rows[$keys['verify_ssl']] !== '' && $rows[$keys['verify_ssl']] !== null) {
        $rowVerify = $rows[$keys['verify_ssl']] === '1';
    }

    return [
        'url'          => rtrim((string) $url, '/'),
        'token'        => (string) $token,
        'refresh'      => $refresh,
        'min_severity' => $minSev,
        'verify_ssl'   => $globalVerify && $rowVerify,
    ];
}

/** UI-safe view — never returns the plaintext token, only a mask + flag. */
function zabbixSettingsForUi(PDO $conn): array
{
    $cfg = zabbixSettingsLoad($conn);
    return [
        'url'          => $cfg['url'],
        'refresh'      => $cfg['refresh'],
        'min_severity' => $cfg['min_severity'],
        'verify_ssl'   => $cfg['verify_ssl'] ? 1 : 0,
        'has_token'    => $cfg['token'] !== '',
        'masked_token' => $cfg['token'] !== '' ? maskSecret($cfg['token']) : '',
    ];
}

/**
 * Persist config. The token is encrypted; a masked/empty token is treated as
 * "no change" so saving the other fields never wipes it.
 *
 * @param array $data ['url','token'?,'refresh','min_severity','verify_ssl']
 */
function zabbixSettingsSave(PDO $conn, array $data): void
{
    $keys = zabbixSettingsKeys();

    $upsert = function (string $key, string $value) use ($conn) {
        $upd = $conn->prepare("UPDATE system_settings SET setting_value = :v WHERE setting_key = :k");
        $upd->execute([':k' => $key, ':v' => $value]);
        if ($upd->rowCount() === 0) {
            $ins = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v)
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $ins->execute([':k' => $key, ':v' => $value]);
        }
    };

    $upsert($keys['url'], rtrim(trim((string) ($data['url'] ?? '')), '/'));

    $refresh = (int) ($data['refresh'] ?? 30);
    if ($refresh < 0) $refresh = 0;
    $upsert($keys['refresh'], (string) $refresh);

    $minSev = max(0, min(5, (int) ($data['min_severity'] ?? 0)));
    $upsert($keys['min_severity'], (string) $minSev);

    $upsert($keys['verify_ssl'], !empty($data['verify_ssl']) ? '1' : '0');

    // Token: only write when the user actually entered a new value.
    $rawToken = $data['token'] ?? '';
    if (!isMaskedNoChangeValue($rawToken)) {
        $upsert($keys['token'], encryptValue(trim((string) $rawToken)));
    }
}
