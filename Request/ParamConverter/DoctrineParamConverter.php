<?php

namespace Ehann\Bundle\WebServiceBundle\Request\ParamConverter;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;
use ReflectionClass;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DoctrineParamConverter implements ParamConverterInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    public function __construct(ManagerRegistry $registry = null)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException       When unable to guess how to get a Doctrine instance from the request information
     * @throws NotFoundHttpException When object not found
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $name = $configuration->getName();
        $class = $configuration->getClass();
        $options = $this->getOptions($configuration);

        $reflectionClass = new ReflectionClass($class);
        if (!$reflectionClass->implementsInterface('Ehann\Bundle\WebServiceBundle\Request\IWebServiceRequest')) {
            return false;
        }

        if (null === $request->attributes->get($name, false)) {
            $configuration->setIsOptional(true);
        }

        // find by identifier?
        $object = $this->find($class, $request, $options, $name);
        if ($object === false && $request->isMethod('GET')) {
            // find by criteria
            $object = $this->findOneBy($class, $request, $options);
            if ($object === false && $configuration->isOptional()) {
                $object = null;
            } else if ($object === false) {
                throw new \LogicException('Unable to guess how to get a Doctrine instance from the request information.');
            }
        } else if ($object === false && $request->isMethod('POST')) {
            // Create new object
            $object = new $class; // init from request
        } else if ($object !== false && $request->isMethod('POST')) {
            // Merge object with new object
//die('here');
            $newObject = new $class; // init from request
            $object = $this->mergeObjects($class, $options, $newObject, $object);
        }

//        var_dump($object);
//        die();
//        if ($request->isMethod('POST') && is_null($object)) {
//            $object = new $class;
//        } else
        if (is_null($object) && !$configuration->isOptional()) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $class));
        }

        $request->attributes->set($name, $object);

        return true;
    }

    protected function mergeObjects($class, $options, $object1, $object2)
    {
        $em = $this->getManager($options['entity_manager'], $class);
        $metadata = $em->getClassMetadata($class);

        $validFields = $metadata->fieldNames;
        foreach ($validFields as $fieldName => $fieldType) {
            $methodSuffix = ucfirst($fieldName);
            $getMethodName = 'get' . $methodSuffix;
            $value = $object1->{$getMethodName}();
            if (is_null($value)) {
                $value = $object2->{$getMethodName}();
            }
            $setMethodName = 'set' . $methodSuffix;
            if (method_exists($object1, $setMethodName)) {
                $object1->{$setMethodName}($value);
            }
        }

        return $object1;
    }

    protected function find($class, Request $request, $options, $name)
    {
        if ($options['mapping'] || $options['exclude']) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);

        if (false === $id || null === $id) {
            return false;
        }

        if (isset($options['repository_method'])) {
            $method = $options['repository_method'];
        } else {
            $method = 'find';
        }

        try {
            return $this->getManager($options['entity_manager'], $class)->getRepository($class)->$method($id);
        } catch (NoResultException $e) {
            return null;
        }
    }

    protected function getIdentifier(Request $request)
    {
        if ($request->query->has('id')) {
            return $request->query->get('id');
        }
        if ($request->request->has('id')) {
            return $request->request->get('id');
        }

        return false;
    }

    protected function findOneBy($class, Request $request, $options)
    {
        if (!$options['mapping']) {
            $keys = array_merge(array_keys($request->query->all()), array_keys($request->request->all()));
            $options['mapping'] = $keys ? array_combine($keys, $keys) : array();
        }

        foreach ($options['exclude'] as $exclude) {
            unset($options['mapping'][$exclude]);
        }
        if (!$options['mapping']) {
            return false;
        }

        $criteria = array();
        $em = $this->getManager($options['entity_manager'], $class);
        $metadata = $em->getClassMetadata($class);

        foreach ($options['mapping'] as $parameter => $field) {
            if ($metadata->hasField($field) || ($metadata->hasAssociation($field) && $metadata->isSingleValuedAssociation($field))) {
                if ($request->query->has($parameter)) {
                    $criteria[$field] = $request->query->get($parameter);
                }
                if (!array_key_exists($field, $criteria) && $request->request->has($parameter)) {
                    $criteria[$field] = $request->request->get($parameter);
                }
                if ('datetime' === $metadata->getTypeOfField($field)) {
                    try {
                        $criteria[$field] = new \DateTime($criteria[$field]);
                    } catch (\Exception $exception) {
                        throw new NotFoundHttpException('Invalid date given.', $exception);
                    }
                }
            }
        }

        if ($options['strip_null']) {
            $criteria = array_filter($criteria, function ($value) {
                return !is_null($value);
            });
        }

        if (!$criteria) {
            return false;
        }

        if (isset($options['repository_method'])) {
            $method = $options['repository_method'];
        } else {
            $method = 'findOneBy';
        }

        try {
            return $em->getRepository($class)->$method($criteria);
        } catch (NoResultException $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ParamConverter $configuration)
    {
        // if there is no manager, this means that only Doctrine DBAL is configured
        if (null === $this->registry || !count($this->registry->getManagers())) {
            return false;
        }

        if (null === $configuration->getClass()) {
            return false;
        }

        $options = $this->getOptions($configuration);

        // Doctrine Entity?
        $em = $this->getManager($options['entity_manager'], $configuration->getClass());
        if (null === $em) {
            return false;
        }

        return !$em->getMetadataFactory()->isTransient($configuration->getClass());
    }

    protected function getOptions(ParamConverter $configuration)
    {
        return array_replace(array(
            'entity_manager' => null,
            'exclude' => array(),
            'mapping' => array(),
            'strip_null' => false,
        ), $configuration->getOptions());
    }

    private function getManager($name, $class)
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        return $this->registry->getManager($name);
    }
}