<?php

namespace Kunststube\Routing;

require_once 'route.php';

class RouteFactory {

	public function newRoute($pattern, array $dispatch = array()) {
		return new Route($pattern, $dispatch);
	}

}
