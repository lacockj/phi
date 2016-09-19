# Phi

Phi is an HTTP request router that endeavors to be the perfect balance between the speed of using core PHP functions, and the convenience of using class methods that already incorporate best-practices techniques.

Read the full documentation on the [Phi Wiki](https://github.com/lacockj/phi/wiki)

---

### Example Router Script
```php
// my-first-router.php
require( '/lib/Phi/Phi.php' );     // Load
$phi = new \Phi('../etc/phi.ini'); // Configure
$phi->run();                       // Run
```

### Example Config File
```
AUTOLOAD_DIR[] = /lib/primary-classes
AUTOLOAD_DIR[] = /php/classes/auxiliary-classes
AUTOLOAD_DIR[] = /../etc/rarely-used-classes
ROUTES_INI = /../etc/routes.ini
SESSION_LIFE = 28800
```

### Example Routes
```
/ = getHomePage
/@page = getPage
/api/test = Test::get
/api/test[POST] = Test::post
/api/test/@uri_param = Test::getWithParam
/api/test/@uri_param[PUT] = Test::putWithParam
/old/route[*] = giveMoveNotice
```
