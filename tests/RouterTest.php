<?php

use Kunststube\Routing\Router,
	Kunststube\Routing\Route;

require_once dirname(__DIR__) . '/router.php';

class RouterTest extends PHPUnit_Framework_TestCase {

	public function testBasicRouting() {
		$mock = $this->getMock('stdClass', array('callback'));
  		$mock->expects($this->once())->method('callback');

		$r = new Router;
		$r->add('/foo', array('controller' => 'foo'), array($mock, 'callback'));
		$r->route('/foo');
	}

	public function testNamedParameterRouting() {
		$test   = $this;
		$called = false;

		$r = new Router;
		$r->add('/:controller/:action', array(), function (Route $route) use ($test, &$called) {
			$called = true;
			$test->assertEquals('foo', $route->controller);
			$test->assertEquals('bar', $route->action);
		});
		$r->route('/foo/bar');

		$this->assertTrue($called);
	}

	public function testRegexParameterRouting() {
		$test   = $this;
		$called = false;

		$r = new Router;
		$r->add('/\d+:id/\w{3}:lang/\w+_controller:controller', array('action' => 'view'), function (Route $route) use ($test, &$called) {
			$called = true;
			$test->assertEquals('foo_controller', $route->controller);
			$test->assertEquals('eng',            $route->lang);
			$test->assertEquals(42,               $route->id);
			$test->assertEquals('view',           $route->action);
		});
		$r->route('/42/eng/foo_controller');

		$this->assertTrue($called);
	}

	public function testFailingRegexParameterRouting() {
		$routeMock = $this->getMock('stdClass', array('callback'));
  		$routeMock->expects($this->never())->method('callback');

		$noMatchMock = $this->getMock('stdClass', array('callback'));
  		$noMatchMock->expects($this->exactly(3))->method('callback');

		$r = new Router;
		$r->add('/\d+:id/\w{3}:lang/\w+_controller:controller', array(), array($routeMock, 'callback'));
		
		$r->route('/42/engl/foo_controller',   array($noMatchMock, 'callback'));
		$r->route('/a42/eng/foo_controller',   array($noMatchMock, 'callback'));
		$r->route('/42/eng/foo%20_controller', array($noMatchMock, 'callback'));
	}

	public function testReverseRouting() {
	    $r = new Router;
	    $r->add('/foo',         array('controller' => 'foos', 'action' => 'index'));
	    $r->add('/foo/:action', array('controller' => 'foos'));

	    $this->assertEquals('/foo', $r->reverseRoute(array('controller' => 'foos', 'action' => 'index')));
	    $this->assertEquals('/foo/bar', $r->reverseRoute(array('controller' => 'foos', 'action' => 'bar')));
	}

	public function testWildcardReverseRouting() {
		$r = new Router;
		$r->add('/foo',       array('controller' => 'foo', 'action' => 'index'));
		$r->add('/foo/bar/*', array('controller' => 'foo', 'action' => 'index'));

		$this->assertEquals('/foo', $r->reverseRoute(array('controller' => 'foo', 'action' => 'index')));
		$this->assertEquals('/foo/bar/baz:42', $r->reverseRoute(array('controller' => 'foo', 'action' => 'index', 'baz' => 42)));
	}

	public function testEmptyDispatcherInformation() {
		$routeMock = $this->getMock('stdClass', array('callback'));
		$routeMock->expects($this->once())->method('callback');

		$r = new Router;
		$r->add('/foo', array(), array($routeMock, 'callback'));
		$r->route('/foo');
	}

	public function testRoutePriority() {
		$routeMock = $this->getMock('stdClass', array('callback'));
		$routeMock->expects($this->exactly(2))->method('callback');

		$noMatchMock = $this->getMock('stdClass', array('callback'));
		$noMatchMock->expects($this->never())->method('callback');

		$r = new Router;
		$r->add('/*', array(), array($routeMock, 'callback'));
		$r->add('/foo', array(), array($noMatchMock, 'callback'));
		$r->route('/');
		$r->route('/foo');
	}

	public function testRegexReverseRouting() {
		$r = new Router;
		$r->add('/\d+:id',     array('controller' => 'foo', 'action' => 'bar'));
		$r->add('/foo/\w+:id', array('controller' => 'foo', 'action' => 'bar'));

		$this->assertEquals('/42', $r->reverseRoute(array('controller' => 'foo', 'action' => 'bar', 'id' => 42)));
		$this->assertEquals('/foo/baz', $r->reverseRoute(array('controller' => 'foo', 'action' => 'bar', 'id' => 'baz')));

	}

}