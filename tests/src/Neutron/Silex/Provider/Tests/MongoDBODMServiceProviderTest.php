<?php

namespace Neutron\Silex\Provider\Tests;

use Silex\Application;
use Neutron\Silex\Provider\MongoDBODMServiceProvider;

class MongoDBODMServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testRegister()
    {
        $app = new Application();
        $app->register(new MongoDBODMServiceProvider(), array(
            'doctrine.odm.mongodb.connection_options' => array(
                'database' => 'mongodb_extension_test',
                'host'     => 'localhost',
            ),
            'doctrine.odm.mongodb.documents' => array(
                array('type' => 'yml', 'path' => '/path/to/yml/files', 'namespace' => 'My\\Document'),
                array('type' => 'annotation', 'path' => '/path/to/another/dir/with/documents', 'namespace' => 'Acme\\Document'),
                array('type' => 'xml', 'path' => '/path/to/xml/files', 'namespace' => 'Your\\Document'),
                array('type' => 'annotation', 'path' => array(
                    '/path/to/Documents',
                    '/path/to/another/dir/for/the/same/namespace'
                ), 'namespace' => 'Document'),
            )
        ));

        $conn = $app['doctrine.mongodb.connection'];
        $this->assertInstanceOf('Doctrine\MongoDB\Connection', $conn);

        $this->assertInstanceOf('Doctrine\ODM\MongoDB\DocumentManager', $app['doctrine.odm.mongodb.dm']);

        $config = $app['doctrine.odm.mongodb.configuration'];
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Configuration', $config);
        $this->assertSame($app['doctrine.odm.mongodb.dm']->getConfiguration(), $config);
        $this->assertSame(spl_object_hash($app['doctrine.odm.mongodb.dm']->getConfiguration()), spl_object_hash($config));

        $driver = $app['doctrine.odm.mongodb.dm']->getConfiguration()->getMetadataDriverImpl();
        $this->assertInstanceOf('Doctrine\Common\Persistence\Mapping\Driver\MappingDriver', $driver);

        $drivers = $driver->getDrivers();

        $ymlDriver = $drivers['My\\Document'];
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver', $ymlDriver);
        $this->assertEquals(array('/path/to/yml/files'), $ymlDriver->getLocator()->getPaths());

        $annotationDriver = $drivers['Acme\\Document'];
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver', $annotationDriver);
        $this->assertEquals(array('/path/to/another/dir/with/documents'), $annotationDriver->getPaths());

        $xmlDriver = $drivers['Your\\Document'];
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver', $xmlDriver);
        $this->assertEquals(array('/path/to/xml/files'), $xmlDriver->getLocator()->getPaths());

        $anotherAnnotationDriver = $drivers['Document'];
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver', $anotherAnnotationDriver);
        $this->assertEquals(array(
            '/path/to/Documents',
            '/path/to/another/dir/for/the/same/namespace'
        ), $anotherAnnotationDriver->getPaths());
    }
}
