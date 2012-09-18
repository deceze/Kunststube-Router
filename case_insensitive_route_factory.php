<?php

namespace Kunststube\Routing;

require_once 'route_factory.php';
require_once 'case_insensitive_route.php';

class CaseInsensitiveRouteFactory extends RouteFactory {

	public function newRoute($pattern, array $dispatch = array()) {
		return new CaseInsensitiveRoute($pattern, $dispatch);
	}

}
