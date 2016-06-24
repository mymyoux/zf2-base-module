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
        return include __DIR__ . '/config/module.config.php';
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
        $config = array(
            'factories' => array(
                'BeanstalkdLogTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new BeanstalkdLogTable(new TableGateway(BeanstalkdLogTable::TABLE,$dbAdapter, NULL, NULL));
                },
                'TokenTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new TokenModel());
                    return new TokenTable(new TableGateway("user_token",$dbAdapter, NULL, $resultSetPrototype));
                },
                'ErrorTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new ErrorTable(new TableGateway("error",$dbAdapter, NULL, NULL));
                },
                'StatsTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new StatsTable(new TableGateway(StatsTable::TABLE_API_CALL,$dbAdapter, NULL, NULL));
                },
                'AskTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new AskTable(new TableGateway(AskTable::TABLE,$dbAdapter, NULL, NULL));
                },
                'MailTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new MailTable(new TableGateway("mail",$dbAdapter, NULL, NULL));
                },
                'DetectLanguageTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new DetectLanguageTable(new TableGateway(DetectLanguageTable::TABLE,$dbAdapter, NULL, NULL));
                },
                'TranslationTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    return new TranslationTable(new TableGateway("translate",$dbAdapter, NULL, NULL));
                },
                'UserTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new UserModel());
                    return new UserTable(new TableGateway("user",$dbAdapter, NULL, $resultSetPrototype));
                },
                'RoleTable' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    return new RoleTable(new TableGateway("user_role",$dbAdapter, NULL, $resultSetPrototype));
                },
                'Zend\Db\Adapter\Adapter'
                => 'Zend\Db\Adapter\AdapterServiceFactory'

            ),
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
