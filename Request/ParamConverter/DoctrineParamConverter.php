<?php
/**
 * @author  Ethan Hann <ethanhann@gmail.com>
 * @license For the full copyright and license information, please view the LICENSE
 *          file that was distributed with this source code.
 */

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

        $object = $this->find($class, $request, $options, $name);
        if ($object === false && $request->isMethod('GET')) {
            $object = $this->findOneBy($class, $request, $options);
        } else if ($request->isMethod('POST')) {
            $requestObject = new $class;
            $keys = array_keys($request->request->all());
            $options['mapping'] = $keys ? array_combine($keys, $keys) : array();
            $em = $this->getManager($options['entity_manager'], $class);
            $metadata = $em->getClassMetadata($class);

            foreach ($options['mapping'] as $parameter => $field) {
                if ($metadata->hasField($field) || ($metadata->hasAssociation($field) && $metadata->isSingleValuedAssociation($field))) {
                    $methodSuffix = ucfirst($field);
                    $setMethodName = 'set' . $methodSuffix;
                    $value = $request->request->get($parameter);
                    if ('datetime' === $metadata->getTypeOfField($field)) {
                        try {
                            $value = new \DateTime($value);
                        } catch (\Exception $exception) {
                            throw new NotFoundHttpException('Invalid date given.', $exception);
                        }
                    }
                    if (method_exists($requestObject, $setMethodName)) {
                        $requestObject->{$setMethodName}($value);
                    }
                }
            }

            if ($object !== false) {
                // Merge
                foreach ($metadata->getFieldNames() as $fieldName => $fieldType) {
                    $methodSuffix = ucfirst($fieldName);
                    $getMethodName = 'get' . $methodSuffix;
                    $value = $requestObject->{$getMethodName}();
                    if (is_null($value)) {
                        $value = $object->{$getMethodName}();
                    }
                    $setMethodName = 'set' . $methodSuffix;
                    if (method_exists($requestObject, $setMethodName)) {
                        $requestObject->{$setMethodName}($value);
                    }
                }
            }
            $object = $requestObject;
        }

        if ($object === false && !$configuration->isOptional()) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $class));
        }

        $request->attributes->set($name, $object);

        return true;
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