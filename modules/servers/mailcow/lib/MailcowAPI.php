<?php

namespace Mailcow;

require_once __DIR__ . '/Curl/Curl.php';
require_once __DIR__ . '/Curl/ArrayUtil.php';
require_once __DIR__ . '/Curl/CaseInsensitiveArray.php';

class MailcowAPI{
  private $curl;
  private $cookie;

  public $baseurl;
  public $aliases = 400;
  public $MAILBOXQUOTA = 10240;
  public $UNL_MAILBOXES = 10240;
  
    public function __construct($params) {
        if (!empty($params['serveraccesshash'])) {
            $this->API_KEY = $params['serveraccesshash'];
        } else {
            throw new \Exception('API Key is not provided.');
        }
        $this->baseurl = 'https://' . $params['serverhostname'];
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curl->setHeader('X-API-Key', $this->API_KEY);
        $this->curl->setHeader('Content-Type', 'application/json');
        $this->curl->setHeader('accept', 'application/json');
    }

    public function makeApiRequest($uri, $data) {
        $url = $this->baseurl . $uri;

        $this->curl->post($url, json_encode($data));

        if ($this->curl->error) {
            throw new \Exception('API Request Error: ' . $this->curl->errorMessage);
        }

        return json_decode($this->curl->response);
    }

  
  /**
   * Domain functions
   */
  
  public function addDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'create');
    
  }
  
  public function editDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'edit');
    
  }
  
  public function disableDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'disable');
    
  }
  
  public function activateDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'activate');
    
  }
  
  public function removeDomain($params){
    
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'remove');
      
  }
  
  private function _manageDomain($domain, $product_config, $action){
    
    $attr['description'] = "$domain";
    $attr['aliases'] =  "$this->aliases";
    $attr['mailboxes'] = "10";
    $attr['defquota'] =  "1024";
    $attr['maxquota'] = "$this->MAILBOXQUOTA";
    $attr['quota'] = "$this->MAILBOXQUOTA";
    $attr['backupmx'] =  '0';
    $attr['relay_all_recipients'] =  '0';
    $uri = '/api/v1/edit/domain';
    
    switch ($action){
      case 'create':
        $uri = '/api/v1/add/domain';
        $attr['active'] = "1";
        $attr['domain'] = $domain;
        $attr["restart_sogo"] = "10";
        $attr["tags"] = "$domain";
        break;
      case 'edit':
      case 'disable':
        $data['items'] = $domain;
        $attr['active'] = 0;
        break;
      case 'activate':
        $data['items'] = $domain;
        $attr['active'] = 1;
        break;
      case 'remove':
        $uri = '/api/v1/delete/domain';
        $data['items'] = $domain;
        break;
    }
    if ($action == 'create') {
       $data = json_encode($attr); 
    } else {
           $data['attr'] = $attr; // Используем массив атрибутов напрямую
           $data = json_encode($data);
    }
    
    $this->curl->post($this->baseurl . $uri, $data);
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }

    if ( $action == 'create' && empty($this->curl->error) ){
      $this->_restartSogo(); 
      return $this->curl->response;
    }
    else{
      return $result;
    }
  }
  
  
  /**
   * Domain Administrator Functions
   */
   
   public function addDomainAdmin($params){
    
     return $this->_manageDomainAdmin($params['domain'], $params['username'], $params['password'], 'create');
     
   }
   
   public function editDomainAdmin($params){
     
     return $this->_manageDomainAdmin($domain, $username, $params['password'], 'edit');
     
   }
   
   public function disableDomainAdmin($params){
     
     return $this->_manageDomainAdmin($params['domain'], $params['username'], $params['password'], 'disable');
     
   }
   
   public function activateDomainAdmin($params){
     
     return $this->_manageDomainAdmin($params['domain'], $params['username'], $params['password'], 'activate');
     
   }
   
   public function changePasswordDomainAdmin($params){
     
     return $this->_manageDomainAdmin($params['domain'], $params['username'], $params['password'], 'changepass');
     
   }
   
   public function removeDomainAdmin($params){
     
     return $this->_manageDomainAdmin($params['domain'], $params['username'], null, 'remove');
     
   }
  
  private function _manageDomainAdmin($domain, $username, $password, $action){
        
    $uri = '/api/v1/edit/domain-admin'; //default


    switch ($action){
      
      case 'create':
        $uri = '/api/v1/add/domain-admin';
        $attr['active'] = '1';
        $attr['domains'] = $domain;
        $attr['username'] = $username;
        $attr['password'] = $password;
        $attr['password2'] = $password;
        break;
      case 'edit':
      case 'changepass':
        $data['items'] = array($username);
        $attr['domains'] = array($domain);
        $attr['username_new'] = $username;
        $attr['password'] = $password;
        $attr['password2'] = $password;
        $attr['active'] = '1';
        break;
      case 'disable':
        $data['items'] = array($username);
        $attr['domains'] = array($domain);
        $attr['username_new'] = $username;
        $attr['password'] = $password;
        $attr['password2'] = $password;
        $attr['active'] = 0;
        break; 
      case 'activate':
        $data['items'] = array($username);
        $attr['active'] = 1;
        $attr['username_new'] = $username;
        $attr['password'] = $password;
        $attr['password2'] = $password;
        $attr['domains'] = array($domain);
        break;
      case 'remove':
        $data['items'] = json_encode(array($username));
        $uri = '/api/v1/delete/domain-admin';
        break;
        
    }
    
    if ($action == 'create') {
       $data = json_encode($attr); 
    } else {
           $data['attr'] = $attr; // Используем массив атрибутов напрямую
           $data = json_encode($data);
    }
    
    $this->curl->post($this->baseurl . $uri, $data);
     
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
    return $result;
    
  }
  
  public function removeAllMailboxes($domain){
    
    if ( isset($domain) && !empty($domain) ){
      
      $mailboxes = $this->_getMailboxes();
      
      $mbaddrs = array();
      foreach ($mailboxes as $mbinfo){
        if ( $mbinfo->domain === $domain ){
            array_push($mbaddrs, $mbinfo->username);
        }
      }
      
      if (!empty($mbaddrs)) $this->_removeMailboxes($mbaddrs);
      
    }
    else{
      return "Error: Domain not provided.";
    }
    
  }
  
  public function removeAllResources($domain){
    
    if ( isset($domain) && !empty($domain) ){
      
      $resources_json = $this->_getResources();
      
      $resources = array();
      foreach ($resources_json as $rinfo){
        if ( $rinfo->domain === $domain ){
            array_push($resources, $rinfo->name);
        }
      }
      
      if (!empty($resources)) $this->_removeResources($resources);
      
    }
    else{
      return "Error: Domain not provided.";
    }
    
  }
  
  // Expects array of $mailboxes
  private function _removeMailboxes($mailboxes){
    
    $data = array(
      'items' => json_encode($mailboxes), 
      'csrf_token' => $this->csrf_token,
    );
    
    $uri = '/api/v1/delete/mailbox';
    
    $this->curl->post($this->baseurl . $uri, $data);
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
    
  }
  
  private function _removeResources($resources){
    
    $data = array(
      'items' => json_encode($resources), 
      'csrf_token' => $this->csrf_token,
    );
    
    $uri = '/api/v1/delete/resource';
    
    $this->curl->post($this->baseurl . $uri, $data);
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
    
  }
  
  /* We don't actually use $domains */
  public function getUsageStats($domains){
        
    $usagedata = array();
    
    foreach ($this->_getDomains() as $domain){
      //Init disk usage to 0 and set quota to actual value. 
      $usagedata[$domain->domain_name] = array( 
          'disklimit' => (float) ($domain->max_quota_for_domain / (1024*1024)), 
          'diskusage' => 0,
      );
    }

    foreach ($this->_getMailboxes() as $mailbox){
      //Increase disk usage for domain by this mailboxes' usage
      $usagedata[$mailbox->domain]['diskusage'] += (float) ($mailbox->quota_used / (1024*1024));
    }
    
    //logActivity( print_r($usagedata, true) ); ///DEBUG
    
    return $usagedata;
    
  }
  
  private function _getDomains(){
    
    $uri = '/api/v1/get/domain/all';
    $data = array();
    
    $this->curl->get( $this->baseurl . $uri, $data );
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
    
    return $result;
    
  }
  
  private function _getMailboxes(){
    
    $uri = '/api/v1/get/mailbox/all';
    $data = array();
    
    $this->curl->get( $this->baseurl . $uri, $data );
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
    
    return $result;
    
  }
  
  private function _getResources(){
    
    $uri = '/api/v1/get/resource/all';
    $data = array();
    
    $this->curl->get( $this->baseurl . $uri, $data );
    
    try{
      $result = $this->error_checking($uri, $data); 
    }
    catch (Exception $e) {
      return $e->getMessage();
    }
    
    return $result; 
    
  }
  
  private function _restartSogo(){
    
    $this->curl->get( $this->baseurl . '/inc/call_sogo_ctrl.php', array('ACTION' => 'stop') );
    
    if ($this->curl->error) {
      
      return array( 
        'error' => $this->curl->errorCode,
        'error_message' => $this->curl->errorMessage,
      );
      
    } else {
      
      $this->curl->get( $this->baseurl . '/inc/call_sogo_ctrl.php', array('ACTION' => 'start') );
      
      if ($this->curl->error) {
        
        return array( 
          'error' => $this->curl->errorCode,
          'error_message' => $this->curl->errorMessage,
        );
        
      } else {
                
        return $this->curl->response;
        
      }
      
    }
    
  }
  
  private function error_checking($action = null, $data_sent = null){
    
    // first check for standard HTTP errors like 404
    if ($this->curl->error){
      throw new \Exception($this->curl->errorCode . ': ' . $this->curl->errorMessage);
    } 
    // then check for mailcow errors
    else {
      $json = $this->curl->response;
      
      if ($action !== 'init'){ //silence initial connection logging
        logModuleCall(
            'mailcow',
            $action,
            print_r($data_sent, true),
            print_r($json, true),
            null
        );
      }

      if ($json->type == "error" || $json->type == "danger"){
        throw new \Exception($json->msg);
      }
    }
    
    return $this->curl->response;
    
  }
  
  private function getToken($html){

    
  }
  
}

?>
