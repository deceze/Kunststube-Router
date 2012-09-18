<?php

use Kunststube\Routing\Router,
	Kunststube\Routing\CaseInsensitiveRoute,
	Kunststube\Routing\CaseInsensitiveRouteFactory;

require_once dirname(__DIR__) . '/router.php';
require_once dirname(__DIR__) . '/case_insensitive_route.php';
require_once dirname(__DIR__) . '/case_insensitive_route_factory.php';

class CaseInsensitiveRouteTest extends PHPUnit_Framework_TestCase {

	public function testCaseSensitiveRouting() {
		$noMatchMock = $this->getMock('stdClass', array('callback'));
  		$noMatchMock->expects($this->never())->method('callback');

		$routeMock = $this->getMock('stdClass', array('callback'));
  		$routeMock->expects($this->once())->method('callback');

		$r = new Router;
		$r->add('/foo/bar', array('case' => 'low'), array($noMatchMock, 'callback'));
		$r->add('/foo/Bar', array('case' => 'cap'), array($routeMock, 'callback'));
		$r->route('/foo/Bar');
	}

	public function testCaseInsensitiveRouting() {
		$routeMock = $this->getMock('stdClass', array('callback'));
  		$routeMock->expects($this->once())->method('callback');

		$noMatchMock = $this->getMock('stdClass', array('callback'));
  		$noMatchMock->expects($this->never())->method('callback');

		$r = new Router(new CaseInsensitiveRouteFactory);
		$r->add('/foo/bar', array('case' => 'low'), array($routeMock, 'callback'));
		$r->add('/foo/Bar', array('case' => 'cap'), array($noMatchMock, 'callback'));
		$r->route('/foo/Bar');
	}

	public function testMixedCaseRouting() {
		$routeMock = $this->getMock('stdClass', array('callback'));
  		$routeMock->expects($this->once())->method('callback');

		$noMatchMock = $this->getMock('stdClass', array('callback'));
  		$noMatchMock->expects($this->never())->method('callback');

		$r = new Router;
		$r->add('/case/sensitive/route', array(), array($noMatchMock, 'callback'));
		$r->addRoute(new CaseInsensitiveRoute('/case/insensitive/route'), array($routeMock, 'callback'));
		$r->route('/Case/INSENSITIVE/RoUtE');
	}

}