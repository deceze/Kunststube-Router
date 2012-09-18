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

    use Kunststube\Router\Router;

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
- a trailing wildcard: `/*`

If a parameter of the pattern contains a `:`, it is a named parameter and will be captured as dispatcher information (see below). The name is specified after the `:`. Preceeding the `:` may be a regular expression. A named parameter without regular expression is basically just shorthand for a parameter with regular expression that matches anything; i.e. `/:foo` is shorthand for `/[^/]+:foo`.

The very last parameter may be a `*`. This will match anything and allows you to specify only the initial part of the URL. Without the wildcard, the pattern will only match URLs of identical length. For example:

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

    function (Route $route) {
        $className = ucfirst($route->controller) . 'Controller';
        
        require_once "MyControllers/$className.php";

        $controller = new $className;
        $controller->{$route->action}($route->id);
    }

`$route` is the matched route object passed from the router. The above dispatcher loads the file `FoosController.php`, instantiates a new `FoosController` class, then calls the method `->view(42)` on it. This shows a pretty simple way to load and execute any method of the `FoosController` with a numeric argument when any URL `/foo/(action)/(id)` is being requested.

Note: nothing is stopping you from defining routes without any parameters in either the pattern or the dispatcher array, resulting in empty dispatcher information. This can be useful for hardcoding certain callbacks to certain routes, though should be avoided for more complex routing/dispatching scenarios.


Reverse Routing
---------------

The above example shows how to route from a URL to a specific class method in a specific file. You typically want to output links in your app somewhere that will lead to this file/class/method again. You could do so by hardcoding all your links:

    <a href="/foo/view/42">See foo number 42</a>

This makes your URL structure rather inflexible though. You may eventually decide to shorten those URLs to `/foos/42`, because that looks better. It's pretty easy to change the routing the accomodate that:

    $r->add('/foo/\d+:id', array('controller' => 'foos', 'action' => 'view'));

The above route will route the URL `/foo/42` to the same `'controller' => 'foos', 'action' => 'view', 'id' => 42`. Your page will still have the hardcoded URL `/foo/view/42` all over the place though. To solve this and keep your URL structure flexible, use reverse routing, which takes canonical dispatcher information and turns it back into URLs:

    $url = $r->reverseRoute(array('controller' => 'foos', 'action' => 'view', 'id' => 42));
    printf('<a href="%s">Soo foo number 42</a>', $url);
    
The `Router::reverseRoute` method takes a canonical dispatcher information array and spits out a URL, based on the first of your defined routes that matches it:

    $r = new Router;
    $r->add('/foo',         array('controller' => 'foos', 'action' => 'index'));
    $r->add('/foo/:action', array('controller' => 'foos'));

    echo $r->reverseRoute(array('controller' => 'foos', 'action' => 'index'));
    // /foo

    echo $r->reverseRoute(array('controller' => 'foos', 'action' => 'bar'));
    // /foo/bar

Reverse routing allows you to flexibly tie your controllers and actions (or whatever other paradigm and organizational structure you prefer) to URLs and vice-versa. The canonical dispatcher information is the middle man that uniquely represents the same thing for both sides (hence *canonical*). Your defined routes turn URLs into dispatcher information through *routing* and dispatcher information into URLs through *reverse routing*.

When reverse routing, regular expressions in the pattern are evaluated against the passed dispatcher information:

    $r->add('/\d+:id',     array('controller' => 'foo', 'action' => 'bar'));
    $r->add('/foo/\w+:id', array('controller' => 'foo', 'action' => 'bar'));

    echo $r->reverseRoute(array('controller' => 'foo', 'action' => 'bar', 'id' => 42));
    // /42

    echo $r->reverseRoute(array('controller' => 'foo', 'action' => 'bar', 'id' => 'baz'));
    // /foo/baz

In the above example, the first route does not reverse match `array('controller' => 'foo', 'action' => 'bar', 'id' => 'baz')`, since `id` is defined as `\d+`, which does not match `'baz'`. The second route matches though.


Wildcard Arguments
------------------

If a routing pattern is defined with a trailing `*`, it allows wildcard arguments, as explained above. These arguments can be either named or unnamed. For example:

    $r->addRoute('/foo/*', array('controller' => 'foos'));
    $r->route('/foo/bar/baz:42')

The resulting dispatcher information will be simply `'controller' => 'foos'`, since no other parameters are specified in the route. The wildcard arguments the dispatcher receives will be `'bar', 'baz' => 42`, or technically `array(0 => 'bar', 'baz' => 42)`. In other words, values passed in `name:value` notation are broken apart and treated as associative key-value pairs.

You should avoid naming conflicts between named parameters and wildcard arguments. Accessing values through `$route->name` always prefers the dispatch information; if an identically named wildcard argument was also set, you have to access it through `$route->wildcardArg('name')`.

When reverse routing, given dispatcher information that contains wildcard arguments, a route will only match if it allows wildcard arguments.

When reverse routing there is no distinction between dispatch information and wildcard arguments, they're all specified in one array. In other words, it is not possible to reverse route with conflicting dispatch/wildcard parameters. Avoid using the same names for two different purposes.

    $r->add('/foo',       array('controller' => 'foos', 'action' => 'index'));
    $r->add('/foo/bar/*', array('controller' => 'foos', 'action' => 'index'));

    $r->reverseRoute(array('controller' => 'foos', 'action' => 'index'));
    // /foo

    $r->reverseRoute(array('controller' => 'foos', 'action' => 'index', 'baz' => '42'));
    // /foo/bar/baz:42

Both routes above have the same dispatcher information for `'controller' => 'foos', 'action' => 'index'`, but only one of them allows wildcard arguments. When reverse routing `array('controller' => 'foos', 'action' => 'index')`, the first route matches and `/foo` is returned. When reverse routing with an additional argument `'baz' => 42`, the first route does not match, but the second does.


Dispatching
-----------

Kunststube\Router does not dispatch, this is entirely up to you to add. Kunststube\Router enables you to specify a callback that will be executed for a matched route. Callbacks can be implemented in any syntax supported by PHP as callable type, including object methods and anonymous functions. A callback could go straight to a controller action, if you wanted it to. Or it could load a separate dispatcher class which implements some logic to load further classes based on the information received through the router. It can also be used to implement redirects.

Couple a static route directly to a class method:

    $r->add('/foo', array(), function () {
        FooController::execute();
    });

This could also be written like so:

    $r->add('/foo', array(), 'FooController::execute');

Redirects are easy to implement:

    $r->add('/foo', array(), function () {
        header('Location: /bar');
        exit;
    });

You can chain your dispatchers with pre-processing logic:

    function dispatch($controller, $action) {
        require "$controller.php";
        $controller::$action();
    }

    $r->add('/foo/:action', array(), function (Route $route) {
        dispatch('bar', $route->action);
    });
    $r->add('/:controller/:action', array(), 'dispatch');

The above basically aliases `/bar/...` to `/foo/...`. This is just for demonstrating the flexibility of callbacks; it could also have been written simpler like so:

    $r->add('/foo/:action', array('controller' => 'bar'), 'dispatch');
    $r->add('/:controller/:action', array(), 'dispatch');

To avoid having to pass the same callback to each of your routes, you can specify a default callback and write the above like so:

    $r->add('/foo/:action', array('controller' => 'bar'));
    $r->add('/:controller/:action');
    $r->defaultCallback('dispatch');


Route matching
--------------

Routes are matched in order from the first route defined to the last one. The first route that matches invokes the associated callback (or the default callback) and stops the routing process. It is important to define your routes in the correct order. For example, the second route here will never be matched, since the first route matches everything:

    $r->add('/*');
    $r->add('/foo');

This is powerful behavior, but also tricky. Generally you should define your specific, narrow routes before the broad catch-all routes.

If no route matched a given URL, a `RuntimeException` is thrown. Alternatively you can pass a callback as second argument to `Router::route`, which will be called in case no URL matched:

    $r->route($_GET['url'], function ($url) {
        die("404: $url not found");
    });

No `RuntimeException` will be thrown in this case.

This gives you several different strategies for dealing with non-matches. You can catch the thrown exception:

    try {
        $r->route($_GET['url']);
    } catch (RuntimeException $e) {
        die($e->getMessage());
    }

This is not recommended, since exceptions are expensive and since a 404 event is not really an exceptional event, but it may tie in well with your existing error handling strategy.

It is usually better to pass a callback to `route()` as shown above. Lastly you can also define a catch-all route as your last route and deal with it:

    // define regular routes here...

    $r->add('/*', array(), 'ErrorHandler::handle404');

A catch-all route has the advantage that the URL will be parsed and your callback receives a regular `Route` object. This is not the case for callbacks passed to `route()`, which will only receive the non-matched URL as string.


Extensions
----------

You can modify and extend the behavior of Kunststube\Router. The most interesting is probably to pass a custom `RouteFactory` to the `Router` constructor. Here an example using a `CaseInsensitiveRoute`:

    require_once 'Kunststube/Router/CaseInsensitiveRouteFactory.php';

    $r = new Router(new CaseInsensitiveRouteFactory);

The bulk of the routing logic resides in the `Route` objects. They are the ones parsing the URLs and matching them both ways. If not otherwise specified, the `Router` uses the `RouteFactory` to create new `Route` objects when you call `Router::add`. The default `Route` objects are strictly case sensitive in their matching. An extension of the `Route` class called `CaseInsensitiveRoute` matches URLs and patterns even if their case differs.

If you do not want all your routes to be case insensitive but only some, you can create a `CaseInsensitiveRoute` yourself and add it to the routing chain:

    require_once 'Kunststube/Router/CaseInsensitiveRoute.php';

    $r = new Router;
    $r->add('/regular/case/sensitive/route');

    $caseInsensitiveRoute = new CaseInsensitiveRoute('/case/insensitive/route');
    $r->addRoute($caseInsensitiveRoute, function () {
        echo 'This will match';
    });

    $r->route('/Case/INSENSITIVE/rOuTe');


The `Route` class
-----------------

The dispatcher callback will be passed an instance of `Route`. The main purpose of this is to give it access to the matched and parsed values. They can be directly accessed as properties of the object:

    function (Route $route) {
        echo $route->controller;
        echo $route->action;
    }

The `Route` object can also be manipulated though and used to generate a new URL according to the set pattern. For example:

    $r = new Route('/foo/:id');
    $r->id = 42;
    echo $r->url();  // /foo/42

This creates a new `Route` object (what is usually done behind the scenes when you call `Router::add`), then sets the missing placeholder `id` to the value `42`, then generates a URL from the set values and the pattern. The values are strictly validated according to the pattern; the following will throw an `InvalidArgumentException`:

    $r = new Route('/foo/\d+:id');
    $r->id = 'bar';  // invalid value for pattern \d+

Wildcard arguments are supported the same way, but only if the route supports wildcard arguments.

This is mainly useful as efficient way to generate a URL for similar routes. Using `Router::reverseRoute`, all routes must be evaluated in order to find the matching route to generate the correct URL. If you already know the pattern of the URL though and just need to change a single value or two to regenerate the URL, doing so on the correct `Route` object is more efficient:

    $r = new Router;
    $r->add('/item/\d+:id', array(), function (Route $route) {
        echo "Now visiting item {$route->id}. ";
        $route->id = $route->id + 1;
        echo "The next item is at " . $route->url();
    });
    $r->route('/item/42');  // Now visiting item 42. The next item is at /item/43

Use this feature with care, since explictly *not* all defined routes are being evaluated and you may get results different from when you'd use reverse routing.

