<?php

namespace Kunststube\Router;

require_once 'Kunststube/Router/Route.php';


class RouteFactory {

	public function newRoute($pattern, array $dispatch = array()) {
		return new Route($pattern, $dispatch);
	}

}
