<?php

namespace Ehann\Bundle\WebServiceBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use JMS\Serializer\Serializer;
use Negotiation\Negotiator;
use Ehann\Bundle\WebServiceBundle\Response\IWebServiceResponse;

class ViewListener
{
    public $serializer;
    public $negotiator;

    public function __construct(Serializer $serializer, Negotiator $negotiator)
    {
        $this->serializer = $serializer;
        $this->negotiator = $negotiator;
    }

    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $controllerResult = $event->getControllerResult();
        if ($controllerResult instanceof IWebServiceResponse) {
            $request = $event->getRequest();
            $acceptHeader = implode(',', $request->getAcceptableContentTypes());
            $priorities = array('application/json', 'application/xml', 'text/yml', '*/*');
            $format = $this->negotiator->getBest($acceptHeader, $priorities)->getValue();
            $response = $event->getResponse();
            if (!$response) {
                $response = new Response();
            }
            $response->headers->set('Content-Type', $format);
            $content = $controllerResult->getContent();
            if ($format === 'application/json' || $format === '*/*') {
                $response->setContent($this->serializer->serialize($content, 'json'));
            } else if ($format === 'application/xml') {
                $response->setContent($this->serializer->serialize($content, 'xml'));
            } else if ($format === 'text/yml') {
                $response->setContent($this->serializer->serialize($content, 'yml'));
            } else {
                $msg = sprintf('The response format "%s" is not supported by this resource. ', $format);
                $msg .= sprintf('Acceptable formats are %s', implode(', ', $priorities));
                throw new NotAcceptableHttpException($msg);
            }
            $event->setResponse($response);
        }
    }
}