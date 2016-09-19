# Phi

Phi is an HTTP request router that endeavors to be the perfect balance between the _speed_ of using core PHP functions, and the _convenience_ of using class methods that already incorporate best-practices.

Read the full documentation on the [Phi Wiki](https://github.com/lacockj/phi/wiki)

---

## Example Phi Setup

### index.php (router)
```php
<?php
require( '/lib/Phi/Phi.php' );     // Load
$phi = new \Phi('../etc/phi.ini'); // Configure
$phi->run();                       // Run
```

### phi.ini (configuration)
```
AUTOLOAD_DIR[] = /lib/primary-classes
AUTOLOAD_DIR[] = /php/classes/auxiliary-classes
AUTOLOAD_DIR[] = /../etc/rarely-used-classes
ROUTES_INI = /../etc/routes.ini
SESSION_LIFE = 28800
```

### routes.ini (routes and handlers)
```
/ = Page::get
/@page = Page::get
/api/test = Test::get
/api/test[POST] = Test::post
/api/test/@uri_param = Test::getWithParam
/api/test/@uri_param[PUT] = Test::putWithParam
/old/route[*] = Trouble::giveMoveNotice
```

## Example Handler

```php
<?php class Page {

static function get( $params ){

  // Get specified page, or default to "home.php"
  $page = ( array_key_exists( 'page', $params ) ) ? $params['page'] : "home.php";

  // If page doesn't exist, log the request and respond with "not found"
  if ( ! file_exists( $page ) ) {
    \Phi::log( "IP " . \Phi::ip() . " requested non-existant page " . $page );
    \Phi\Response::status( 404 );
    include "not-found.html";
    return false;
  }

  // For XHR/AJAX requests, respond with partial pages.
  if ( \Phi\Request::isXHR() ) {
    $page = $page . ".part";
  }
  include $page;

  return true;
}
```
