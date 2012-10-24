<?php

use Kunststube\Router\Route;

require_once 'Route.php';


class RouteTest extends PHPUnit_Framework_TestCase {

    public function testConstruction() {
    	$r = new Route('/foo/:bar/\d+:baz', array('foo' => 'bar'));
    	$this->assertInstanceOf('Kunststube\Router\Route', $r);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEmptyRoute() {
    	new Route('');
    }

    public function testRouteWithoutNames() {
    	$this->assertInstanceOf('Kunststube\Router\Route', new Route('/foo/bar'));
    }

    public function testSettingRouteValues() {
        $r = new Route('/:foo/\w+:bar/\d+:baz');
        $r->foo = 'foo';
        $r->bar = 'bar';
        $r->baz = 42;
        $this->assertEquals('/foo/bar/42', $r->url());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailingSettingRouteValues() {
        $r = new Route('/:foo/\w+:bar/\d+:baz');
        $r->foo = 'foo';
        $r->bar = 'bar';
        $r->baz = 'baz';
    }

    public function testSettingWildcardArgs() {
        $r = new Route('/:foo/*');
        $r->foo = 'foo';
        $r->bar = 'bar';
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailingSettingWildcardArgs() {
        $r = new Route('/:foo');
        $r->foo = 'foo';
        $r->bar = 'bar';
    }

    public function testRouteManipulationAndUrlGeneration() {
        $r = new Route('/foo/:bar/*');

        $r->bar = 42;
        $this->assertEquals('/foo/42', $r->url());

        $r->bar = 'baz';
        $this->assertEquals('/foo/baz', $r->url());

        $r->wild = 'card';
        $this->assertEquals('/foo/baz/wild:card', $r->url());
    }

    public function testTrailingSlash() {
        $r = new Route('/foo');
        $this->assertInstanceOf('Kunststube\Router\Route', $r->matchUrl('/foo/'));
    }

}