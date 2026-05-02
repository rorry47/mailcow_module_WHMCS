<?php
namespace Mailcow;

/**
 * MailCow API Client
 *
 * All configuration is passed via $params from WHMCS (server settings + product configoptions).
 * config.php is no longer used.
 *
 * Compatible with PHP 7.4+
 */
class MailcowAPI
{
    /** @var string */
    private $API_KEY;

    /** @var string */
    private $baseurl;

    /** @var int */
    private $aliases;

    /** @var int */
    private $NUM_MAILBOXES;

    /** @var int */
    private $MAILBOXQUOTA;

    /** @var int */
    private $DEFQUOTA;

    /** @var int */
    private $TOTAL_QUOTA;

    /** @var int */
    private $rl_value;

    /** @var string */
    private $rl_frame;

    /** @var array Valid rate-limit frame values accepted by Mailcow API */
    private static $VALID_RL_FRAMES = ['s', 'm', 'h', 'd'];

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------
    public function __construct(array $params)
    {
        if (empty($params['serveraccesshash'])) {
            throw new \Exception($this->_lang('mailcow_api_key_missing', 'API Key is not provided.'));
        }

        $this->API_KEY = trim((string)$params['serveraccesshash']);

        // BUG FIX: validate hostname to prevent SSRF — only allow valid hostnames/IPs
        $hostname = isset($params['serverhostname']) ? trim((string)$params['serverhostname']) : '';
        if (empty($hostname) || !self::_isValidHostname($hostname)) {
            throw new \Exception('Invalid or missing server hostname.');
        }
        $this->baseurl = 'https://' . $hostname;

        // Read limits from WHMCS product ConfigOptions (configoption1..7)
        $this->aliases       = self::_posInt($params, 'configoption1', 100);
        $this->NUM_MAILBOXES = self::_posInt($params, 'configoption2', 10);
        $this->MAILBOXQUOTA  = self::_posInt($params, 'configoption3', 1024);
        $this->DEFQUOTA      = self::_posInt($params, 'configoption4', 1024);
        $this->TOTAL_QUOTA   = self::_posInt($params, 'configoption5', 10240);
        $this->rl_value      = self::_posInt($params, 'configoption6', 10);

        // BUG FIX: whitelist rl_frame to prevent injection into API payload
        $rlFrame = isset($params['configoption7']) ? trim((string)$params['configoption7']) : '';
        $this->rl_frame = in_array($rlFrame, self::$VALID_RL_FRAMES, true) ? $rlFrame : 's';
    }

    // -----------------------------------------------------------------------
    // Input helpers
    // -----------------------------------------------------------------------

    /**
     * Extract a positive integer config option, fall back to $default.
     */
    private static function _posInt(array $params, string $key, int $default): int
    {
        if (isset($params[$key]) && $params[$key] !== '') {
            $v = (int)$params[$key];
            return $v > 0 ? $v : $default;
        }
        return $default;
    }

    /**
     * Basic hostname/IP validation to prevent SSRF via crafted server settings.
     * Allows: valid hostnames, IPv4. Blocks: paths, schemes, port injections.
     */
    private static function _isValidHostname(string $host): bool
    {
        // Strip port if present (e.g. mail.example.com:8443)
        $hostOnly = preg_replace('/:\d+$/', '', $host);

        // IPv4
        if (filter_var($hostOnly, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        // Hostname: labels separated by dots, each 1-63 chars [a-z0-9-]
        if (preg_match('/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $hostOnly)) {
            return true;
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Lang helper
    // -----------------------------------------------------------------------
    private function _lang(string $key, string $fallback): string
    {
        global $_LANG;
        return (!empty($_LANG[$key])) ? (string)$_LANG[$key] : $fallback;
    }

    // -----------------------------------------------------------------------
    // HTTP helper
    // -----------------------------------------------------------------------

    /**
     * @param string      $method  GET | POST | DELETE
     * @param string      $uri     API path, e.g. /api/v1/add/domain
     * @param mixed       $body    Array or null
     * @return array{code:int, body:mixed, raw:string}
     * @throws \Exception on cURL error or json_encode failure
     */
    private function _request(string $method, string $uri, $body = null): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseurl . $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        // BUG FIX: verify SSL peer AND host (was only VERIFYPEER=false before)
        // Set to true in production. Can be toggled via server "Use SSL" flag if needed.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if ($method === 'POST') {
            $encoded = $this->_jsonEncode($body);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_jsonEncode($body));
            }
        }
        // GET: default, no extra options needed

        // BUG FIX: curl_exec can return false on failure — handle explicitly
        $response = curl_exec($ch);

        if ($response === false || curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \Exception($this->_lang('mailcow_curl_error', 'cURL error: ') . $err);
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        return [
            'code' => $httpCode,
            'body' => ($decoded !== null) ? $decoded : $response,
            'raw'  => (string)$response,
        ];
    }

    /**
     * json_encode with error checking.
     * @throws \Exception if encoding fails
     */
    private function _jsonEncode($data): string
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            throw new \Exception('Failed to encode request body: ' . json_last_error_msg());
        }
        return $encoded;
    }

    // -----------------------------------------------------------------------
    // Domain management
    // -----------------------------------------------------------------------

    public function addDomain(array $params): array
    {
        return $this->_manageDomain((string)$params['domain'], 'create');
    }

    public function editDomain(array $params): array
    {
        return $this->_manageDomain((string)$params['domain'], 'edit');
    }

    public function disableDomain(array $params): array
    {
        return $this->_manageDomain((string)$params['domain'], 'disable');
    }

    public function activateDomain(array $params): array
    {
        return $this->_manageDomain((string)$params['domain'], 'activate');
    }

    public function removeDomain(array $params): array
    {
        return $this->_manageDomain((string)$params['domain'], 'remove');
    }

    private function _manageDomain(string $domain, string $action): array
    {
        $attr = [
            'description'          => $domain,
            'aliases'              => $this->aliases,
            'mailboxes'            => $this->NUM_MAILBOXES,
            'defquota'             => $this->DEFQUOTA,
            'maxquota'             => $this->MAILBOXQUOTA,
            'quota'                => $this->TOTAL_QUOTA,
            'backupmx'             => 0,
            'relay_all_recipients' => 0,
            'rl_value'             => $this->rl_value,
            'rl_frame'             => $this->rl_frame,
        ];

        switch ($action) {
            case 'create':
                $attr['active']       = 1;
                $attr['domain']       = $domain;
                $attr['restart_sogo'] = 1;
                $attr['tags']         = $domain;
                return $this->_request('POST', '/api/v1/add/domain', $attr);

            case 'edit':
                return $this->_request('POST', '/api/v1/edit/domain', [
                    'items' => $domain,
                    'attr'  => $attr,
                ]);

            case 'disable':
                $attr['active'] = 0;
                return $this->_request('POST', '/api/v1/edit/domain', [
                    'items' => $domain,
                    'attr'  => $attr,
                ]);

            case 'activate':
                $attr['active'] = 1;
                return $this->_request('POST', '/api/v1/edit/domain', [
                    'items' => $domain,
                    'attr'  => $attr,
                ]);

            case 'remove':
                return $this->_request('POST', '/api/v1/delete/domain', ['items' => $domain]);
        }

        throw new \Exception("Unknown domain action: {$action}");
    }

    // -----------------------------------------------------------------------
    // Domain administrator management
    // -----------------------------------------------------------------------

    public function addDomainAdmin(array $params): array
    {
        return $this->_manageDomainAdmin(
            (string)$params['domain'],
            (string)$params['username'],
            (string)$params['password'],
            'create'
        );
    }

    public function disableDomainAdmin(array $params): array
    {
        return $this->_manageDomainAdmin(
            (string)$params['domain'],
            (string)$params['username'],
            (string)$params['password'],
            'disable'
        );
    }

    public function activateDomainAdmin(array $params): array
    {
        return $this->_manageDomainAdmin(
            (string)$params['domain'],
            (string)$params['username'],
            (string)$params['password'],
            'activate'
        );
    }

    public function changePasswordDomainAdmin(array $params): array
    {
        return $this->_manageDomainAdmin(
            (string)$params['domain'],
            (string)$params['username'],
            (string)$params['password'],
            'changepass'
        );
    }

    public function removeDomainAdmin(array $params): array
    {
        return $this->_manageDomainAdmin(
            (string)$params['domain'],
            (string)$params['username'],
            null,
            'remove'
        );
    }

    /**
     * @param string|null $password null only when action === 'remove'
     */
    private function _manageDomainAdmin(
        string $domain,
        string $username,
        $password,
        string $action
    ): array {
        switch ($action) {
            case 'create':
                return $this->_request('POST', '/api/v1/add/domain-admin', [
                    'active'    => 1,
                    'domains'   => $domain,
                    'username'  => $username,
                    'password'  => $password,
                    'password2' => $password,
                ]);

            case 'changepass':
            case 'edit':
                return $this->_request('POST', '/api/v1/edit/domain-admin', [
                    'items' => [$username],
                    'attr'  => [
                        'domains'      => [$domain],
                        'username_new' => $username,
                        'password'     => $password,
                        'password2'    => $password,
                        'active'       => 1,
                    ],
                ]);

            case 'disable':
                return $this->_request('POST', '/api/v1/edit/domain-admin', [
                    'items' => [$username],
                    'attr'  => [
                        'domains'      => [$domain],
                        'username_new' => $username,
                        'password'     => $password,
                        'password2'    => $password,
                        'active'       => 0,
                    ],
                ]);

            case 'activate':
                return $this->_request('POST', '/api/v1/edit/domain-admin', [
                    'items' => [$username],
                    'attr'  => [
                        'domains'      => [$domain],
                        'username_new' => $username,
                        'password'     => $password,
                        'password2'    => $password,
                        'active'       => 1,
                    ],
                ]);

            case 'remove':
                return $this->_request('POST', '/api/v1/delete/domain-admin', [
                    'items' => $username,
                ]);
        }

        throw new \Exception("Unknown admin action: {$action}");
    }

    // -----------------------------------------------------------------------
    // Mailbox removal (used on TerminateAccount)
    // -----------------------------------------------------------------------

    /**
     * BUG FIX: was silently swallowing errors and always returning 'error'.
     * Now throws on real failures; silently returns if domain has no mailboxes.
     */
    public function removeDomainMailbox(array $params): void
    {
        $result    = $this->_request('GET', '/api/v1/get/mailbox/all/' . rawurlencode((string)$params['domain']));
        $mailboxes = $result['body'];

        if (!is_array($mailboxes) || empty($mailboxes)) {
            return;
        }

        $usernames = array_values(array_filter(array_column($mailboxes, 'username')));
        if (!empty($usernames)) {
            $this->_request('POST', '/api/v1/delete/mailbox', $usernames);
        }
    }

    // -----------------------------------------------------------------------
    // Alias removal (used on TerminateAccount)
    // -----------------------------------------------------------------------

    /**
     * BUG FIX: was silently swallowing errors and always returning 'error'.
     * Now throws on real failures; silently returns if domain has no aliases.
     */
    public function removeDomainAliases(array $params): void
    {
        $result  = $this->_request('GET', '/api/v1/get/alias/all');
        $aliases = $result['body'];

        if (!is_array($aliases) || empty($aliases)) {
            return;
        }

        $domain = (string)$params['domain'];
        $ids    = [];
        foreach ($aliases as $item) {
            if (isset($item['domain']) && $item['domain'] === $domain && isset($item['id'])) {
                $ids[] = $item['id'];
            }
        }

        if (!empty($ids)) {
            $this->_request('POST', '/api/v1/delete/alias', $ids);
        }
    }

    // -----------------------------------------------------------------------
    // DKIM
    // -----------------------------------------------------------------------

    public function getDkim(string $domain): array
    {
        return $this->_request('GET', '/api/v1/get/dkim/' . rawurlencode($domain));
    }

    public function generateDkim(string $domain): array
    {
        return $this->_request('POST', '/api/v1/add/dkim', [
            'domains'       => $domain,
            'dkim_selector' => 'dkim',
            'key_size'      => 2048,
        ]);
    }

    // -----------------------------------------------------------------------
    // Test connection
    // -----------------------------------------------------------------------

    public function testConnection(): bool
    {
        $result = $this->_request('GET', '/api/v1/get/status/containers');
        return ($result['code'] === 200);
    }
}
