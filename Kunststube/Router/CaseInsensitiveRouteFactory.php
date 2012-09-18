<?php

namespace Kunststube\Router;

require_once 'Kunststube/Router/RouteFactory.php';
require_once 'Kunststube/Router/CaseInsensitiveRoute.php';


class CaseInsensitiveRouteFactory extends RouteFactory {

	public function newRoute($pattern, array $dispatch = array()) {
		return new CaseInsensitiveRoute($pattern, $dispatch);
	}

}
