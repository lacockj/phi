# Phi

Phi is a fast, easy-to-use PHP framework that endeavors to be the perfect balance between the _speed_ of core PHP functions, the _convenience_ of class methods, and _security_ of coding best-practices. Phi includes methods for request routing, database queries, authentication, and response formatting.

Read the full documentation on the [Phi Wiki](https://github.com/lacockj/phi/wiki)

---

## Request Routing

Have nice, clean URLs!

Turn this: `/api.php?type=user&id=12345`

Into this: `/users/12345`

Clean URLs look better for webpage addresses, and are easier to use and understand for APIs. Phi can extract parameters from the URL for use in your code, like the user ID in the example above.

You create a list of URL patterns and request methods, and map them to whatever function or class method you want to handle the request. When a request matches a listed patter, the handler function is called, passing along the URL parameters and any other input.

```
/users         [POST]  = Users::createNewUser
/users/@userID [GET]   = Users::getUserByID
/users/@userID [PATCH] = Users::updateUser
```

Phi automatically responds to requests that don't match a URL pattern with the appropriate "404" status code. Similarly, an unexpected request method automatically gets a "405" Method Not Allowed status code and the "Allow:" response header with a list of the methods you do have in the list, in accordance with [RFC 2616].

[RFC 2616]: https://www.w3.org/Protocols/rfc2616/rfc2616.html
