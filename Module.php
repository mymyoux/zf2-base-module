<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Core;

use Application\Model\CabinetModel;
use Application\Table\CabinetTable;
use Application\Table\UserTable;
use Core\Table\TranslationTable;
use Core\Model\TokenModel;
use Core\Model\UserModel;
use Core\Table\ErrorTable;
use Core\Table\AskTable;
use Core\Table\DetectLanguageTable;
use Core\Table\MailTable;
use Core\Table\RoleTable;
use Core\Table\PictureTable;
use Core\Table\ABTable;
use Core\Table\CronTable;
use Core\Table\TokenTable;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Db\ResultSet\ResultSet;
use Core\Table\TableGateway;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Role\GenericRole;
use Zend\Permissions\Acl\Resource\GenericResource;
use Zend\View\Model\JsonModel;
use Zend\View\ViewEvent;
use Core\Table\Cron\LogTable;

use Core\Table\BeanstalkdLogTable;
use Core\Table\StatsTable;

class Module
{

    public function onBootstrap(MvcEvent $e)
    {
      
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);


        //handle the dispatch error (exception)

        $eventManager->attach(\Zend\Mvc\MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'handleError'));
        //handle the view render error (exception)
        $eventManager->attach(\Zend\Mvc\MvcEvent::EVENT_RENDER_ERROR, array($this, 'handleRenderError'));

    }
    private function redirectError(MVcEvent $e)
    {
        if(!$e->getRequest() instanceof \Zend\Console\Request && $e->getRequest()->isXmlHttpRequest())
        {
            $view = new JsonModel();
            $exception = $e->getParam('exception');
            if(isset($exception))
            {
                $view->setVariable("error",$exception->getMessage());
            }else
            {
                $view->setVariable("error","unknown_error");
            }
            $e->setViewModel($view);
            $e->getResponse()->setStatusCode(200);
        }
    }
    public function handleError(MvcEvent $e)
    {
        $this->redirectError($e);
        $errorController = $e->getApplication()->getServiceManager()->get('ErrorHandler');
        return $errorController->handleError($e, $e->getError()=='error-router-no-match' && !$e->getRequest() instanceof \Zend\Console\Request && !$e->getRequest()->isXmlHttpRequest()?404:NULL);
    }
    public function handleRenderError(MvcEvent $e)
    {
        $this->redirectError($e);
        $errorController = $e->getApplication()->getServiceManager()->get("ErrorHandler");
        return $errorController->handleRenderError($e);
    }
    public function getConfig()
    {
        return include __DIR__ . '/config/merge.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/'/* . __NAMESPACE__,*/
                ),
            ),
        );
    }
    public function getServiceConfig()
    {
        $tables = [
            'Core\Table\BeanstalkdLogTable',
            'Core\Table\AppTable',
            'Core\Table\ErrorTable',
            'Core\Table\StatsTable',
            'Core\Table\AskTable',
            'Core\Table\ABTable',
            'Core\Table\PictureTable',
            'Core\Table\DetectLanguageTable',
            'Core\Table\TranslationTable',
            'Core\Table\CronTable',
            'Core\Table\CronLogTable',
            'Core\Table\MailTable',
            'Core\Table\PushTable',
        ];

        $factories = array_reduce($tables, function($previous, $tablename)
        {
            $last = explode('\\', $tablename);
            $previous[$last[count($last)-1]] = function($sm)use($tablename) {
             $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new $tablename(new TableGateway($tablename::TABLE,$dbAdapter, NULL, NULL));
                };
            return $previous;
        }, []);

        $factories['TokenTable'] = function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new TokenModel());
                    return new TokenTable(new TableGateway("user_token",$dbAdapter, NULL, $resultSetPrototype));
                };
        $factories['UserTable'] = function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new UserModel());
                    return new UserTable(new TableGateway("user",$dbAdapter, NULL, $resultSetPrototype));
                };
        $factories['CronLogTable'] = function($sm) {
                      $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new LogTable(new TableGateway(LogTable::TABLE,$dbAdapter, NULL, NULL));
                };
        $factories['Zend\Db\Adapter\Adapter'] = 'Zend\Db\Adapter\AdapterServiceFactory';
        $config = array(
            'factories' => $factories ,
            'services'=>array(
                'Identity' =>   new \Core\Service\Identity(),
                'Session' =>   new \Core\Service\Session(),
                'APIManager' => new \Core\Service\ApiManager(),
                'ErrorHandler' => new \Core\Service\ErrorHandler(),
                'ACL' => new \Core\Service\ACL,
                'translator' => new \Core\Service\Translator(),
            )
        );
        return $config;
    }
    /**
     * This method is defined in ConsoleBannerProviderInterface
     */
    public function getConsoleBanner(Console $console){
        return "Core Module v0.1";
    }
    public function isDebug()
    {
        if(!array_key_exists("HTTP_HOST", $_SERVER))
        {
            return False;
        }
        return mb_strpos($_SERVER['HTTP_HOST'], ".local")!==FALSE;
    }
}
