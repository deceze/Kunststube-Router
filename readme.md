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

    use Kunststube\Routing\Router;

    require_once 'router.php';

    $r = new Router;

    $r->add('/', array('controller' => 'static', 'action' => 'index'));
    $r->add('/user/profile/:name', array('controller' => 'users', 'action' => 'profile'));
    $r->add('/foo/bar', array(), function (Route $route) {
        header('Location: /bar/baz');
        exit;
    });
    $r->add('/:controller/:action/*');

    $r->defaultCallback(function (Route $route) {
        require_once 'dispatcher.php';
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
        require "{$route->controller}_controller.php";

        $className = ucfirst($route->controller) . 'Controller';
        $controller = new $className;
        $controller->{$route->action}($route->id);
    }

`$route` is the matched route object passed from the router. The above dispatcher loads the file `foos_controller.php`, instantiates a new `FoosController` class, then calls the method `->view(42)` on it. This shows a pretty simple way to load and execute any method of the `FoosController` with arguments when any URL `/foo/...` is being requested.


Reverse Routing
---------------

The above example shows how to route from a URL to a specific class method in a specific file. You typically want to output links in your app somewhere that will lead to this file/class/method again. You could do so by hardcoding all your links:

    <a href="/foo/view/42">See foo number 42</a>

This makes your URL structure rather inflexible though. You may eventually decide to shorten those URLs to `/foos/42`, because that looks better. It's pretty easy to change the routing the accomodate that:

    $r->add('/foo/\d+:id', array('controller' => 'foos', 'action' => 'view'));

The above route will route the URL `/foo/42` to the same `'controller' => 'foos', 'action' => 'view', 'id' => 42`. Your page will still be hardcoded with the URL `/foo/view/42` all over the place. To solve this and keep your URL structure flexible, use reverse routing, which turns canonical dispatcher information and turns it back into URLs:

    printf('<a href="%s">Soo foo number 42</a>', $r->reverseRoute(array('controller' => 'foos', 'action' => 'view', 'id' => 42)));
    
The `Router::reverseRoute` method takes a canonical dispatcher information array and spits out a URL, based on the first of your defined routes that matches it:

    $r = new Router;
    $r->add('/foo',         array('controller' => 'foos', 'action' => 'index'));
    $r->add('/foo/:action', array('controller' => 'foos'));

    echo $r->reverseRoute(array('controller' => 'foos', 'action' => 'index'));
    // /foo

    echo $r->reverseRoute(array('controller' => 'foos', 'action' => 'bar'));
    // /foo/bar
