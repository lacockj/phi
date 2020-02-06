[![Build Status](https://travis-ci.org/brianoflan/phi.svg?branch=master)](https://travis-ci.org/brianoflan/phi)

# Phi

Phi is a fast, easy-to-use PHP framework that endeavors to be the perfect balance between the _speed_ of core PHP functions, the _convenience_ of class methods, and _security_ of coding best-practices. Phi includes methods for request routing, database queries, authentication, and response formatting.

Read the full documentation on the [Phi Wiki](https://github.com/lacockj/phi/wiki)

---

## Request Routing

Have nice, clean URLs!

Turn this: `/api.php?type=user&id=12345`

Into this: `/users/12345`

Clean URLs look better for webpage addresses, and are easier to use and understand for APIs. Phi can extract parameters from the URL for use in your code, like the user ID in the example above.

You create a list of URL patterns and request methods, and map them to whatever function or class method you want to handle the request. When a request matches a listed pattern, the handler function is called, receiving the URL parameters and input data. Input data could be GET query parameters, POST form data, or php://input.

```
/users         [POST]  = Users::createNewUser
/users/@userID [GET]   = Users::getUserByID
/users/@userID [PATCH] = Users::updateUser
```

Phi automatically responds to requests that don't match a URL pattern with the appropriate "404" Not Found status code. Similarly, an unexpected request method automatically gets a "405" Method Not Allowed status code and the `Allow:` response header with a list of the methods you do have in the list, in accordance with [RFC 2616].

---

## Response Formatting

Provide more context to your script's output. In one line of code, you can set the status code, status text, content type, and output your data in the selected format.

```
if ( $myDataArray ) {
  $phi->response->json( $myDataArray );  // Defaults to status 200 "OK"
} else {
  $phi->response->no_content( 204, "No data for the selected resource" );  // Custom status text
}
```

Using the headers and status codes makes it easier for your API's consumers to know when a request succeded or failed, and handle the response appropriately. All this information is already part of the [HTTP definition][RFC 2616], why not use it?

---

## Database Queries

Databases are probably the most useful, and at the same time most troublesome tools in your web service. There is a constant struggle between making each request as fast as possible, and as secure as possible.

The number one best thing you can do to secure your database is use [parameterized queries][PHP mysqli]. But this normally requires a five step process to prepare the query, bind the query parameters, execute the query, bind the result variables, and then fetch and use the results.

With Phi, you can execute a parameterized database query in one line of code. Better still, you get back a result you can iterate like an array.

```
$cities = $phi->db->pq( 'SELECT * FROM `Cities` WHERE `population` > ?', $userInputPopulation );
foreach ( $cities as $city ) {
  ...
}
```

Impact to the script's speed is minimal; the results are not all loaded into memory at once but as they are accessed, which is especially useful for very large result sets. There is no loss of security using Phi's convenient query method, and it greatly improves the code readability.


[RFC 2616]: https://www.w3.org/Protocols/rfc2616/rfc2616.html
[PHP mysqli]: https://php.net/manual/en/mysqli.prepare.php
