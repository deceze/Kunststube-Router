<?php

namespace Kunststube\Routing;

use \InvalidArgumentException,
	\RuntimeException;


class Router {

	protected $routes = array(),
	          $routeFactory,
	          $defaultCallback;

	public function __construct(RouteFactory $routeFactory = null) {
		if (!$routeFactory) {
			require_once 'route_factory.php';
			$routeFactory = new RouteFactory;
		}
		$this->routeFactory = $routeFactory;
	}

	public function add($pattern, array $dispatch = array(), $callback = null) {
		$this->addRoute($this->routeFactory->newRoute($pattern, $dispatch), $callback);
	}

	public function addRoute(Route $route, $callback = null) {
		if ($callback && !is_callable($callback)) {
			throw new InvalidArgumentException('$callback must be of type callable, got ' . gettype($callback));
		}
		$this->routes[] = compact('route', 'callback');
	}

	public function defaultCallback($callback) {
		if ($callback && !is_callable($callback)) {
			throw new InvalidArgumentException('$callback must be of type callable, got ' . gettype($callback));
		}
		$this->defaultCallback = $callback;
	}

	public function route($url, $noMatch = null) {
		if ($noMatch && !is_callable($noMatch)) {
			throw new InvalidArgumentException('$noMatch must be of type callable, got ' . gettype($noMatch));
		}

		foreach ($this->routes as $route) {
			if ($match = $route['route']->matchUrl($url)) {
				return $this->callback($route['callback'], $match);
			}
		}

		if ($noMatch) {
			call_user_func($noMatch, $url);
		}
		
		throw new RuntimeException('No route matched');
	}

	public function reverseRoute(array $dispatch) {
		foreach ($this->routes as $route) {
			if ($match = $route['route']->matchDispatch($dispatch)) {
				return $match->url();
			}
		}
		return false;
	}

	private function callback($callback, Route $route) {
		if ($callback) {
			if (!is_callable($callback)) {
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
