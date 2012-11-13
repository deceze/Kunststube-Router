<?php

namespace Kunststube\Router;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'RouteFactory.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'CaseInsensitiveRoute.php';


class CaseInsensitiveRouteFactory extends RouteFactory {

    public function newRoute($pattern, array $dispatch = array()) {
        return new CaseInsensitiveRoute($pattern, $dispatch);
    }

}
