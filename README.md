# phi (&phi;)

Phi endeavors to be the perfect balance between the speed of using core PHP functions, and the convenience of using class methods that already include common best-practices techniques.

## Installation

1. Download Phi.
2. Place the `Phi` folder somewhere accessible to your web server.
  * For example, in the `public_html/lib` folder.
3. Include Phi in your PHP script.
```php
require( '/lib/Phi/Phi.php' );
```

## Configuration

1. Create an initialization file.
  * You can put this file anywhere, but I recommend keeping all your `.ini` files outside the public_html folder, hidden from prying eyes.
  * I saved mine as `phi.ini` in the `etc` folder.
2. Load the `.ini` file in your PHP script after including the Phi library.
```php
require( '/lib/Phi/Phi.php' );
$phi = new \Phi('../etc/phi.ini');
```

Here's an example Phi initialization file.
```php
AUTOLOAD_DIR = /my-php-classes
ROUTES_INI = /../etc/routes.ini
SESSION_LIFE = 28800
```

Phi currently only has three configurable options:

### AUTOLOAD_DIR

The directory in which to look for not-yet-loaded classes.

Phi will "lazy-load" classes, meaning it doesn't load all possibly needed classes every time someone makes an HTTP request, it only loads those classes that are actually used, right before they are called for the first time.

Have classes in more than one directory? No problem! Load an array of directories.
```php
AUTOLOAD_DIR[] = /lib/primary-classes
AUTOLOAD_DIR[] = /php/classes/auxiliary-classes
AUTOLOAD_DIR[] = /../etc/rarely-used-classes
```

Would you like to know more?
* [PHP Manual: parse_ini_file][PHP parse_ini_file]
* [PHP Manual: spl_autoload_register][PHP spl_autoload_register]

### ROUTES_INI

The path and name of the routes initialization file, reletive to the public HTML document root.

What to put in the routes file will be covered a little further down.

### SESSION_LIFE

How long to keep a visitor's session data, in seconds.

Note: This is _not_ a good way to track how long someone can remain logged in because every time they make a request the timer is reset.


[PHP parse_ini_file]: http://php.net/manual/en/function.parse-ini-file.php
[PHP spl_autoload_register]: http://php.net/manual/en/function.spl-autoload-register.php
