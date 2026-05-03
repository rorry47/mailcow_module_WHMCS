<?php
/**
 * MailCow Provisioning Module for WHMCS
 *
 * Based on the original module by Websavers Inc and rorry47.
 *
 * Features:
 *  - Tariff plans via WHMCS ConfigOptions (no more config.php)
 *  - Server settings via WHMCS Server Manager (hostname + API key)
 *  - SuspendAccount / UnsuspendAccount
 *  - ChangePassword (domain admin)
 *  - Localisation via lang/ files (english, russian, ukrainian)
 *
 * Compatible with PHP 7.4+
 *
 * @see https://developers.whmcs.com/provisioning-modules/
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/MailcowAPI.php';

use Mailcow\MailcowAPI;

// ---------------------------------------------------------------------------
// Language loader
// ---------------------------------------------------------------------------

function mailcow_loadLang(): void
{
    global $_LANG;

    $langDir  = __DIR__ . '/lang/';
    $language = 'english';

    if (!empty($GLOBALS['CONFIG']['Language'])) {
        // BUG FIX: strip everything except a-z and hyphens to prevent
        // path traversal attacks via a crafted $CONFIG['Language'] value
        $language = preg_replace('/[^a-z\-]/', '', strtolower((string)$GLOBALS['CONFIG']['Language']));
        if ($language === '') {
            $language = 'english';
        }
    }

    $langFile = $langDir . $language . '.php';

    // Fall back to English if language file not found
    if (!file_exists($langFile)) {
        $langFile = $langDir . 'english.php';
    }

    if (file_exists($langFile)) {
        include $langFile;
    }
}

mailcow_loadLang();

// ---------------------------------------------------------------------------
// Translation helper
// ---------------------------------------------------------------------------

function mailcow_t(string $key, string $fallback = ''): string
{
    global $_LANG;
    return (!empty($_LANG[$key])) ? (string)$_LANG[$key] : $fallback;
}

// ---------------------------------------------------------------------------
// MetaData
// ---------------------------------------------------------------------------

function mailcow_MetaData(): array
{
    return [
        'DisplayName'              => 'MailCow',
        'APIVersion'               => '1.1',
        'RequiresServer'           => true,
        'DefaultNonSSLPort'        => '80',
        'DefaultSSLPort'           => '443',
        'ShowPanelLoginLink'       => false,
    ];
}

// ---------------------------------------------------------------------------
// ConfigOptions — per-product tariff settings
// Replaces config.php entirely. Set in WHMCS → Products → Module Settings.
// ---------------------------------------------------------------------------

function mailcow_ConfigOptions(): array
{
    return [
        // configoption1
        'Aliases Limit' => [
            'Type'        => 'text',
            'Size'        => '10',
            'Default'     => '100',
            'Description' => 'Max number of aliases for the domain',
        ],
        // configoption2
        'Mailboxes Limit' => [
            'Type'        => 'text',
            'Size'        => '10',
            'Default'     => '10',
            'Description' => 'Max number of mailboxes for the domain',
        ],
        // configoption3
        'Mailbox Quota (MB)' => [
            'Type'        => 'text',
            'Size'        => '10',
            'Default'     => '1024',
            'Description' => 'Maximum quota per individual mailbox (MB)',
        ],
        // configoption4
        'Default Mailbox Quota (MB)' => [
            'Type'        => 'text',
            'Size'        => '10',
            'Default'     => '1024',
            'Description' => 'Default quota pre-filled when creating a mailbox (MB)',
        ],
        // configoption5
        'Total Domain Quota (MB)' => [
            'Type'        => 'text',
            'Size'        => '10',
            'Default'     => '10240',
            'Description' => 'Total quota for all mailboxes in the domain combined (MB)',
        ],
        // configoption6
        'Rate Limit Value' => [
            'Type'        => 'text',
            'Size'        => '10',
            'Default'     => '10',
            'Description' => 'Rate limit value (number of messages per frame)',
        ],
        // configoption7
        'Rate Limit Frame' => [
            'Type'        => 'dropdown',
            'Options'     => 's,m,h,d',
            'Default'     => 's',
            'Description' => 'Rate limit time frame: s=second, m=minute, h=hour, d=day',
        ],

    ];
}

// ---------------------------------------------------------------------------
// CreateAccount
// ---------------------------------------------------------------------------

function mailcow_CreateAccount(array $params): string
{
    try {
        $api = new MailcowAPI($params);
        $api->addDomain($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    try {
        $api = new MailcowAPI($params);
        $api->addDomainAdmin($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    return 'success';
}

// ---------------------------------------------------------------------------
// SuspendAccount — disable domain + disable domain admin in Mailcow
// ---------------------------------------------------------------------------

function mailcow_SuspendAccount(array $params): string
{
    try {
        $api = new MailcowAPI($params);
        $api->disableDomain($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    try {
        $api = new MailcowAPI($params);
        $api->disableDomainAdmin($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    return 'success';
}

// ---------------------------------------------------------------------------
// UnsuspendAccount — re-enable domain + domain admin in Mailcow
// ---------------------------------------------------------------------------

function mailcow_UnsuspendAccount(array $params): string
{
    try {
        $api = new MailcowAPI($params);
        $api->activateDomain($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    try {
        $api = new MailcowAPI($params);
        $api->activateDomainAdmin($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    return 'success';
}

// ---------------------------------------------------------------------------
// TerminateAccount — remove mailboxes, aliases, domain, domain admin
// ---------------------------------------------------------------------------

function mailcow_TerminateAccount(array $params): string
{
    if ($params['status'] === 'Terminated') {
        return mailcow_t('mailcow_already_deleted', 'Account has already been deleted!');
    }

    try {
        $api = new MailcowAPI($params);
        $api->removeDomainMailbox($params);
        $api->removeDomainAliases($params);
        $api->removeDomain($params);
        $api->removeDomainAdmin($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    return 'success';
}

// ---------------------------------------------------------------------------
// ChangePassword — changes the domain admin password
// ---------------------------------------------------------------------------

function mailcow_ChangePassword(array $params): string
{
    try {
        $api    = new MailcowAPI($params);
        $result = $api->changePasswordDomainAdmin($params);
        logModuleCall('mailcow', __FUNCTION__, $params, $result, null);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    return 'success';
}


// ---------------------------------------------------------------------------
// ChangePackage — updates domain limits in Mailcow when tariff changes
// ---------------------------------------------------------------------------

function mailcow_ChangePackage(array $params): string
{
    try {
        $api = new MailcowAPI($params);
        $api->editDomain($params);
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }

    return 'success';
}

// ---------------------------------------------------------------------------
// TestConnection — verifies API key + server connectivity
// ---------------------------------------------------------------------------

function mailcow_TestConnection(array $params): array
{
    try {
        $api     = new MailcowAPI($params);
        $success = $api->testConnection();
        $error   = $success ? '' : mailcow_t('mailcow_connection_fail', 'Connection failed');
    } catch (Exception $e) {
        logModuleCall('mailcow', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        $success = false;
        $error   = $e->getMessage();
    }

    return [
        'success' => $success,
        'error'   => $error,
    ];
}

// ---------------------------------------------------------------------------
// ClientArea — rendered in the client portal for this service
// Tabs: Overview | Statistics | Upgrade
// ---------------------------------------------------------------------------

function mailcow_ClientArea(array $params): string
{
    $address = (!empty($params['serverhostname'])) ? $params['serverhostname'] : $params['serverip'];
    if (empty($address)) {
        return '';
    }

    $domain    = (string)$params['domain'];
    $username  = (string)$params['username'];
    $ip        = (string)$params['serverip'];
    $addr      = $address;

    // Active tab
    $tab = isset($_GET['mc_tab']) ? preg_replace('/[^a-z]/', '', (string)$_GET['mc_tab']) : 'overview';
    if (!in_array($tab, ['overview', 'stats'], true)) {
        $tab = 'overview';
    }


    // CSRF token — compatible with all WHMCS versions
    $csrfToken = '';
    if (!empty($_SESSION['WMCStokenID']))   { $csrfToken = (string)$_SESSION['WMCStokenID']; }
    elseif (!empty($_SESSION['WHMCS_token'])) { $csrfToken = (string)$_SESSION['WHMCS_token']; }
    elseif (!empty($_SESSION['token']))       { $csrfToken = (string)$_SESSION['token']; }
    else                                      { $csrfToken = session_id(); }

    $message = '';
    $error   = '';

    // ------------------------------------------------------------------
    // POST: DKIM regenerate
    // ------------------------------------------------------------------
    if (isset($_POST['gen_dkim'])) {
        $postToken = isset($_POST['token']) ? (string)$_POST['token'] : '';
        if (!hash_equals($csrfToken, $postToken)) {
            return '<div class="alert alert-danger">Invalid request token.</div>';
        }
        try {
            $api = new MailcowAPI($params);
            // Use regenerateDkim which deletes old key first, then creates new one
            $api->regenerateDkim($domain);
            $message = 'DKIM key regenerated successfully.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        // Safe redirect back
        $safePath = '/' . ltrim(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (!empty($_SERVER['QUERY_STRING'])) {
            $safePath .= '?' . $_SERVER['QUERY_STRING'];
        }
        if (empty($error)) {
            header('Location: ' . $safePath);
            exit;
        }
    }

    // ------------------------------------------------------------------
    // POST: DKIM regenerate
    // ------------------------------------------------------------------
    if (isset($_POST['gen_dkim'])) {
        $postToken = isset($_POST['token']) ? (string)$_POST['token'] : '';
        if (!hash_equals($csrfToken, $postToken)) {
            return '<div class="alert alert-danger">Invalid request token.</div>';
        }
        try {
            $api = new MailcowAPI($params);
            // Use regenerateDkim which deletes old key first, then creates new one
            $api->regenerateDkim($domain);
            $message = 'DKIM key regenerated successfully.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        // Safe redirect back
        $safePath = '/' . ltrim(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (!empty($_SERVER['QUERY_STRING'])) {
            $safePath .= '?' . $_SERVER['QUERY_STRING'];
        }
        if (empty($error)) {
            header('Location: ' . $safePath);
            exit;
        }
    }
    // ------------------------------------------------------------------
    // Build tab navigation URL helper
    // ------------------------------------------------------------------
    $baseUrl = '/' . ltrim(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $qs      = $_GET;
    unset($qs['mc_tab']);
    $qsBase  = http_build_query($qs);

    $tabUrl = function (string $t) use ($baseUrl, $qsBase): string {
        $q = $qsBase ? $qsBase . '&mc_tab=' . $t : 'mc_tab=' . $t;
        return htmlspecialchars($baseUrl . '?' . $q);
    };

    $csrfHidden  = htmlspecialchars($csrfToken);
    $domainH     = htmlspecialchars($domain);
    $addrH       = htmlspecialchars($addr);
    $ipH         = htmlspecialchars($ip);
    $userH       = htmlspecialchars($username);

    // ------------------------------------------------------------------
    // HTML output
    // ------------------------------------------------------------------
    $html = '';

    // Hide "Visit Website" button — works across all WHMCS versions
    $html .= '<style>.panel-service-overview .btn[href*="http"]:not(.btn-primary){display:none!important}</style>';

    if ($error)   { $html .= '<div class="alert alert-danger">'  . htmlspecialchars($error)   . '</div>'; }
    if ($message) { $html .= '<div class="alert alert-success">' . $message                             . '</div>'; }

    // Tab navigation — styled as button group
    $tabs = ['overview' => '&#9993; Overview', 'stats' => '&#128200; Statistics'];
    $html .= '<div class="btn-group" style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:4px">';
    foreach ($tabs as $key => $label) {
        $btnClass = ($tab === $key) ? 'btn btn-primary' : 'btn btn-default';
        $html .= '<a href="' . $tabUrl($key) . '" class="' . $btnClass . '">' . $label . '</a>';
    }
    $html .= '</div>';

    // ==================== TAB: OVERVIEW ====================
    if ($tab === 'overview') {

        // Direct link to Mailcow panel
        $html .= '<div style="margin-bottom:16px">';
        $html .= '<a href="https://' . $addrH . '" target="_blank" rel="noopener noreferrer" class="btn btn-primary">&#128274; Open Mailcow Panel</a>';
        $html .= '</div>';

        // Credentials
        $html .= '<div class="row"><div class="col-sm-4 text-right"><strong>Username</strong></div><div class="col-sm-8">' . $userH . '</div></div>';
        $html .= '<div class="row"><div class="col-sm-4 text-right"><strong>Mail Server</strong></div>';
        $html .= '<div class="col-sm-8"><a href="https://' . $addrH . '" target="_blank" rel="noopener noreferrer">' . $addrH . '</a></div></div>';

        $html .= '<hr>';

        // DNS records
        $html .= '<div class="row"><div class="col-sm-12 text-center"><strong>DNS Records</strong></div></div><br>';

        // Helper to render a DNS row
        $dnsRow = function (string $name, string $type, string $value) {
            return '<div class="row" style="margin-bottom:4px">'
                . '<div class="col-sm-5 text-right"><strong>' . $name . ' (' . $type . '):</strong></div>'
                . '<div class="col-sm-7"><pre style="margin:0;font-size:12px;word-break:break-all">' . $value . '</pre></div>'
                . '</div>';
        };

        // Mailcow server hostname — all records point here, not to mail.clientdomain.com
        // The client domain uses the shared Mailcow server as its mail host
        $mcHost = $addrH; // e.g. mail.nkotov.net

        // MX — points to Mailcow server hostname
        $html .= $dnsRow($domainH, 'MX', '10 ' . $mcHost);

        // Autodiscover / Autoconfig — CNAME to Mailcow server
        $html .= $dnsRow('autoconfig.' . $domainH,   'CNAME', $mcHost);
        $html .= $dnsRow('autodiscover.' . $domainH, 'CNAME', $mcHost);

        // SPF — authorize Mailcow server
        $html .= $dnsRow($domainH,             'TXT', 'v=spf1 mx a:' . $mcHost . ' -all');
        $html .= $dnsRow('_dmarc.' . $domainH, 'TXT', 'v=DMARC1; p=none; rua=mailto:postmaster@' . $domainH);

        // SRV records — all point to Mailcow server
        $html .= '<div class="row" style="margin-top:8px;margin-bottom:4px"><div class="col-sm-12"><strong>SRV Records</strong></div></div>';
        $srvRecords = [
            '_imap._tcp.'        . $domainH => '0 1 143 ' . $mcHost,
            '_imaps._tcp.'       . $domainH => '0 1 993 ' . $mcHost,
            '_pop3._tcp.'        . $domainH => '0 1 110 ' . $mcHost,
            '_pop3s._tcp.'       . $domainH => '0 1 995 ' . $mcHost,
            '_submission._tcp.'  . $domainH => '0 1 587 ' . $mcHost,
            '_submissions._tcp.' . $domainH => '0 1 465 ' . $mcHost,
            '_autodiscover._tcp.'. $domainH => '0 1 443 ' . $mcHost,
        ];
        foreach ($srvRecords as $name => $value) {
            $html .= $dnsRow($name, 'SRV', $value);
        }

        // TLSA (DANE) — values must be retrieved from Mailcow UI
        $html .= '<div class="row" style="margin-top:8px;margin-bottom:4px"><div class="col-sm-12"><strong>TLSA (DANE) — optional, requires DNSSEC</strong></div></div>';
        $html .= $dnsRow('_25._tcp.'  . $mcHost, 'TLSA', '3 1 1 &lt;see Mailcow UI&gt;');
        $html .= $dnsRow('_443._tcp.' . $mcHost, 'TLSA', '3 1 1 &lt;see Mailcow UI&gt;');
        $html .= '<div class="row" style="margin-bottom:8px"><div class="col-sm-12">'
            . '<small class="text-muted" style="font-size:11px">&#9432; TLSA record values are generated by Mailcow based on your TLS certificate. '
            . 'Get the exact values from: <a href="https://' . $addrH . '" target="_blank" rel="noopener noreferrer">Mailcow UI</a> '
            . '&rarr; <strong>Configuration &rarr; Server configuration &rarr; DNS</strong></small>'
            . '</div></div>';

        // DKIM
        $dkimText = '';
        try {
            $api      = new MailcowAPI($params);
            $dkimResp = $api->getDkim($domain);
            $dkimText = isset($dkimResp['body']['dkim_txt']) ? (string)$dkimResp['body']['dkim_txt'] : '';
        } catch (Exception $e) { /* ignore */ }

        $html .= '<div class="row" style="margin-top:8px">';
        $html .= '<div class="col-sm-5 text-right"><strong>DKIM (TXT):</strong></div>';
        $html .= '<div class="col-sm-7">';

        if (!empty($dkimText)) {
            $html .= '<pre style="word-break:break-all;font-size:12px">' . htmlspecialchars($dkimText) . '</pre>';
        } else {
            $html .= '<p class="text-warning">DKIM record not found.</p>';
        }

        // Always show regenerate button
        $html .= '<form method="POST" style="margin-top:4px">';
        $html .= '<input type="hidden" name="token" value="' . $csrfHidden . '">';
        $html .= '<button type="submit" name="gen_dkim" class="btn btn-sm btn-' . (empty($dkimText) ? 'primary' : 'default') . '">';
        $html .= empty($dkimText) ? 'Generate DKIM' : '&#8635; Regenerate DKIM';
        $html .= '</button></form>';
        $html .= '</div></div>';
    }

    // ==================== TAB: STATISTICS ====================
    if ($tab === 'stats') {
        $stats     = [];
        $mailboxes = [];
        try {
            $api       = new MailcowAPI($params);
            $stats     = $api->getDomainStats($domain);
            $mailboxes = $api->getMailboxes($domain);
        } catch (Exception $e) {
            $html .= '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        if (!empty($stats)) {
            // Mailcow API returns quota values in BYTES for domain endpoint.
            // max_quota = total domain quota in bytes
            // quota_used_in_domain / bytes_total = used bytes

            $usedBytes = 0;
            if (isset($stats['quota_used_in_domain'])) {
                $usedBytes = (int)$stats['quota_used_in_domain'];
            } elseif (isset($stats['bytes_total'])) {
                $usedBytes = (int)$stats['bytes_total'];
            }
            $usedQuotaMb = (int)round($usedBytes / 1048576);

            // max_quota: try bytes first, fall back to MB interpretation
            // Mailcow stores it in bytes internally
            $maxQuotaRaw  = isset($stats['max_quota']) ? (int)$stats['max_quota'] : 0;
            // If value > 1048576 it's bytes, otherwise it's already MB (older API versions)
            $totalQuotaMb = $maxQuotaRaw > 1048576
                ? (int)round($maxQuotaRaw / 1048576)
                : $maxQuotaRaw;

            // Fallback: use configoption5 (product setting) if API returns 0
            if ($totalQuotaMb === 0 && !empty($params['configoption5'])) {
                $totalQuotaMb = (int)$params['configoption5'];
            }

            $freeQuotaMb  = max(0, $totalQuotaMb - $usedQuotaMb);

            $maxMailboxes = isset($stats['max_mailboxes'])    ? (int)$stats['max_mailboxes']    : 0;
            $numMailboxes = isset($stats['mboxes_in_domain']) ? (int)$stats['mboxes_in_domain'] : count($mailboxes);
            $mboxesLeft   = isset($stats['mboxes_left'])      ? (int)$stats['mboxes_left']      : max(0, $maxMailboxes - $numMailboxes);

            // Total allocated quota = sum of all mailbox quotas (what admin actually assigned)
            $allocatedMb = 0;
            foreach ($mailboxes as $mb) {
                $allocatedMb += isset($mb['quota']) ? (int)round($mb['quota'] / 1048576) : 0;
            }

            $pct      = $totalQuotaMb > 0 ? min(100, round($usedQuotaMb / $totalQuotaMb * 100)) : 0;
            $barColor = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');

            $html .= '<h4>Storage</h4>';
            $html .= '<div class="progress" style="height:22px;margin-bottom:8px">';
            $html .= '<div class="progress-bar progress-bar-' . $barColor . '" style="width:' . $pct . '%;line-height:22px">' . $pct . '%</div>';
            $html .= '</div>';
            $html .= '<table class="table table-condensed" style="margin-top:4px"><tbody>';
            $html .= '<tr><td><strong>Used</strong></td><td>'             . number_format($usedQuotaMb)   . ' MB</td></tr>';
            $html .= '<tr><td><strong>Free</strong></td><td>'             . number_format($freeQuotaMb)   . ' MB</td></tr>';
            $html .= '<tr><td><strong>Total domain quota</strong></td><td>' . number_format($totalQuotaMb) . ' MB</td></tr>';
            $html .= '<tr><td><strong>Allocated to mailboxes</strong></td><td>' . number_format($allocatedMb) . ' MB</td></tr>';
            $html .= '</tbody></table>';

            $html .= '<h4>Mailboxes</h4>';
            $html .= '<table class="table table-condensed"><tbody>';
            $html .= '<tr><td><strong>Created</strong></td><td>' . $numMailboxes . ' / ' . $maxMailboxes . '</td></tr>';
            $html .= '<tr><td><strong>Available slots</strong></td><td>' . $mboxesLeft . '</td></tr>';
            $html .= '</tbody></table>';
        }

        if (!empty($mailboxes)) {
            $html .= '<h4>Mailbox details</h4>';
            $html .= '<table class="table table-condensed table-striped">';
            $html .= '<thead><tr><th>Address</th><th>Used (MB)</th><th>Quota (MB)</th><th>%</th></tr></thead><tbody>';
            foreach ($mailboxes as $mb) {
                $mbUsed  = isset($mb['quota_used'])  ? (int)round($mb['quota_used']  / 1048576) : 0;
                $mbTotal = isset($mb['quota'])        ? (int)round($mb['quota']        / 1048576) : 0;
                $mbPct   = $mbTotal > 0 ? min(100, round($mbUsed / $mbTotal * 100)) : 0;
                $mbAddr  = htmlspecialchars((string)($mb['username'] ?? ''));
                $html .= '<tr>';
                $html .= '<td>' . $mbAddr . '</td>';
                $html .= '<td>' . number_format($mbUsed) . '</td>';
                $html .= '<td>' . number_format($mbTotal) . '</td>';
                $html .= '<td><div class="progress" style="height:14px;margin:0"><div class="progress-bar" style="width:' . $mbPct . '%;line-height:14px;font-size:11px">' . $mbPct . '%</div></div></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        if (empty($stats) && empty($mailboxes)) {
            $html .= '<p class="text-muted">No statistics available.</p>';
        }
    }

    return $html;
}
