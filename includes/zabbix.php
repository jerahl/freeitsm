<?php
/**
 * Zabbix JSON-RPC API client
 *
 * Thin wrapper around the Zabbix API (https://www.zabbix.com/documentation/current/en/manual/api)
 * used by the Service Status > Zabbix dashboard. Reads connection settings from
 * the ZABBIX_* constants defined in config.php.
 *
 * Requires Zabbix 6.0+ — authentication uses an API token sent as a Bearer
 * header (Users > API tokens in the Zabbix frontend).
 *
 * Usage:
 *   require_once __DIR__ . '/zabbix.php';
 *   $zbx = new ZabbixClient();
 *   if ($zbx->isConfigured()) {
 *       $data = $zbx->getDashboardData();
 *   }
 */

class ZabbixClient
{
    /** Zabbix default severity palette (matches the Zabbix frontend). */
    const SEVERITIES = [
        0 => ['name' => 'Not classified', 'color' => '#97AAB3'],
        1 => ['name' => 'Information',     'color' => '#7499FF'],
        2 => ['name' => 'Warning',         'color' => '#FFC859'],
        3 => ['name' => 'Average',         'color' => '#FFA059'],
        4 => ['name' => 'High',            'color' => '#E97659'],
        5 => ['name' => 'Disaster',        'color' => '#E45959'],
    ];

    private $url;
    private $token;
    private $minSeverity;
    private $verifySsl;

    public function __construct($url = null, $token = null, $minSeverity = null, $verifySsl = null)
    {
        $this->url = rtrim($url ?? (defined('ZABBIX_API_URL') ? ZABBIX_API_URL : ''), '/');
        $this->token = $token ?? (defined('ZABBIX_API_TOKEN') ? ZABBIX_API_TOKEN : '');
        $this->minSeverity = $minSeverity !== null
            ? (int) $minSeverity
            : (defined('ZABBIX_MIN_SEVERITY') ? (int) ZABBIX_MIN_SEVERITY : 0);
        $this->verifySsl = $verifySsl !== null
            ? (bool) $verifySsl
            : (defined('SSL_VERIFY_PEER') ? (bool) SSL_VERIFY_PEER : true);
    }

    /**
     * Build a client from the DB-backed System > Zabbix settings (falls back to
     * the ZABBIX_* constants for any unset value).
     */
    public static function fromSettings(PDO $conn): self
    {
        require_once __DIR__ . '/zabbix_settings.php';
        $cfg = zabbixSettingsLoad($conn);
        return new self($cfg['url'], $cfg['token'], $cfg['min_severity'], $cfg['verify_ssl']);
    }

    /** Whether the integration has the minimum config needed to talk to Zabbix. */
    public function isConfigured()
    {
        return $this->url !== '' && $this->token !== '';
    }

    /** Human-readable label for a severity level. */
    public static function severityName($level)
    {
        $level = (int) $level;
        return self::SEVERITIES[$level]['name'] ?? 'Unknown';
    }

    /** Hex colour for a severity level. */
    public static function severityColor($level)
    {
        $level = (int) $level;
        return self::SEVERITIES[$level]['color'] ?? '#97AAB3';
    }

    /**
     * Make a single JSON-RPC call against the Zabbix API.
     *
     * @param string $method e.g. 'problem.get'
     * @param array  $params
     * @return mixed The decoded "result" payload
     * @throws Exception on transport or API errors
     */
    public function call($method, array $params = [])
    {
        if (!$this->isConfigured()) {
            throw new Exception('Zabbix integration is not configured.');
        }

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => 1,
        ]);

        $verifySsl = $this->verifySsl;
        $ch = curl_init($this->url . '/api_jsonrpc.php');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json-rpc',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('Could not reach Zabbix: ' . $err);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Unexpected response from Zabbix (HTTP ' . $httpCode . ').');
        }
        if (isset($decoded['error'])) {
            $e = $decoded['error'];
            $msg = trim(($e['message'] ?? 'API error') . ' ' . ($e['data'] ?? ''));
            throw new Exception('Zabbix API error: ' . $msg);
        }

        return $decoded['result'] ?? null;
    }

    /** Zabbix server version string, e.g. "6.4.0". */
    public function apiVersion()
    {
        return $this->call('apiinfo.version');
    }

    /**
     * Fetch current (unresolved) problems, newest first, with the host name
     * resolved for each via a follow-up trigger.get.
     *
     * @param int $limit
     * @return array list of normalised problem rows
     */
    public function getProblems($limit = 100)
    {
        $problems = $this->call('problem.get', [
            'output'       => ['eventid', 'objectid', 'name', 'severity', 'clock', 'acknowledged', 'r_eventid'],
            'recent'       => false,            // only currently-active problems
            'sortfield'    => ['eventid'],
            'sortorder'    => 'DESC',
            'limit'        => $limit,
        ]);
        $problems = is_array($problems) ? $problems : [];

        // Resolve host names for the triggers behind these problems.
        $triggerIds = array_values(array_unique(array_filter(array_map(
            function ($p) { return $p['objectid'] ?? null; },
            $problems
        ))));

        $hostByTrigger = [];
        if (!empty($triggerIds)) {
            $triggers = $this->call('trigger.get', [
                'output'      => ['triggerid'],
                'selectHosts' => ['hostid', 'name'],
                'triggerids'  => $triggerIds,
            ]);
            foreach (is_array($triggers) ? $triggers : [] as $tr) {
                $hostName = $tr['hosts'][0]['name'] ?? '';
                $hostByTrigger[$tr['triggerid']] = $hostName;
            }
        }

        $rows = [];
        foreach ($problems as $p) {
            $sev = (int) ($p['severity'] ?? 0);
            if ($sev < $this->minSeverity) {
                continue;
            }
            $rows[] = [
                'eventid'      => $p['eventid'] ?? '',
                'name'         => $p['name'] ?? '(unnamed problem)',
                'host'         => $hostByTrigger[$p['objectid'] ?? ''] ?? '',
                'severity'     => $sev,
                'severityName' => self::severityName($sev),
                'severityColor'=> self::severityColor($sev),
                'clock'        => (int) ($p['clock'] ?? 0),
                'acknowledged' => (int) ($p['acknowledged'] ?? 0) === 1,
            ];
        }

        return $rows;
    }

    /**
     * Build the full payload the dashboard renders: problem list, per-severity
     * counts, host totals and Zabbix version.
     */
    public function getDashboardData($limit = 100)
    {
        $problems = $this->getProblems($limit);

        // Per-severity counts (seed every level so the summary tiles are stable).
        $counts = [];
        foreach (array_keys(self::SEVERITIES) as $level) {
            $counts[$level] = 0;
        }
        foreach ($problems as $p) {
            $counts[$p['severity']]++;
        }

        // Host inventory totals (available vs unavailable agent interfaces).
        $hosts = $this->call('host.get', [
            'output'           => ['hostid', 'name', 'status'],
            'selectInterfaces' => ['available'],
        ]);
        $hosts = is_array($hosts) ? $hosts : [];
        $hostsTotal = 0;
        $hostsDown = 0;
        foreach ($hosts as $h) {
            if ((int) ($h['status'] ?? 0) !== 0) {
                continue; // skip disabled hosts
            }
            $hostsTotal++;
            $unavailable = false;
            foreach ($h['interfaces'] ?? [] as $iface) {
                if ((int) ($iface['available'] ?? 0) === 2) { // 2 = unavailable
                    $unavailable = true;
                    break;
                }
            }
            if ($unavailable) {
                $hostsDown++;
            }
        }

        return [
            'version'      => $this->apiVersion(),
            'problems'     => $problems,
            'counts'       => $counts,
            'totalProblems'=> count($problems),
            'hostsTotal'   => $hostsTotal,
            'hostsDown'    => $hostsDown,
            'severities'   => self::SEVERITIES,
        ];
    }
}
