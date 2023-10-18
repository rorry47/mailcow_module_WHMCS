<?php
namespace Mailcow;
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
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_URL, $this->baseurl);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array(
            'X-API-Key: ' . $this->API_KEY,
            'Content-Type: application/json',
            'accept: application/json'
        ));
    }

  /* Domain functions */
  public function addDomain($params){
    return $this->_manageDomain($params['domain'], $params['configoptions'], 'create');
  }
  public function addDkim($params){
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
        $attr["restart_sogo"] = "1";
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
           $data['attr'] = $attr;
           $data = json_encode($data);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseurl . $uri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $this->API_KEY,
        'Content-Type: application/json',
        'accept: application/json'
    ));
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new \Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
  }
  
  
  /* Domain Administrator Functions*/
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
    $uri = '/api/v1/edit/domain-admin';
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
        $data['items'] = $username;
        $uri = '/api/v1/delete/domain-admin';
        break;
        
    }
    
    if ($action == 'create') {
       $data = json_encode($attr); 
    } else {
           $data['attr'] = $attr;
           $data = json_encode($data);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseurl . $uri);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-API-Key: ' . $this->API_KEY,
        'Content-Type: application/json',
        'accept: application/json'
    ));
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new \Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
  }
  
  
  
    public function removeDomainMailbox($params){
        return $this->_removeMailboxes($params['domain'], $params['username'], null, 'remove');
    }
    
    private function _removeMailboxes($domain, $username, $password, $action){
        $uri_m = '/api/v1/get/mailbox/all/' . $domain;
        $context = stream_context_create(array(
            'http' => array(
                'header' => 'X-API-Key: ' . $this->API_KEY
            )
        ));
        $response_list = file_get_contents($this->baseurl . $uri_m, false, $context);
        $data_m = json_decode($response_list, true);
        if ($data_m === null) {
            return 'error';
        }
        $usernames = array();
        foreach ($data_m as $item) {
            $usernames[] = $item['username'];
        }
        
        $data = json_encode($usernames, JSON_PRETTY_PRINT);
        
        $uri = '/api/v1/delete/mailbox/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . $uri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-API-Key: ' . $this->API_KEY,
            'Content-Type: application/json',
            'accept: application/json'
        ));
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);
        return 'error';
    }

    public function removeDomainAliases($params){
        return $this->_removeAliases($params['domain'], $params['username'], null, 'remove');
    }
  
    private function _removeAliases($domain, $username, $password, $action){
        $uri_m = '/api/v1/get/alias/all';
        $context = stream_context_create(array(
            'http' => array(
                'header' => 'X-API-Key: ' . $this->API_KEY
            )
        ));
        $response_list = file_get_contents($this->baseurl . $uri_m, false, $context);
        $data_m = json_decode($response_list, true);
        if ($data_m === null) {
            return 'error';
        }
        $filteredIds = array();
        
        foreach ($data_m as $item) {
            if ($item['domain'] === $domain) {
                $filteredIds[] = $item['id'];
            }
        }
        $data = json_encode($filteredIds, JSON_PRETTY_PRINT);
        $uri = '/api/v1/delete/alias';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . $uri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-API-Key: ' . $this->API_KEY,
            'Content-Type: application/json',
            'accept: application/json'
        ));
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);
        return 'error';
    }






  
  
 
}

?>
