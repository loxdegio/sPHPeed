<?php

namespace sPHPeed;

use DI\Container;
use DI\ContainerBuilder;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use FastRoute\DataGenerator;
use FastRoute\DataGenerator\GroupCountBased as GroupCountBasedDataGenerator;
use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use FastRoute\RouteParser;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Neat\Http\Request;
use Neat\Http\Response;
use function DI\create;
use function FastRoute\cachedDispatcher;


class App {

    /**
     * settings array
     * 
     * @var array
     */
    private $settings;

    /**
     * DI container
     *
     * @var DI\Container
     */
    private $container;

    /**
     * @var FastRoute\RouteCollector
     */
    private $routeCollector;

    public function __construct(array $settings) {
        $this->settings = $settings;
        $builder = new ContainerBuilder();

        $this->buildContainer($builder, $settings);

        $this->routeCollector = $this->container->get(RouteCollector::class);
        
    }

    private function buildContainer(ContainerBuilder $builder) {
        /**
         * @var App
         */
        $app = $this;
        $builder->addDefinitions([
            EntityManager::class => function() use ($app) {
                $config = Setup::createAnnotationMetadataConfiguration($app->settings['db']['entity_dir'], $app->settings['db']['dev_mode']);
                return EntityManager::create($app->settings['db']['configuration'], $config);
            },
            Request::class => function() {
                return Request::capture();
            },
            Dispatcher::class => function() use ($app) {
                if (!IS_DEBUG_ENABLED && !isset($app->settings['routing']['cacheFile'])) {
                    throw new \LogicException('Must specify "cacheFile" option');
                }
        
                if (!IS_DEBUG_ENABLED && file_exists($app->settings['routing']['cacheFile'])) {
                    $dispatchData = require $app->settings['routing']['cacheFile'];
                    if (!is_array($dispatchData)) {
                        throw new \RuntimeException('Invalid cache file "' . $app->settings['routing']['cacheFile'] . '"');
                    }
                    return new GroupCountBasedDispatcher($dispatchData);
                }
        
                /** @var RouteCollector $routeCollector */
                $dispatchData = $app->routeCollector->getData();
                if (!IS_DEBUG_ENABLED) {
                    file_put_contents(
                        $app->settings['routing']['cacheFile'],
                        '<?php return ' . var_export($dispatchData, true) . ';'
                    );
                }
        
                return new GroupCountBasedDispatcher($dispatchData);
            },
            Logger::class => function() use ($app) {
                // Create the logger
                $logger = new Logger($app->settings['log']['log_name']);
                // Now add some handlers
                $logger->pushHandler(new StreamHandler($app->settings['log']['log_file'], IS_DEBUG_ENABLED? Logger::DEBUG : Logger::INFO));

                return $logger;
            },
            'Twig' => function() use($app) {
                $loader = new Twig_Loader_Filesystem($app->settings['twig']['template_dir']);
                $twig = new Twig_Environment($loader, [
                    'cache' => $app->settings['twig']['cache_dir'],
                ]);
                return $twig;
            }
        ]);

        if(!IS_DEBUG_ENABLED) {
            $builder->enableCompilation(CACHE_DIR);
            $builder->writeProxiesToFile(true, CACHE_DIR.DS.'proxies');
            $builder->enableDefinitionCache();
        }

        $this->container = $builder->build();

        $this->container->set(DataGenerator::class, create(GroupCountBasedDataGenerator::class));
        $this->container->set(RouteCollector::class, create(Std::class));
        $this->container->set('twig', create('Twig'));
        $this->container->set('db', create(EntityManager::class));
    }

    public function getContainer() {
        return $this->container;
    }

    public function addRoute($method, $routePattern, $handler) {
        $this->routeCollector->addRoute($method, $routePattern, $handler);
    }

    public function run() {
        $dispatcher = $this->container->get(Dispatcher::class);
        
        // Fetch method and URI from somewhere
        /**
         * @var Neat\Http\Request
         */
        $request = $this->container->get(Request::class);
        $response = new Response();
        
        $routeInfo = $dispatcher->dispatch($request->method(), $request->url());
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response->setStatus(404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                $response->setStatus(405);
                break;
            case Dispatcher::FOUND:
                $handler = explode('::', $routeInfo[1]);
                $vars = $routeInfo[2];
                $vars['request'] = $request;
                $vars['response'] = $response;
                $obj = $this->container->get($handler[0]);
                $obj->setContainer($this->container);
                call_user_func('sPHPeed\\Controller\\'.$handler[1], $obj, $vars);
                break;
        }

        $response->send();

    }
}