<?php

use Kunststube\Routing\Route;

require_once dirname(__DIR__) . '/route.php';

class RouteTest extends PHPUnit_Framework_TestCase {

    public function testConstruction() {
    	$r = new Route('/foo/:bar/\d+:baz', array('foo' => 'bar'));
    	$this->assertInstanceOf('Kunststube\Routing\Route', $r);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEmptyRoute() {
    	new Route('');
    }

    public function testRouteWithoutNames() {
    	$this->assertInstanceOf('Kunststube\Routing\Route', new Route('/foo/bar'));
    }

}