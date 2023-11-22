# MailCow module for WHMCS
A WHMCS module to automatically integrate with MailCow servers for new API MailCOW. (mailcow API 1.0.0 OAS 3.1)

The module is installed in the folder `modules/servers/`. 

---
**Update 22.11.2023:**
1. Fixed a bug where all mailboxes were deleted when deleting account again.

**Update 20.10.2023:**
1. Mail account creation settings are moved to a separate file
2. All the necessary files are in one directory.

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
I don't setting tariffs etc. because I didn't need it.So, all options, the creation of a domain administrator and domains is written in the file: `config.php` !
Namely: 
```php
  $SET_aliases = 100;
  $SET_MMAILBOXES = 10;
  $SET_MAILBOXQUOTA = 1024;
  $SET_DEFQUOTA = 1024;
  $SET_MAILBOXES = 10240;
  $SET_rl_value = 10;
  $SET_rl_frame = "s";
```

That's all I wanted to say. Good luck. 😉

---

The old module from this repository was taken as a basis: https://github.com/websavers/WHMCS-mailcow
