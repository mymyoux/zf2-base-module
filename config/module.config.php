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

            'picture' => array(
                'type'    => 'Segment',
                'options' => array(
                    'route'    => '/picture/:width/:height[/:dpi][/:extension]',
                    'constraints' => array(
                         'width' => '[0-9]+',
                         'height' => '[0-9]+',
                         'extension' => '[a-z]+',
                         'dpi' => '[0-9\.]+'
                    ),
                    'defaults' => array(
                        'module' => 'core',
                        '__NAMESPACE__' => 'Core\Controller',
                        'controller'    => 'picture',
                        'action'        => 'index',
                        'dpi' =>1

                    )
                )
            ),
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
                      'replay_error' => array(
                            'type'    => 'Segment',
                            'options' => array(
                                'route'    => '/replay/:id_error[/:url]',
                                'constraints' => array(
                                   // 'usertype' => '[a-zA-Z-]+'
                                ),
                                'defaults' => array(
                                    'module' => 'application',
                                     '__NAMESPACE__' => 'Core\Controller',
                                    'controller'    => 'error',
                                    'action'        => 'replay',
                                   'usertype' => 'admin',
                                      'roles'=>array("main"=>array("admin"))

                                ),
                                 'constraints' => array(
                                        'id_error'     => '[0-9]+'
                                    ),
                            ),
                            'may_terminate'=>True,
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
            'Picture' => 'Core\Service\Picture',
            'Email' => 'Core\Service\Email',
            'Push' => 'Core\Service\Push',
            'Notifications'=>'Core\Service\Notifications',
            'Spreadsheet'=>'Core\Service\Spreadsheet',
            'Geolocation'=>'Core\Service\Geolocation',
            'Env'=>'Core\Service\Env',
            'AppConfig'=>'Core\Service\Configuration',
            'Excel'=>'Core\Service\Excel',
            'API'=>'Core\Service\API',
            'QueueService' => 'Core\Service\Queue',
            'DetectLanguage' => 'Core\Service\DetectLanguage',
            'Log'=>'Core\Service\Log',
            'CSV' => 'Core\Service\CSV',
            'ABTesting' => 'Core\Service\ABTesting',
            'Module' => 'Core\Service\Module',
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
            'Core\Controller\Picture' => 'Core\Controller\PictureController',
            'Core\Controller\User' => 'Core\Controller\UserController',
            'Core\Controller\Ask' => 'Core\Controller\AskController',
            'Core\Controller\AB' => 'Core\Controller\ABController',
            'Core\Controller\Error' => 'Core\Controller\ErrorController',
            'Core\Console\Queue\Listen' => 'Core\Console\Queue\ListenController',
            'Core\Console\Queue\Replay' => 'Core\Console\Queue\ReplayController',
            'Core\Console\Queue\Test' => 'Core\Console\Queue\TestController',
            'Core\Console\Cli\Manage' => 'Core\Console\Cli\ManageController',
            'Core\Console\Core\Crontab' => 'Core\Console\Core\CrontabController',
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
        'linkedIn' => array(
                'login' => True,
                'multi' => False,
                'sharable'=>False,
                'class'=>'\Core\Service\Api\LinkedIn',
        ),
        'facebook'=> array(
            'login' => True,
            'multi' => False,
            'sharable'=>False,
            'class'=>'\Core\Service\Api\Facebook',
        ),
        'twitter'=> array(
            'login' => True,
            'multi' => False,
            'sharable'=>False,
            'class'=>'\Core\Service\Api\Twitter',
        ), 
        'google'=> array(
            'login' => True,
            'multi' => False,
            'sharable'=>False,
            'by_app'=>True,
            'class'=>'\Core\Service\Api\Google',
        )
    ),
        // Placeholder for console routes
    'console' => array(
        'router' => array(
            'routes' => array(
                'catchall-route' => array(
                'type'     => 'catchall',
                'options' => array(
                    'route'    => '',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Core\Console\Core',
                        'controller'    => 'crontab',
                        'action'        => 'generik'
                    )
                )
            )
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
