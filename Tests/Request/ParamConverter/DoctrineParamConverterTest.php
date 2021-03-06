<?php

namespace Ehann\Bundle\WebServiceBundle\Tests\Request\ParamConverter;

use Ehann\Bundle\WebServiceBundle\Tests\Fixtures\Entity\Document;
use Symfony\Component\HttpFoundation\Request;
use Ehann\Bundle\WebServiceBundle\Request\ParamConverter\DoctrineParamConverter;
use Doctrine\Common\Persistence\ManagerRegistry;

class DoctrineParamConverterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @var DoctrineParamConverter
     */
    private $converter;

    public function setUp()
    {
        if (!interface_exists('Doctrine\Common\Persistence\ManagerRegistry')) {
            $this->markTestSkipped();
        }

        $this->registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $this->converter = new DoctrineParamConverter($this->registry);
    }

    public function createConfiguration($class = null, array $options = null, $name = 'arg', $isOptional = false)
    {
        $methods = array('getClass', 'getAliasName', 'getOptions', 'getName', 'allowArray');
        if (null !== $isOptional) {
            $methods[] = 'isOptional';
        }
        $config = $this
            ->getMockBuilder('Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter')
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();
        if ($options !== null) {
            $config->expects($this->once())
                ->method('getOptions')
                ->will($this->returnValue($options));
        }
        if ($class !== null) {
            $config->expects($this->any())
                ->method('getClass')
                ->will($this->returnValue($class));
        }
        $config->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));
        if ($isOptional !== null) {
            $config->expects($this->any())
                ->method('isOptional')
                ->will($this->returnValue($isOptional));
        }

        return $config;
    }

    public function testApplyWithNoIdAndData()
    {
        $request = new Request();
        $config = $this->createConfiguration(null, array());

        $this->setExpectedException('ReflectionException');

        $this->converter->apply($request, $config);
    }

    public function idsProvider()
    {
        return array(
            array(1),
            array(0),
            array('foo'),
        );
    }

    /**
     * @dataProvider idsProvider
     */
    public function testApplyWithIdAndGet($id)
    {
        $request = new Request();
        $request->setMethod('GET');
        $request->query->set('id', $id);
        $class = 'Ehann\Bundle\WebServiceBundle\Tests\Fixtures\Entity\Document';
        $config = $this->createConfiguration($class, array('id' => 'id'), 'arg');
        $manager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');
        $objectRepository = $this->getMock('Doctrine\Common\Persistence\ObjectRepository');
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($manager));
        $manager->expects($this->once())
            ->method('getRepository')
            ->with($class)
            ->will($this->returnValue($objectRepository));
        $objectRepository->expects($this->once())
            ->method('find')
            ->with($this->equalTo($id))
            ->will($this->returnValue($object =new \stdClass));

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertSame($object, $request->attributes->get('arg'));
    }

    public function testApplyWithNoIdAndPost()
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('title', 'foo');
        $request->request->set('body', 'bar');
        $class = 'Ehann\Bundle\WebServiceBundle\Tests\Fixtures\Entity\Document';
        $manager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');
        $config = $this->createConfiguration($class, array());
        $metadata = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadata');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($manager));
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($metadata));
        $metadata->expects($this->exactly(2))
            ->method('hasField')
            ->with($this->logicalOr(
                $this->equalTo('title'),
                $this->equalTo('body')
            ))
            ->will($this->returnValue(true));

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertEquals('foo', $request->attributes->get('arg')->getTitle());
        $this->assertEquals('bar', $request->attributes->get('arg')->getBody());
    }

    public function testApplyWithMappingAndExclude()
    {
        $request = new Request();
        $request->query->set('foo', 1);
        $request->query->set('bar', 2);
        $class = 'Ehann\Bundle\WebServiceBundle\Tests\Fixtures\Entity\Document';
        $config = $this->createConfiguration(
            $class,
            array('mapping' => array('foo' => 'Foo'), 'exclude' => array('bar')),
            'arg'
        );

        $manager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');
        $metadata = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadata');
        $repository = $this->getMock('Doctrine\Common\Persistence\ObjectRepository');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($manager));

        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($metadata));
        $manager->expects($this->once())
            ->method('getRepository')
            ->with($class)
            ->will($this->returnValue($repository));

        $metadata->expects($this->once())
            ->method('hasField')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(true));

        $repository->expects($this->once())
            ->method('findOneBy')
            ->with($this->equalTo(array('Foo' => 1)))
            ->will($this->returnValue($object =new \stdClass));

        $ret = $this->converter->apply($request, $config);

//        $this->assertTrue($ret);
//        $this->assertSame($object, $request->query->get('arg'));
    }

    public function testSupports()
    {
        $config = $this->createConfiguration('stdClass', array());
        $metadataFactory = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadataFactory');
        $metadataFactory->expects($this->once())
            ->method('isTransient')
            ->with($this->equalTo('stdClass'))
            ->will($this->returnValue( false ));

        $objectManager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');
        $objectManager->expects($this->once())
            ->method('getMetadataFactory')
            ->will($this->returnValue($metadataFactory));

        $this->registry->expects($this->once())
            ->method('getManagers')
            ->will($this->returnValue(array($objectManager)));

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->will($this->returnValue($objectManager));

        $ret = $this->converter->supports($config);

        $this->assertTrue($ret, "Should be supported");
    }

    public function testSupportsWithConfiguredgetClassMetadataManager()
    {
        $config = $this->createConfiguration('stdClass', array('entity_manager' => 'foo'));
        $metadataFactory = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadataFactory');
        $metadataFactory->expects($this->once())
            ->method('isTransient')
            ->with($this->equalTo('stdClass'))
            ->will($this->returnValue( false ));

        $objectManager = $this->getMock('Doctrine\Common\Persistence\ObjectManager');
        $objectManager->expects($this->once())
            ->method('getMetadataFactory')
            ->will($this->returnValue($metadataFactory));

        $this->registry->expects($this->once())
            ->method('getManagers')
            ->will($this->returnValue(array($objectManager)));

        $this->registry->expects($this->once())
            ->method('getManager')
            ->with('foo')
            ->will($this->returnValue($objectManager));

        $ret = $this->converter->supports($config);

        $this->assertTrue($ret, "Should be supported");
    }
}
