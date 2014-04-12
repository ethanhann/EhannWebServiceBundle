<?php

namespace Ehann\Bundle\WebServiceBundle\Controller;

use Doctrine\Common\Persistence\Mapping\MappingException;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class MetadataController extends Controller
{
    public function indexAction()
    {
        $routes = $this->get('router')->getRouteCollection()->all();
        $manager = $this->getDoctrine()->getManager();
        $services = array();
        foreach ($routes as $routeName => $route) {
            if ($route->hasDefault('_controller')) {
                $controller = explode('::', $route->getDefault('_controller'));
                if (count($controller) === 2 && class_exists($controller[0])) { //} && $controller[0] instanceof IWebServiceController) {
                    $class = new ReflectionClass($controller[0]);
                    $method = $class->getMethod($controller[1]);

                    if ($method->isPublic() && $class->implementsInterface('Ehann\Bundle\WebServiceBundle\Controller\IWebServiceController')) {
                        $parameters = array();
                        foreach ($method->getParameters() as $parameter) {
                            try {
                                $paramClass = $parameter->getClass()->name;
                                $parameters[$parameter->getName()] = $manager->getClassMetadata($paramClass)->fieldMappings;
                            } catch (MappingException $mappingException) {
                            }
                        }

//                        var_dump($parameters);
                        $services[$routeName] = array(
                            'parameters' => $parameters,
                            'route' => $route
                        );
                    }
                }
            }
        }
//        die();
//        var_dump($services);die();

        return $this->render('EhannWebServiceBundle:Metadata:index.html.twig', array('services' => $services));
    }
}
