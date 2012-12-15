<?php

use Kunststube\Router\Router,
	Kunststube\Router\Route;

require_once 'Router.php';


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
    
    public function testRequestMethodRouting() {
        $getMock = $this->getMock('stdClass', array('callback'));
        $getMock->expects($this->exactly(2))->method('callback');
        $postMock = $this->getMock('stdClass', array('callback'));
        $postMock->expects($this->exactly(1))->method('callback');
        $putMock = $this->getMock('stdClass', array('callback'));
        $putMock->expects($this->exactly(1))->method('callback');
        $deleteMock = $this->getMock('stdClass', array('callback'));
        $deleteMock->expects($this->exactly(1))->method('callback');
        
        $r = new Router;
        $r->addGet('/foo', array(), array($getMock, 'callback'));
        $r->addPost('/foo', array(), array($postMock, 'callback'));
        $r->addPut('/foo', array(), array($putMock, 'callback'));
        $r->addDelete('/foo', array(), array($deleteMock, 'callback'));
        
        $r->route('/foo');
        $r->routeMethod($r::GET, '/foo');
        $r->routeMethod($r::POST, '/foo');
        $r->routeMethod($r::PUT, '/foo');
        $r->routeMethod($r::DELETE, '/foo');
    }
    
    public function testHeadRouting() {
        $callbackMock = $this->getMock('stdClass', array('callback'));
        $callbackMock->expects($this->exactly(1))->method('callback');
        
        $r = new Router;
        $r->addMethod($r::HEAD, '/*', array(), array($callbackMock, 'callback'));
        $r->routeMethod($r::HEAD, '/foo');
    }

    public function testCombinedRouting() {
        $callbackMock = $this->getMock('stdClass', array('callback'));
        $callbackMock->expects($this->exactly(1))->method('callback');
        
        $r = new Router;
        $r->addMethod($r::POST | $r::PUT, '/*', array(), array($callbackMock, 'callback'));
        $r->routeMethod($r::POST, '/foo');
    }

    public function testCombinedRoutingNonMatch() {
        $callbackMock = $this->getMock('stdClass', array('callback'));
        $callbackMock->expects($this->never())->method('callback');
        
        $nonMatchCallbackMock = $this->getMock('stdClass', array('callback'));
        $nonMatchCallbackMock->expects($this->exactly(1))->method('callback');
        
        $r = new Router;
        $r->addMethod($r::POST | $r::PUT, '/*', array(), array($callbackMock, 'callback'));
        $r->routeMethod($r::GET, '/foo', array($nonMatchCallbackMock, 'callback'));
    }

    public function testMethodStringRouting() {
        $callbackMock = $this->getMock('stdClass', array('callback'));
        $callbackMock->expects($this->exactly(1))->method('callback');
        
        $r = new Router;
        $r->addMethod($r::POST, '/*', array(), array($callbackMock, 'callback'));
        $r->routeMethodFromString('POST', '/foo');
    }

}