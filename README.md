# MailCow module for WHMCS
A WHMCS module to automatically integrate with MailCow servers for new API MailCOW. (mailcow API 1.0.0 OAS 3.1)

The module is installed in the folder `modules/servers/`. 

---

**Update 18.10.2023:**
1. The Curl library is disabled.
2. The client can see DNS records in the order details.
3. The client can independently create a DKIM record.
4. When deleting an order, the account, domain, mailboxes and aliases are deleted.

---

# *ATTENTION!*
You do not need to create an administrator in MailCow, just generate an API key with read and write rights and register it in the server settings. This will be enough for the module to work.

---

# Important nuances
I don't setting tariffs etc. because I didn't need it.So, all options, the creation of a domain administrator and domains is written in the file: `/lib/MailcowAPI.php` !
Namely: 
```php
  public $aliases = 400;
  public $MAILBOXQUOTA = 10240;
  public $UNL_MAILBOXES = 10240;
...
    $attr['mailboxes'] = "10"; // Maximum mailboxes
    $attr['defquota'] =  "1024"; //Default quota
    $attr['backupmx'] =  '0'; // Backups... i down know :) 
    $attr['relay_all_recipients'] =  '0'; // 

```

That's all I wanted to say. Good luck. 😉

---

The old module from this repository was taken as a basis: https://github.com/websavers/WHMCS-mailcow
