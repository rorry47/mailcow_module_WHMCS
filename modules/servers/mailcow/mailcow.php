<?php
/**
 * MailCow Provisioning Module by Websavers Inc
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Require any libraries needed for the module to function.
require_once 'lib/MailcowAPI.php';
use Mailcow\MailcowAPI;
use WHMCS\Input\Sanitize;
use WHMCS\Database\Capsule;

function mailcow_MetaData()
{
    return array(
        'DisplayName' => 'MailCow',
        'APIVersion' => '3.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '80', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '443', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}

function mailcow_ConfigOptions(){

    return array(
        'Default Mailbox Quota' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '1024',
            'Description' => 'When specifying per-account limits, this storage limit will be applied to each mailbox. Enter in megabytes',
        )
    );
}

function mailcow_CreateAccount(array $params)
{

    try {
      
      $mailcow_d = new MailcowAPI($params);
      $result_d = $mailcow_d->addDomain($params);
    
    } catch (Exception $e) {

      return $e->getMessage();
    }

    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->addDomainAdmin($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }

    return 'success';
}


function mailcow_SuspendAccount(array $params)
{
    try {
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->disableDomain($params);
    } catch (Exception $e) {
        return $e->getMessage();
    }
    try {
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->disableDomainAdmin($params);
    } catch (Exception $e) {
        return $e->getMessage();
    }
    return 'success';
}



function mailcow_UnsuspendAccount(array $params)
{
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->activateDomain($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }
    
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->activateDomainAdmin($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }

    return 'success';
}

function mailcow_TerminateAccount(array $params)
{
    try {
      
      //Remove Mailboxes
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeAllMailboxes($params['domain']);
      
      //Remove Resources
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeAllResources($params['domain']);
      
      //Remove Domain
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeDomain($params);
      
      //Remove Domain Admin
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->removeDomainAdmin($params);
      
    } catch (Exception $e) {

        return $e->getMessage();
    }

    return 'success';
}

 
function mailcow_ChangePassword(array $params)
{
    try {
      
      $mailcow = new MailcowAPI($params);
      $result = $mailcow->changePasswordDomainAdmin($params);
      
      logModuleCall(
          'mailcow',
          __FUNCTION__,
          print_r($params, true),
          print_r($result, true),
          null
      );
      
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

function mailcow_ChangePackage(array $params)
{
    try {
        
        $mailcow = new MailcowAPI($params);
        $result = $mailcow->editDomain($params);
        
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            print_r($result, true),
            null
        );
        
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}


function mailcow_TestConnection(array $params)
{
    try {

        $success = true;
        $errorMsg = '';
    } catch (Exception $e) {
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            print_r($params, true),
            $e->getMessage(),
            $e->getTraceAsString()
        );

        $success = false;
        $errorMsg = $e->getMessage();
    }

    return array(
        'success' => $success,
        'error' => $errorMsg,
    );
}

 

function mailcow_AdminLink(array $params)
{
    $address = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
    $secure = ($params["serversecure"]) ? 'https' : 'http';
    if (empty($address)) {
        return '';
    }

    $form = sprintf(
        '<form action="%s://%s/index.php" method="post" target="_blank">' .
        '<input type="hidden" name="login_user" value="%s" />' .
        '<input type="hidden" name="pass_user" value="%s" />' .
        '<input type="submit" value="%s">' .
        '</form>',
        $secure,
        Sanitize::encode($address),
        Sanitize::encode($params["serverusername"]),
        Sanitize::encode($params["serverpassword"]),
        'Login to panel'
    );

    return $form;
}

/**
 * @param $params
 * @return string
 */
function mailcow_ClientArea(array $params) {
  
  $address = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
  $secure = ($params["serversecure"]) ? 'https' : 'http';
  if (empty($address)) {
      return '';
  }

  $form = sprintf(
      '<div class="row">
<div class="col-sm-5 text-right">
<strong>Username</strong>
</div>
<div class="col-sm-7 text-left">
' . $params["username"] . '
</div>
</div>

<div class="row">
<div class="col-sm-5 text-right">
<strong>Password</strong>
</div>
<div class="col-sm-7 text-left">
' . $params["password"] . '
</div>
</div>

<div class="row">
<div class="col-sm-5 text-right">
<strong>Mail Server</strong>
</div>
<div class="col-sm-7 text-left">
<a href="https://' . $address . '" target="_blank">' . $address . '</a>
</div>
</div>
'
  );

  return $form;
    
}

/**
 * Admin Area Client Login link
 */
function mailcow_LoginLink(array $params){ /** Not working Need to use JS to submit form **/
/*
  return "<a href='https://{$params['serverhostname']}/index.php?login_user={$params['username']}&pass_user={$params['password']}' 
    class='btn btn-primary' 
    target='_blank'>
    <i class='fa fa-login'></i> Login as User</a>";
*/
}


/**
 * @param $params
 * @return string
 */
function mailcow_UsageUpdate($params) {

    $query = Capsule::table('tblhosting')
        ->where('server', $params["serverid"]);

    $domains = array();
    /** @var stdClass $hosting */
    foreach ($query->get() as $hosting) {
        $domains[] = $hosting->domain;
    }
    
    try {
      
      $mailcow = new MailcowAPI($params);
      $domainsUsage = $mailcow->getUsageStats($domains);
      
      logModuleCall(
          'mailcow',
          __FUNCTION__,
          $params,
          print_r($domainsUsage, true),
          null
      );
      
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'mailcow',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    
    //logActivity(print_r($domainsUsage, true)); ////DEBUG
    
    foreach ( $domainsUsage as $domainName => $usage ) {

        Capsule::table('tblhosting')
            ->where('server', $params["serverid"])
            ->where('domain', $domainName)
            ->update(
                array(
                    "diskusage" => $usage['diskusage'],
                    "disklimit" => $usage['disklimit'],
                    //"bwusage" => $usage['bwusage'],
                    //"bwlimit" => $usage['bwlimit'],
                    "lastupdate" => Capsule::table('tblhosting')->raw('now()'),
                )
            );
    }

    return 'success';
}
