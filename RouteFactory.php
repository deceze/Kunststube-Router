<?php

namespace Kunststube\Router;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Route.php';


class RouteFactory {

    public function newRoute($pattern, array $dispatch = array()) {
        return new Route($pattern, $dispatch);
    }

}
