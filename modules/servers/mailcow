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
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel'   => 'Login to Panel as Admin',
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
// ---------------------------------------------------------------------------

function mailcow_ClientArea(array $params): string
{
    // BUG FIX: use ?? instead of ?: so an empty string falls through correctly
    $address = (!empty($params['serverhostname'])) ? $params['serverhostname'] : $params['serverip'];

    if (empty($address)) {
        return '';
    }

    // ------------------------------------------------------------------
    // Handle DKIM generation POST
    // BUG FIX: validate CSRF token via WHMCS token field ($_POST['token'])
    // WHMCS automatically injects a 'token' field in client area forms.
    // ------------------------------------------------------------------
    if (isset($_POST['gen_dkim'])) {
        // Verify WHMCS CSRF token
        if (empty($_POST['token']) || !hash_equals($_SESSION['token'] ?? '', (string)$_POST['token'])) {
            return '<div class="alert alert-danger">Invalid request token.</div>';
        }

        try {
            $api = new MailcowAPI($params);
            $api->generateDkim((string)$params['domain']);
        } catch (Exception $e) {
            // Non-fatal — page will reload and show "not found" if it failed
        }

        // BUG FIX: safe redirect — use only path+query from REQUEST_URI,
        // never inject it raw into a header to prevent header injection
        $safePath = '/' . ltrim(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (!empty($_SERVER['QUERY_STRING'])) {
            $safePath .= '?' . $_SERVER['QUERY_STRING'];
        }
        header('Location: ' . $safePath);
        exit;
    }

    // ------------------------------------------------------------------
    // Fetch DKIM record
    // ------------------------------------------------------------------
    $dkimText = '';
    try {
        $api      = new MailcowAPI($params);
        $dkimResp = $api->getDkim((string)$params['domain']);
        // BUG FIX: PHP 7.4-safe: use isset() instead of ?? on nested keys
        $dkimText = isset($dkimResp['body']['dkim_txt']) ? (string)$dkimResp['body']['dkim_txt'] : '';
    } catch (Exception $e) {
        // Ignore — will display "not found"
    }

    // ------------------------------------------------------------------
    // Build DKIM block
    // ------------------------------------------------------------------
    if (empty($dkimText)) {
        // BUG FIX: add WHMCS CSRF token to the form
        $csrfToken = isset($_SESSION['token']) ? htmlspecialchars((string)$_SESSION['token']) : '';
        $dkimDisplay  = '<p class="text-warning">'
            . htmlspecialchars(mailcow_t('mailcow_dkim_missing', 'DKIM record not found.'))
            . '</p>'
            . '<form method="POST">'
            . '<input type="hidden" name="token" value="' . $csrfToken . '">'
            . '<button type="submit" name="gen_dkim" class="btn btn-sm btn-primary">'
            . htmlspecialchars(mailcow_t('mailcow_dkim_add', 'Generate DKIM'))
            . '</button>'
            . '</form>';
    } else {
        // $dkimText is already escaped below; use htmlspecialchars for output
        $dkimDisplay = '<pre class="pre-scrollable" style="word-break:break-all;">'
            . htmlspecialchars($dkimText)
            . '</pre>';
    }

    // ------------------------------------------------------------------
    // Escape all values for HTML output
    // ------------------------------------------------------------------
    $domain = htmlspecialchars((string)$params['domain']);
    $ip     = htmlspecialchars((string)$params['serverip']);
    $addr   = htmlspecialchars((string)$address);
    $user   = htmlspecialchars((string)$params['username']);

    // BUG FIX: do NOT display the plain-text password in the client area.
    // A password is a secret credential — it should never be rendered as
    // readable text after account creation. If the client needs to reset
    // it, they should use the "Change Password" function.
    // $pass is intentionally omitted from the output.

    $lUsername   = htmlspecialchars(mailcow_t('mailcow_username',   'Username'));
    $lMailServer = htmlspecialchars(mailcow_t('mailcow_mail_server', 'Mail Server'));
    $lDnsRecords = htmlspecialchars(mailcow_t('mailcow_dns_records', 'DNS Records'));
    $lDkimRecord = htmlspecialchars(mailcow_t('mailcow_dkim_record', 'DKIM Record'));

    // Build output without exposing password
    $html  = '<div class="row">';
    $html .=   '<div class="col-sm-5 text-right"><strong>' . $lUsername . '</strong></div>';
    $html .=   '<div class="col-sm-7 text-left">' . $user . '</div>';
    $html .= '</div>';

    $html .= '<div class="row">';
    $html .=   '<div class="col-sm-5 text-right"><strong>' . $lMailServer . '</strong></div>';
    $html .=   '<div class="col-sm-7 text-left">';
    $html .=     '<a href="https://' . $addr . '" target="_blank" rel="noopener noreferrer">' . $addr . '</a>';
    $html .=   '</div>';
    $html .= '</div>';

    $html .= '<hr>';

    $html .= '<div class="row">';
    $html .=   '<div class="col-sm-12 text-center"><strong>' . $lDnsRecords . '</strong></div>';
    $html .= '</div><br>';

    $html .= '<div class="row">';
    $html .=   '<div class="col-sm-5 text-right"><strong>mail.' . $domain . ' (A):</strong></div>';
    $html .=   '<div class="col-sm-7 text-left"><pre>' . $ip . '</pre></div>';
    $html .= '</div>';

    $html .= '<div class="row">';
    $html .=   '<div class="col-sm-5 text-right"><strong>' . $domain . ' (MX):</strong></div>';
    $html .=   '<div class="col-sm-7 text-left"><pre>mail.' . $domain . '</pre></div>';
    $html .= '</div>';

    $html .= '<div class="row">';
    $html .=   '<div class="col-sm-5 text-right"><strong>' . $domain . ' (TXT):</strong></div>';
    $html .=   '<div class="col-sm-7 text-left"><pre>v=spf1 a mx -all</pre></div>';
    $html .= '</div>';

    $html .= '<div class="row">';
    $html .=   '<div class="col-sm-5 text-right"><strong>_dmarc.' . $domain . ' (TXT):</strong></div>';
    $html .=   '<div class="col-sm-7 text-left"><pre>v=DMARC1;p=none</pre></div>';
    $html .= '</div>';

    $html .= '<div class="row">';
    $html .=   '<div class="col-sm-5 text-right"><strong>' . $lDkimRecord . ' (TXT):</strong></div>';
    $html .=   '<div class="col-sm-7">' . $dkimDisplay . '</div>';
    $html .= '</div>';

    return $html;
}
