<?php

namespace Kunststube\Router;

use InvalidArgumentException,
	RuntimeException;


class Router {

	protected $routes = array(),
	          $routeFactory,
	          $defaultCallback;

	/**
	 * @param RouteFactory $routeFactory Optionally supply an instance of a RouteFactory
	 *	that may instantiate alternative Route objects. Defaults to standard RouteFactory.
	 */
	public function __construct(RouteFactory $routeFactory = null) {
		if (!$routeFactory) {
			require_once __DIR__ . DIRECTORY_SEPARATOR . 'RouteFactory.php';
			$routeFactory = new RouteFactory;
		}
		$this->routeFactory = $routeFactory;
	}

	/**
	 * Create a new route using a pattern, dispatch array and callback and add it to the stack.
	 *
	 * @param string $pattern
	 * @param array $dispatch
	 * @param callable $callback
	 */
	public function add($pattern, array $dispatch = array(), $callback = null) {
		$this->addRoute($this->routeFactory->newRoute($pattern, $dispatch), $callback);
	}

	/**
	 * Add a Route object and a callback to the stack.
	 * 
	 * @param Route $route
	 * @param callable $callback
	 * @throws InvalidArgumentException if $callback is not callable
	 */
	public function addRoute(Route $route, $callback = null) {
		if ($callback && !is_callable($callback, true)) {
			throw new InvalidArgumentException('$callback must be of type callable, got ' . gettype($callback));
		}
		$this->routes[] = compact('route', 'callback');
	}

	/**
	 * Set a default callback for routes that have no specific callback defined.
	 *
	 * @param callable $callback
	 * @throws InvalidArgumentException if $callback is not callable
	 */
	public function defaultCallback($callback) {
		if ($callback && !is_callable($callback, true)) {
			throw new InvalidArgumentException('$callback must be of type callable, got ' . gettype($callback));
		}
		$this->defaultCallback = $callback;
	}

	/**
	 * Start the routing process.
	 *
	 * @param string $url The URL to route.
	 * @param callable $noMatch Callback in case no route matched.
	 * @return mixed Return value of whatever callback was invoked.
	 * @throws InvalidArgumentException if $noMatch is not callable.
	 * @throws RuntimeException in case no route matched and no callback was supplied.
	 */
	public function route($url, $noMatch = null) {
		if ($noMatch && !is_callable($noMatch, true)) {
			throw new InvalidArgumentException('$noMatch must be of type callable, got ' . gettype($noMatch));
		}

		foreach ($this->routes as $route) {
			if ($match = $route['route']->matchUrl($url)) {
				return $this->callback($route['callback'], $match);
			}
		}

		if ($noMatch) {
			return call_user_func($noMatch, $url);
		}
		
		throw new RuntimeException("No route matched $url");
	}

	/**
	 * Get a URL from a dispatch array.
	 *
	 * @param array $dispatch
	 * @return mixed Matching URL or false if no match.
	 */
	public function reverseRoute(array $dispatch) {
		foreach ($this->routes as $route) {
			if ($match = $route['route']->matchDispatch($dispatch)) {
				return $match->url();
			}
		}
		return false;
	}

	/**
	 * Executes a callback or the default callback for a matched route.
	 */
	protected function callback($callback, Route $route) {
		if ($callback) {
			if (!is_callable($callback, true)) {
				throw new InvalidArgumentException('$callback must be of type callable, got ' . gettype($callback));
			}
			return call_user_func($callback, $route);
		}

		if ($this->defaultCallback) {
			return call_user_func($this->defaultCallback, $route);
		}

		throw new RuntimeException(sprintf('Route %s matched URL %s, but no callback given', $route->pattern(), $route->url()));
	}

}
