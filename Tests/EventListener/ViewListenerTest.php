<?php

namespace Ehann\Bundle\WebServiceBundle\Tests\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Ehann\Bundle\WebServiceBundle\EventListener\ViewListener;
use Ehann\Bundle\WebServiceBundle\Tests\Fixtures\Response\FooResponse;
use JMS\Serializer\Serializer;
use Negotiation\Negotiator;

class ViewListenerTest extends \PHPUnit_Framework_TestCase
{
    private $listener;
    private $viewHandler;

    public function setUp()
    {
        $serializer = $this->getMockBuilder('JMS\Serializer\Serializer')
            ->disableOriginalConstructor()
            ->getMock();
        $negotiator = new Negotiator();
        $this->listener = new ViewListener($serializer, $negotiator);
    }

    public function formatProvider()
    {
        return array(
            array('application/json'),
            array('application/xml'),
            array('text/yml'),
            array('*/*'),
        );
    }

    /**
     * @dataProvider formatProvider
     */
    public function testOnKernelView($format)
    {
        $request = new Request();
        $request->headers->set('Accept', $format);
        $event = $this->getEvent($request);

        $this->listener->onKernelView($event);
    }

    public function testOnKernelViewWithUnsupportedFormat()
    {
        $request = new Request();
        $request->headers->set('Accept', 'unsupported/format');
        $event = $this->getEvent($request);
        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException');

        $this->listener->onKernelView($event);
    }

    public function getEvent($request)
    {
        $response = $this->getMock('Ehann\Bundle\WebServiceBundle\Tests\Fixtures\Response\FooResponse');
        $response->expects($this->any())
            ->method('setContent');
        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue($request));
        $event->expects($this->once())
            ->method('getControllerResult')
            ->will($this->returnValue($response));

        return $event;
    }
}
