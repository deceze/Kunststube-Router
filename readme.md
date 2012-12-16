Kunststube\Router
=================

Introduction
------------

The Kunststube\Router is a simple and flexible router and reverse router. Nothing more, nothing less.

Routing is the very first step in your application. It decides how to get from a URL to a piece of executable code. In the Kunststube\Router, there are three parts to this process:

1. the URL
2. the dispatcher information
3. the dispatcher

Kunststube\Router deals with the first two only. It allows you to specify rules to transform a URL into dispatcher information, which is called "routing". It also allows you to do the reverse, transforming dispatcher information into a URL according to your set up rules. This is called "reverse routing". Finally it enables you to hook in any dispatcher you want, but it does not itself deal with dispatching.


Usage example
-------------

```php
use Kunststube\Router\Router,
    Kunststube\Router\Route;

require_once 'Kunststube/Router/Router.php';

$r = new Router;

$r->add('/', array('controller' => 'static', 'action' => 'index'));
$r->add('/user/profile/:name', array('controller' => 'users', 'action' => 'profile'));
$r->add('/foo/bar', array(), function (Route $route) {
    header('Location: /bar/baz');
    exit;
});
$r->add('/:controller/:action/*');

$r->defaultCallback(function (Route $route) {
    require_once 'MyDispatcher.php';
    $dispatcher = new Dispatcher;
    $dispatcher->dispatch($route);
});

$r->route($_GET['url']);
```

You add routes to the router using `Router::add`. This method takes three arguments:

1. a pattern
2. a dispatcher information array
3. a dispatcher callback

Only the pattern is mandatory, the dispatcher information and the dispatcher callback are optional. See below for details on how the pattern and the dispatcher information play together. If no dispatcher callback is supplied for a specific route, the default callback set with `Router::defaultCallback` will be called. The callback gets passed a `Route` object of the matched route. The actual routing process is started using `Route::route`, to which the URL that should be routed is passed.


The Pattern
-----------

The first and defining argument for a route is the pattern. The pattern is matched against a URL. The following syntax is supported:

- literals: `/foo`
- named parameters: `/:bar`
- named parameters with regular expression: `/\d+:baz`
- a trailing wild card: `/*`

If a parameter of the pattern contains a `:`, it is a named parameter and will be captured as dispatcher information (see below). The name is specified after the `:`. Preceding the `:` may be a regular expression. A named parameter without regular expression is basically just shorthand for a parameter with regular expression that matches anything; i.e. `/:foo` is shorthand for `/[^/]+:foo`.

The very last parameter may be a `*`. This will match anything and allows you to specify only the initial part of the URL. Without the wild card, the pattern will only match URLs of identical length. For example:

- `/foo/:bar` will *not* match the URL `/foo/bar/baz`
- `/foo/:bar/*` *will* match the URL `/foo/bar/baz`

No other syntax is supported. Regular expressions should be kept simple and must not contain a `/`, otherwise you may see weird results. Some typical expressions you may want to use:

- any number: `\d+`
- any "word": `\w+`
- "word" starting with capital letter: `[A-Z]\w*`
- a "word" followed by a number: `\w+\d+`

Unnamed regular expression parameters are not supported; every dynamically matched parameter must be captured as dispatcher information. Otherwise reverse routing reliably is rather difficult.


The Dispatcher Information array
--------------------------------

The second part of the route is the dispatcher information. This sets defaults for the dispatcher. It is an entirely arbitrary array of arbitrary information that will be passed to the dispatcher later. The dispatcher can decide what to do with it. There is no predefined structure for the dispatcher information. It can contain associative key-values and numerically indexed values.


The Canonical Dispatcher Information
------------------------------------

Named parameters from the pattern are considered part of the dispatcher information. When a URL is matched, all named parameters are added to the dispatcher information. For example:

1. the route:

        $r->add('/foo/:action/\d+:id', array('controller' => 'foos'));

2. the URL:

        /foo/view/42

3. the dispatcher receives:

        'controller' => 'foos', 'action' => 'view', 'id' => 42

Any named parameters from the pattern are passed to the dispatcher (`action` and `id`). The default dispatcher information defined by the route (`controller => foos`) is merged together with the named parameters. Together they form the canonical dispatcher information for the route (`action`, `id` and `controller => foos`). The canonical dispatcher information should be thought of as the primary "id" of some controller/action in your app. This allows flexible reverse routing. For example, we'll assume a dispatcher like this:

```php
function (Route $route) {
    $className = ucfirst($route->controller) . 'Controller';
        
    require_once "MyControllers/$className.php";

    $controller = new $className;
    $controller->{$route->action}($route->id);
}
```

`$route` is the matched route object passed from the router. The above dispatcher loads the file `FoosController.php`, instantiates a new `FoosController` class, then calls the method `->view(42)` on it. This shows a pretty simple way to load and execute any method of the `FoosController` with a numeric argument when any URL `/foo/(action)/(id)` is being requested.

Note: nothing is stopping you from defining routes without any parameters in either the pattern or the dispatcher array, resulting in empty dispatcher information. This can be useful for hard-coding certain callbacks to certain routes, though should be avoided for more complex routing/dispatching scenarios.


Reverse Routing
---------------

The above example shows how to route from a URL to a specific class method in a specific file. You typically want to output links in your app somewhere that will lead to this file/class/method again. You could do so by hard-coding all your links:

```html
<a href="/foo/view/42">See foo number 42</a>
```

This makes your URL structure rather inflexible though. You may eventually decide to shorten those URLs to `/foos/42`, because that looks better. It's pretty easy to change the routing to accommodate that:

```php
$r->add('/foo/\d+:id', array('controller' => 'foos', 'action' => 'view'));
```

The above route will route the URL `/foo/42` to the same `'controller' => 'foos', 'action' => 'view', 'id' => 42`. Your page will still have the hard-coded URL `/foo/view/42` all over the place though. To solve this and keep your URL structure flexible, use reverse routing, which takes canonical dispatcher information and turns it back into URLs:

```php
$url = $r->reverseRoute(array('controller' => 'foos', 'action' => 'view', 'id' => 42));
printf('<a href="%s">See foo number 42</a>', $url);
```
    
The `Router::reverseRoute` method takes a canonical dispatcher information array and spits out a URL, based on the first of your defined routes that matches it:

```php
$r = new Router;
$r->add('/foo',         array('controller' => 'foos', 'action' => 'index'));
$r->add('/foo/:action', array('controller' => 'foos'));

echo $r->reverseRoute(array('controller' => 'foos', 'action' => 'index'));
// /foo

echo $r->reverseRoute(array('controller' => 'foos', 'action' => 'bar'));
// /foo/bar
```

Reverse routing allows you to flexibly tie your controllers and actions (or whatever other paradigm and organizational structure you prefer) to URLs and vice-versa. The canonical dispatcher information is the middle man that uniquely represents the same thing for both sides (hence *canonical*). Your defined routes turn URLs into dispatcher information through *routing* and dispatcher information into URLs through *reverse routing*.

When reverse routing, regular expressions in the pattern are evaluated against the passed dispatcher information:

```php
$r->add('/\d+:id',     array('controller' => 'foo', 'action' => 'bar'));
$r->add('/foo/\w+:id', array('controller' => 'foo', 'action' => 'bar'));

echo $r->reverseRoute(array('controller' => 'foo', 'action' => 'bar', 'id' => 42));
// /42

echo $r->reverseRoute(array('controller' => 'foo', 'action' => 'bar', 'id' => 'baz'));
// /foo/baz
```

In the above example, the first route does not reverse match `array('controller' => 'foo', 'action' => 'bar', 'id' => 'baz')`, since `id` is defined as `\d+`, which does not match `'baz'`. The second route matches though.


Wild-card Arguments
-------------------

If a routing pattern is defined with a trailing `*`, it allows wild-card arguments, as explained above. These arguments can be either named or unnamed. For example:

```php
$r->addRoute('/foo/*', array('controller' => 'foos'));
$r->route('/foo/bar/baz:42')
```

The resulting dispatcher information will be simply `'controller' => 'foos'`, since no other parameters are specified in the route. The wild-card arguments the dispatcher receives will be `'bar', 'baz' => 42`, or technically `array(0 => 'bar', 'baz' => 42)`. In other words, values passed in `name:value` notation are broken apart and treated as associative key-value pairs.

You should avoid naming conflicts between named parameters and wild-card arguments. Accessing values through `$route->name` always prefers the dispatch information; if an identically named wild-card argument was also set, you have to access it through `$route->wildcardArg('name')`.

When reverse routing, given dispatcher information that contains wild-card arguments, a route will only match if it allows wild-card arguments.

When reverse routing there is no distinction between dispatch information and wild-card arguments, they're all specified in one array. In other words, it is not possible to reverse route with conflicting dispatch/wild-card parameters. Avoid using the same names for two different purposes.

```php
$r->add('/foo',       array('controller' => 'foos', 'action' => 'index'));
$r->add('/foo/bar/*', array('controller' => 'foos', 'action' => 'index'));

$r->reverseRoute(array('controller' => 'foos', 'action' => 'index'));
// /foo

$r->reverseRoute(array('controller' => 'foos', 'action' => 'index', 'baz' => '42'));
// /foo/bar/baz:42
```

Both routes above have the same dispatcher information for `'controller' => 'foos', 'action' => 'index'`, but only one of them allows wild-card arguments. When reverse routing `array('controller' => 'foos', 'action' => 'index')`, the first route matches and `/foo` is returned. When reverse routing with an additional argument `'baz' => 42`, the first route does not match, but the second does.


Dispatching
-----------

Kunststube\Router does not dispatch, this is entirely up to you to add. Kunststube\Router enables you to specify a callback that will be executed for a matched route. Callbacks can be implemented in any syntax supported by PHP as callable type, including object methods and anonymous functions. A callback could go straight to a controller action, if you wanted it to. Or it could load a separate dispatcher class which implements some logic to load further classes based on the information received through the router. It can also be used to implement redirects.

Couple a static route directly to a class method:

```php
$r->add('/foo', array(), function () {
    FooController::execute();
});
```

This could also be written like so:

```php
$r->add('/foo', array(), 'FooController::execute');
```

Redirects are easy to implement:

```php
$r->add('/foo', array(), function () {
    header('Location: /bar');
    exit;
});
```

You can chain your dispatchers with preprocessing logic:

```php
function dispatch(Route $route) {
    $controller = $route->controller;
    require "$controller.php";
    $controller::{$route->action}();
}

$r->add('/foo/:action', array(), function (Route $route) {
    $route->controller = 'bar';
    dispatch($route);
});
$r->add('/:controller/:action', array(), 'dispatch');
```

The above basically aliases `/bar/...` to `/foo/...`. This is just for demonstrating the flexibility of callbacks; it could also have been written simpler like so:

```php
$r->add('/foo/:action', array('controller' => 'bar'), 'dispatch');
$r->add('/:controller/:action', array(), 'dispatch');
```

To avoid having to pass the same callback to each of your routes, you can specify a default callback and write the above like so:

```php
$r->add('/foo/:action', array('controller' => 'bar'));
$r->add('/:controller/:action');
$r->defaultCallback('dispatch');
```


RESTful routing (routing by request method)
-------------------------------------------

For more fine-grained routing the router allows specific HTTP request methods to be specified for each route. This is done using the following API methods:

- `addGet(string $pattern [, array $dispatch [, callable $callback ] ])`
- `addPost(string $pattern [, array $dispatch [, callable $callback ] ])`
- `addPut(string $pattern [, array $dispatch [, callable $callback ] ])`
- `addDelete(string $pattern [, array $dispatch [, callable $callback ] ])`
- `addMethod(int $method, string $pattern [, array $dispatch [, callable $callback ] ])`
- `addMethodRoute(int $method, Route $route [, callable $callback ])`

The `Router::addMethodRoute` method is the canonical method to add all routes, the other methods are merely convenience wrappers around it. The `$method` is a bitmask of the following constants:

- `Router::GET`
- `Router::POST`
- `Router::PUT`
- `Router::DELETE`
- `Router::HEAD`
- `Router::TRACE`
- `Router::OPTIONS`
- `Router::CONNECT`

The first four HTTP request methods have their own convenience wrapper (`addGet` etc.), the more uncommon methods `HEAD`, `TRACE`, `OPTIONS` and `CONNECT` can be used using the `addMethod` and `addMethodRoute` methods. Several methods can be combined by bitwise-ORing them. Using the `Router::add` and `Router::addRoute` methods defaults to adding the route matching `GET`, `POST`, `PUT` and `DELETE` requests. Examples:

```php
$r = new Router;

// matches GET, POST, PUT and DELETE requests
$r->add('/foo');

// matches only GET requests
$r->addGet('/bar');

// matches the same URL as above, but only for POST requests
$r->addPost('/bar');

// matches PUT and POST requests
$r->addMethod($r::PUT | $r::POST, '/baz');

// custom route matching only HEAD requests
$r->addMethodRoute($r::HEAD, new CaseInsensitiveRoute('/42'));
```

To initiate request method-specific routing, use `Router::routeMethod` or `Router::routeMethodFromString`. The former method requires one of the constants to be passed as argument while the latter accepts the request method as string:

```php
$r->routeMethod($r::POST, $_GET['url']);

$r->routeMethodFromString('POST', $_GET['url']);
$r->routeMethodFromString($_SERVER['REQUEST_METHOD'], $_GET['url']);
```

The normal `Router::route` method handles the request as if it was a GET, POST, PUT *or* DELETE and does not differentiate between them. I.e. it's a convenience wrapper around `Router::routeMethod(Router::GET | Router::POST | Router::PUT | Router::DELETE, $url)`. The router does not detect the current request method itself, it must be passed to one of the routing methods explictly.

The request method is handled by the `Router` class, not by the `Route` class (see below). The `Route` object passed to the callback contains no information about the request method. You should include an appropriate parameter in the dispatcher array or pass the request method along to the dispatcher.


Route matching
--------------

Routes are matched in order from the first route defined to the last one. The first route that matches invokes the associated callback (or the default callback) and stops the routing process. It is important to define your routes in the correct order. For example, the second route here will never be matched, since the first route matches everything:

```php
$r->add('/*');
$r->add('/foo');
```

This is powerful behavior, but also tricky. Generally you should define your specific, narrow routes before the broad catch-all routes.

If no route matched a given URL, a `RuntimeException` is thrown. Alternatively you can pass a callback as second argument to `Router::route`, which will be called in case no URL matched:

```php
$r->route($_GET['url'], function ($url) {
    die("404: $url not found");
});
```

No `RuntimeException` will be thrown in this case.

This gives you several different strategies for dealing with non-matches. You can catch the thrown exception:

```php
try {
    $r->route($_GET['url']);
} catch (RuntimeException $e) {
    die($e->getMessage());
}
```

This is not recommended, since exceptions are expensive and since a 404 event is not really an exceptional event, but it may tie in well with your existing error handling strategy.

It is usually better to pass a callback to `route()` as shown above. Lastly you can also define a catch-all route as your last route and deal with it:

```php
// define regular routes here...

$r->add('/*', array(), 'ErrorHandler::handle404');
```

A catch-all route has the advantage that the URL will be parsed and your callback receives a regular `Route` object. This is not the case for callbacks passed to `route()`, which will only receive the non-matched URL as string.


What URLs are and how to set up routing
---------------------------------------

A URL is simply a string consisting of several parts:

    http://example.com/foo?bar=baz
      |         |       |    |
      |         |       |    +- query
      |         |       +- path
      |         +- host
      +- scheme

A URL may additionally have authentication information, a port and a fragment, but we'll try to keep it simple here. Kunststube\Router exclusively deals with the path. The scheme and query typically have no influence on routing and the host is typically handled by the web server.

Assuming a typical setup using an Apache web server, Apache usually does the "routing" for you. It receives an HTTP request looking something like this:

    GET /foo/bar/baz?some=parameters HTTP/1.1

The web server is now free to respond to this request in any way it chooses. The default thing most web servers do is to map the URL's path to files on the hard disk. The web server will first figure out what the appropriate *DocumentRoot* is, i.e. the folder on disk that has been configured as "the public web folder". Let's assume the DocumentRoot is `/var/www`. It will then concatenate the request path to that root, resulting in `/var/www/foo/bar/baz`. It will then try to figure out if that file exists on disk and serve it up as response. If the requested file ends with `.php` or Apache is otherwise configured to treat the file as PHP file, it will first run the file through the PHP interpreter before returning its output.

To use our own custom routing using a PHP router, we need to intercept the process of Apache looking up the file to serve on disk. This can be done in the Apache configuration files; but if you have access to these files I'm assuming you know what you're doing and won't go into the specific details of the best setup there. Instead I'll cover the typical case where you cannot or don't want to edit the core Apache configuration files and instead resort to `.htaccess` files. When Apache traverses the directory structure on the disk to find the correct file to serve, it checks in each directory whether a file called `.htaccess` is placed in it. If it finds one, it will execute and/or incorporate the rules defined within it into the file lookup process, then continue on to the next deeper directory in the path.

What you want to achieve is to make Apache "find" and execute one particular PHP file for any and all requests and make the original URL available to that PHP file so it can do its own routing. The easiest, dirtiest way to do this is a simple `RewriteRule`:

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteRule ^(.*)$ index.php?url=/$1 [QSA,L] 
    </IfModule>

Lets assume you put this into the file `/var/www/.htaccess`. When Apache starts its file lookup in that directory, it will parse these rewrite rules. The "internal state" of the path Apache is looking for at this point is `foo/bar/baz`. The regular expression `^(.*)$` of the `RewriteRule` will match that path (the expression basically says "match anything"), and the rule will rewrite the path to `index.php?url=/foo/bar/baz`. The original `some=parameters` is then appended again to that path/URL (due to the `QSA` flag). Apache will then continue looking for the now rewritten path `index.php`. So just put your code into `/var/www/index.php` and Apache will launch the PHP interpreter for it. PHP will be passed the URL query part `?url=/foo/bar/baz&some=parameters`, which in PHP can be accessed as `$_GET['url']` and `$_GET['some']`. So the complete setup looks like this:

### Basic setup ###

#### File/folder structure ####

    /var
        /www
            .htaccess
            index.php
            /Kunststube
                /Router
                    Router.php
                    ...

#### .htaccess ####

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteRule ^(.*)$ index.php?url=/$1 [QSA,L] 
    </IfModule>

#### index.php ####

```php
<?php

require_once 'Kunststube/Router/Router.php';

$r = new Kunststube\Router\Router;
$r->add('/foo');
...
$r->route($_GET['url']);
```

And that's all there is to it. A basic rewrite rule that redirects every request to the same PHP file and appends the original URL as query parameter, which is then used to invoke the routing process.

### Caveats and tweaks ###

One important caveat to the above setup is that your URL cannot contain a query parameter called `url`, since the original query parameters are added back onto the rewritten URL. The URL `/foo/bar?url=baz` would be rewritten to:

    index.php?url=/foo/bar&url=baz

The second `url` parameter will replace the first. If you need to use the query parameter `url` in your application, choose a different parameter name for your rewrite rule.

Secondly, note that the Kunststube\Router expects the URL passed to `route()` to start with a `/`. You can add that slash during the rewriting process as shown above, or in PHP; just make sure it's there.

Third, you usually also have files you do not want to route through PHP, for example CSS and image files. You'll want those to be served by Apache directly. A good setup for this is as such:

    /var
        /Kunststube
            /Router
                ...
        /MyApp
            MyScript.php
            ...
        /www
            .htaccess
            index.php
            /css
                style.css
                ...
            /img
                kitten.jpg
                ...

Take all the actual PHP files out of the public web root directory, only leave public asset files and a minimal `index.php` file in there. Adjust your RewriteRule to look like this:

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php?url=/$1 [QSA,L] 
    </IfModule>

The `RewriteCond` makes sure the `RewriteRule` only applies if the requested file does not physically exist (`!-f`). That means requests for the URL `css/style.css` will pass through as is, since the file does actually exist and Apache can serve it directly. Any requests for "imaginary" files that do not physically exist will go into `index.php` and can be routed there. Inside `index.php`, make sure to use the correct path to the router:

```php
require_once '../Kunststube/Router/Router.php';
```

In fact, it's better practice to define your routes elsewhere entirely and to use autoloaders to load required files, but this is outside the scope of this document.

### Integration ###

Given that Kunststube\Router only deals with the path, it is but a small part in your bigger application. If you require logic based on the scheme, host or query parameters, you will have to handle them separately. All this information is accessible in PHP through the `$_SERVER` super-global. You could easily customize routes depending on the host name for instance:

```php
$r = new Kunststube\Router\Router;

switch ($_SERVER['HTTP_HOST']) {
    case 'example.com' :
        $r->add('/foo');
        ...
        break;

    case 'elpmaxe.moc' :
        $r->add('/bar');
        ...
        break;
}

$r->route($_GET['url']);
```

For assembling reverse-routed URLs, it's advisable to create a wrapper function that will assemble complete URLs and only uses Kunststube\Router to generate the path component, but adds query parameters and possibly the host and scheme around that.

To pass the router instance around to use it for reverse routing later, there are several ways to do so, but I'd recommend closures:

```php
$r = new Kunststube\Router\Router;

$r->add('/foo', array(), function (Route $route) use ($r) {
    $controller = new MyController;
    $controller->run($r);
});
```

This neatly injects the router instance further down into your call stack. Global variables, registries, wrapper objects that abstract the whole routing process etc. are other options you may consider.


Extensions
----------

You can modify and extend the behavior of Kunststube\Router. The most interesting is probably to pass a custom `RouteFactory` to the `Router` constructor. Here an example using a `CaseInsensitiveRoute`:

```php
require_once 'Kunststube/Router/CaseInsensitiveRouteFactory.php';

$r = new Router(new CaseInsensitiveRouteFactory);
```

The bulk of the routing logic resides in the `Route` objects. They are the ones parsing the URLs and matching them both ways. If not otherwise specified, the `Router` uses the `RouteFactory` to create new `Route` objects when you call `Router::add`. The default `Route` objects are strictly case sensitive in their matching. An extension of the `Route` class called `CaseInsensitiveRoute` matches URLs and patterns even if their case differs.

If you do not want all your routes to be case insensitive but only some, you can create a `CaseInsensitiveRoute` yourself and add it to the routing chain:

```php
require_once 'Kunststube/Router/CaseInsensitiveRoute.php';

$r = new Router;
$r->add('/regular/case/sensitive/route');

$caseInsensitiveRoute = new CaseInsensitiveRoute('/case/insensitive/route');
$r->addRoute($caseInsensitiveRoute, function () {
    echo 'This will match';
});

$r->route('/Case/INSENSITIVE/rOuTe');
```


The `Route` class
-----------------

The dispatcher callback will be passed an instance of `Route`. The main purpose of this is to give it access to the matched and parsed values. They can be directly accessed as properties of the object:

```php
function (Route $route) {
    echo $route->controller;
    echo $route->action;
}
```

The `Route` object can also be manipulated though and used to generate a new URL according to the set pattern. For example:

```php
$r = new Route('/foo/:id');
$r->id = 42;
echo $r->url();  // /foo/42
```

This creates a new `Route` object (what is usually done behind the scenes when you call `Router::add`), then sets the missing placeholder `id` to the value `42`, then generates a URL from the set values and the pattern. The values are strictly validated according to the pattern; the following will throw an `InvalidArgumentException`:

```php
$r = new Route('/foo/\d+:id');
$r->id = 'bar';  // invalid value for pattern \d+
```

Wild-card arguments are supported the same way, but only if the route supports wild-card arguments.

This is mainly useful as efficient way to generate a URL for similar routes. Using `Router::reverseRoute`, all routes must be evaluated in order to find the matching route to generate the correct URL. If you already know the pattern of the URL though and just need to change a single value or two to regenerate the URL, doing so on the correct `Route` object is more efficient:

```php
$r = new Router;
$r->add('/item/\d+:id', array(), function (Route $route) {
    echo "Now visiting item {$route->id}. ";
    $route->id = $route->id + 1;
    echo "The next item is at " . $route->url();
});
$r->route('/item/42');  // Now visiting item 42. The next item is at /item/43
```

Use this feature with care, since explicitly *not* all defined routes are being evaluated and you may get results different from when you'd use reverse routing.


PSR-0
-----

The repository is organized so its contents can be dumped into a folder `Kunststube/Router/` and the naming be PSR-0 compliant.


Information
-----------

Version: 0.2  
Author:  David Zentgraf  
Contact: router@kunststube.net  
Web:     http://kunststube.net, https://github.com/deceze/Kunststube-Router


Version history
---------------

### 0.2

Added APIs for routing by request method.

### 0.1

Initial release.


Disclaimer
----------

The code is provided as is. Feel free to use it for anything you like. No warranty about anything.  
Currently just putting it out there, proper license may be applied in the future.
