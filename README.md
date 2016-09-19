# phi (&phi;)

Phi endeavors to be the perfect balance between the speed of using core PHP functions, and the convenience of using class methods that already include common best-practices techniques.

**[Installation](#installation)**
**[Configuration](#configuration)**
**[Routing](#routing)**

## Installation

1. Download Phi.
2. Place the `Phi` folder somewhere accessible to your web server.
  * For example, in the `public_html/lib` folder.
3. Include Phi in your PHP script.
```php
require( '/lib/Phi/Phi.php' );
```

---

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

#### AUTOLOAD_DIR

The directory in which to look for not-yet-loaded classes.

Phi will "lazy-load" classes, meaning it doesn't load all possibly needed classes every time someone makes an HTTP request. It only loads those classes that are actually used, right before they are called for the first time.

Have classes in more than one directory? No problem! Load an array of directories.
```php
AUTOLOAD_DIR[] = /lib/primary-classes
AUTOLOAD_DIR[] = /php/classes/auxiliary-classes
AUTOLOAD_DIR[] = /../etc/rarely-used-classes
```

#### ROUTES_INI

The path and name of the routes initialization file, reletive to the public HTML document root.

What to put in the routes file will be covered a little further down.

#### SESSION_LIFE

How long to keep a visitor's session data, in seconds.

Note: This is _not_ a good way to track how long someone can remain logged in because every time they make a request the timer is reset.

**Would you like to know more?**
Phi takes advantage of PHP's core functions as much as possible to keep your scripts fast and efficient.
Phi's `.ini` files are read by PHP's [parse_ini_file][PHP parse_ini_file] function.
Class autoloading is configured with PHP's [spl_autoload_register][PHP spl_autoload_register] function.

---

## Routing

Whether you are developing an application program interface (API), or want to provide dynamic content in your web pages based on the requested URL, routing is your friend. Process any number of request patterns through just a few, or even one, central scripts.

Since you're still reading this, I assume you're using PHP, most likely running on Apache or Nginx. To set up routing, you will need to configure four files: the `.htaccess` file, the starting PHP script, the Phi `.ini` file, and a routes `.ini` file.

For this example, let's say we want to route all API requests through a single PHP script, but leave the rest of the website alone.

1. Create a `.htaccess` file in your `public_html` directory.
  * Configure your `.htaccess` to trap incoming requests that match the desired URL pattern(s).
2. Create your primary PHP script, the one that incomming HTTP requests will be sent to.
  * Let's call it `api.php`
  * Follow the [Installation](#installation) and [Configuration](#configuration) notes above.
3. Update the Phi `.ini` file to load the appropriate routes `.ini` file.
  * For this example, I'm going to name the routes file `api-routes.ini`.
3. Create the route definitions `.ini` file.
  * Each line has two parts, the URL pattern on the left, and a "handler" function or static class method on the right.
  * If a URL path segment starts with an `@`, it is considered a parameter and will be saved into an associative array to be passed to the handler as the first argument.
  * In the case of HTTP POST requests, the POST fields will be passed to the handler as the second argument.
  * By default, Phi thinks the route pattern is for an HTTP GET request.
  * To specify a different HTTP method, add it in square brackets `[ ]` after the URL pattern.
  * If a route is meant to handle all HTTP methods, add `[*]` to the end of the URL pattern.

**Example File Structure**
```
etc/
  api-routes.ini
  phi.ini
public_html/
  .htaccess
  api.php
```

**.htaccess**
```
# Use mod_rewrite
RewriteEngine on
RewriteBase /

# API request? Use router.
RewriteRule ^/?api/ /api.php [L]
```

**api.php**
```php
require( '/lib/Phi/Phi.php' );
$phi = new \Phi('../etc/phi.ini');
```

**phi.ini**
```
ROUTES_INI = /../etc/api-routes.ini
```

**api-routes.ini**
```
/api/users = Users::getUserList
/api/users[POST] = Users::createUser
/api/users/@uid = Users::getUserById
/api/users/@uid[POST] = Users::updateUser
/api/users/@uid[DELETE] = Users::deleteUser
/api/users/@uid/records = Records::getRecordsList
/api/users/@uid/records[POST] = Records::createRecord
/api/users/@uid/records/@rid = Records::getRecordById
/api/users/@uid/records/@rid[POST] = Records::updateRecord
/api/users/@uid/records/@rid[DELETE] = Records::deleteRecord
```

**Would you like to know more?**
The `.htaccess` files can do much more than what we're using it for, check out an [htaccess guide].
For some guidance on writing good APIs, follow these [REST API Quick Tips].

---

[PHP parse_ini_file]: http://php.net/manual/en/function.parse-ini-file.php
[PHP spl_autoload_register]: http://php.net/manual/en/function.spl-autoload-register.php
[htaccess guide]: http://www.htaccess-guide.com/
[REST API Quick Tips]: http://www.restapitutorial.com/lessons/restquicktips.html
