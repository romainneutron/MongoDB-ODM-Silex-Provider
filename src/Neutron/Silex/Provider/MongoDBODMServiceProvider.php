<?php

namespace Neutron\Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver;

/**
 * @author Justin Hileman <justin@justinhileman.info>
 * @author Florian Klein  <florian.klein@free.fr>
 * @author Romain Neutron <imprec@gmail.com>
 */
class MongoDBODMServiceProvider implements ServiceProviderInterface
{

    public function register(Application $app)
    {
        $this->setDoctrineMongoDBDefaults($app);
        $this->loadDoctrineMongoDBConfiguration($app);
        $this->loadDoctrineMongoDBConnection($app);
        $this->loadDoctrineMongoDBDocumentManager($app);
    }

    public function boot(Application $app)
    {
    }

    private function setDoctrineMongoDBDefaults(Application $app)
    {
        // default connection options
        $options = isset($app['doctrine.odm.mongodb.connection_options']) ? $app['doctrine.odm.mongodb.connection_options'] : array();
        $app['doctrine.odm.mongodb.connection_options'] = array_replace(array(
            'database' => null,
            'host'     => null,
            'options'  => array()
        ), $options);

        // default extension options
        $defaults = array(
            'documents'               => array(
                array('type' => 'annotation', 'path' => 'Document', 'namespace' => 'Document')
            ),
            'proxies_dir'             => 'cache/doctrine/odm/mongodb/Proxy',
            'proxies_namespace'       => 'DoctrineMongoDBProxy',
            'auto_generate_proxies'   => true,
            'hydrators_dir'           => 'cache/doctrine/odm/mongodb/Hydrator',
            'hydrators_namespace'     => 'DoctrineMongoDBHydrator',
            'auto_generate_hydrators' => true,
            'metadata_cache'          => new \Doctrine\Common\Cache\ArrayCache(),
            'logger_callable'         => null,
        );

        foreach ($defaults as $key => $value) {
            if (!isset($app['doctrine.odm.mongodb.' . $key])) {
                $app['doctrine.odm.mongodb.' . $key] = $value;
            }
        }
    }

    private function loadDoctrineMongoDBConfiguration(Application $app)
    {
        $app['doctrine.odm.mongodb.configuration'] = $app->share(function () use ($app) {
            $config = new Configuration;

            $config->setMetadataCacheImpl($app['doctrine.odm.mongodb.metadata_cache']);

            if (isset($app['doctrine.odm.mongodb.connection_options']['database'])) {
                $config->setDefaultDB($app['doctrine.odm.mongodb.connection_options']['database']);
            }

            $chain = new MappingDriverChain();
            $usingAnnotations = false;
            foreach ((array)$app['doctrine.odm.mongodb.documents'] as $document) {
                switch ($document['type']) {
                    case 'annotation':
                        $driver = AnnotationDriver::create((array)$document['path']);
                        $chain->addDriver($driver, $document['namespace']);
                        $usingAnnotations = true;
                        break;
                    case 'yml':
                        $driver = new YamlDriver((array)$document['path'], '.yml');
                        $chain->addDriver($driver, $document['namespace']);
                        break;
                    case 'xml':
                        $driver = new XmlDriver((array)$document['path'], '.xml');
                        $chain->addDriver($driver, $document['namespace']);
                        break;
                    default:
                        throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $document['type']));
                        break;
                }

                // add namespace alias
                if (isset($document['alias'])) {
                    $config->addDocumentNamespace($document['alias'], $document['namespace']);
                }
            }

            if ($usingAnnotations) {
                AnnotationDriver::registerAnnotationClasses();
            }

            $config->setMetadataDriverImpl($chain);

            $config->setProxyDir($app['doctrine.odm.mongodb.proxies_dir']);
            $config->setProxyNamespace($app['doctrine.odm.mongodb.proxies_namespace']);
            $config->setAutoGenerateProxyClasses($app['doctrine.odm.mongodb.auto_generate_proxies']);

            $config->setHydratorDir($app['doctrine.odm.mongodb.hydrators_dir']);
            $config->setHydratorNamespace($app['doctrine.odm.mongodb.hydrators_namespace']);
            $config->setAutoGenerateHydratorClasses($app['doctrine.odm.mongodb.auto_generate_hydrators']);

            $config->setLoggerCallable($app['doctrine.odm.mongodb.logger_callable']);

            return $config;
        });
    }

    private function loadDoctrineMongoDBConnection(Application $app)
    {
        $app['doctrine.mongodb.connection'] = $app->share(function () use ($app) {
            return new Connection($app['doctrine.odm.mongodb.connection_options']['host'], 
                isset($app['doctrine.odm.mongodb.connection_options']['options']) 
                    ? $app['doctrine.odm.mongodb.connection_options']['options']
                    : array(),
                $app['doctrine.odm.mongodb.configuration']);
        });
    }

    private function loadDoctrineMongoDBDocumentManager(Application $app)
    {
        $app['doctrine.odm.mongodb.event_manager'] = $app->share(function () use ($app) {
            return new EventManager;
        });

        $app['doctrine.odm.mongodb.dm'] = $app->share(function () use ($app) {
            return DocumentManager::create(
                $app['doctrine.mongodb.connection'], $app['doctrine.odm.mongodb.configuration'], $app['doctrine.odm.mongodb.event_manager']
            );
        });
    }
}
