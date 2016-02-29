<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

return array(
    'router' => array(
        'routes' => array(
            'application' => array(
                'type'    => 'Segment',
                'options' => array(
                    'route'    => '/login',
                    'constraints' => array(
                       // 'usertype' => '[a-zA-Z-]+'
                    ),
                    'defaults' => array(
                        'module' => 'application',
                         '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'Index',
                        'action'        => 'home',
                       'usertype' => 'visitor'

                    )
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'api' => array(
                        'type' => 'Segment',
                        'options' => array(

                            'route'    => '/api/:cont[/:act][/:id]',
                            'defaults' => array(
                                '__NAMESPACE__' => 'Core\Controller',
                                'controller' => 'api',
                                'action'     => 'index'
                            ),
                        ),
                        'may_terminate'=>True
                    ),
                    )
                )
        )
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'invokables'=>array(
            'Email' => 'Core\Service\Email',
            'Notifications'=>'Core\Service\Notifications',
            'Spreadsheet'=>'Core\Service\Spreadsheet',
            'Geolocation'=>'Core\Service\Geolocation',
            'Env'=>'Core\Service\Env',
            'AppConfig'=>'Core\Service\Configuration',
            'API'=>'Core\Service\API',
            'QueueService' => 'Core\Service\Queue',

        ),
        'services' => array(

        ),
        'aliases' => array(

            'ErrorController' => '\Core\Controller\ErrorController'
        ),
        'initializers' => array(
            'ServiceManagerIntializer' => '\Core\Service\Manager\ServiceManagerInitializer'
        )

    ),
    'translator' => array(
        'locale' => 'en_US',
        'translation_file_patterns' => array(
            array(
                'type'     => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.mo',
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Core\Controller\CoreController' => 'Core\Controller\CoreController',
            'Core\Controller\Api' => 'Core\Controller\APIController',
            'Core\Console\Queue\Listen' => 'Core\Console\Queue\ListenController',
            'Core\Console\Queue\Test' => 'Core\Console\Queue\TestController',
          /*  'Application\Controller\CoreController' => 'Application\Controller\CoreController',
            'Application\Controller\FrontController' => 'Application\Controller\FrontController'*/
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
        'strategies'=>
            array(
                //allow json output
                'ViewJsonStrategy'

            )
    ),
    'view_helpers' => array(
        'invokables' => array(
            'modelScript' => 'Core\ViewHelper\ModelScript',
            'cacheFile' => 'Core\ViewHelper\CacheFileHelper',
            'urlApp' => 'Application\ViewHelper\UrlApplication'
            // more helpers here ...
        )
    ),
    'beanstalkd' => [
        'ip'            => '127.0.0.1',
        'port'          => 11300,
        'retry_count'   => 3
    ],
    'apis' => array(
        "linkedIn" => array("class"=>'\Core\Service\Api\LinkedIn')
    ),
    // Placeholder for console routes
    'console' => array(
        'router' => array(
            'routes' => array(
            ),
        ),
    ),
    'roles' => array(
        "user" => array(
            "user_login",
            "user_logadd"
        ),
        "admin" => array(

            "user"
        )

    )
);
