<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 14:45
 */

namespace Core\Controller;
use Zend\Http\Request;
use Zend\Mvc\Exception;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class CoreController
 * All controllers inherit from this class
 * @package Core\Controller
 */
class CoreController  extends AbstractActionController
{
    /**
     * @var \Application\Service\API
     */
    protected $api;
    /**
     * Service manager's instance
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;
    /**
     * API Manager
     * @var \Core\Service\ApiManager
     */
    public $apis;

    /**
     * @var \Core\Service\MultipleIdentity
     */
    protected $identity;
    /**
     * @var \Core\Service\Translator
     */
    protected $translator;

    /**
     * Constructor
     */
    public function __construct()
    {


    }
    public function indexAction()
    {
        dd($this->identity);
    }

    /**
     * Function called on startup
     * @throws \Exception
     */
    protected function init(\Zend\Mvc\MvcEvent $event)
    {

        $this->apis = $this->sm->get("APIManager");
        $this->apis->setServiceLocator($this->sm);
        $this->identity = $this->sm->get("Identity");
        $this->identity->setServiceLocator($this->sm);
        $acl = $this->sm->get("ACL");
        $acl->setServiceLocator($this->sm);
        $this->identity->setACL($acl);

        //$this->api = $this->sm->get("API");


        $this->translator = $this->sm->get("translator");
        $this->translator->setServiceLocator($this->sm);


            $this->api = $this->sm->get("API");

    }

    /**
     * Called right before init function
     */
    protected function postInit(\Zend\Mvc\MvcEvent $event)
    {

    }

    /**
     * Set serviceManager instance
     *
     * @param  ServiceLocatorInterface $serviceLocator
     * @return void
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        parent::setServiceLocator($serviceLocator);
       // $this->sm = $serviceLocator;"
       // $this->init();
    }

    /**
     * Override default getMethodAction
     * @param string $action
     * @param string $suffix
     * @return mixed|string
     */
    public static function getMethodFromAction($action, $suffix="Action")
    {
        $method  = str_replace(array('.', '-', '_'), ' ', $action);
        $method  = ucwords($method);
        $method  = str_replace(' ', '', $method);
        $method  = lcfirst($method);
        $method .=  $suffix;

        return $method;
    }
    /**
     * (non-PHPdoc)
     * @param $e MvcEvent
     * @see \Zend\Mvc\Controller\AbstractActionController::onDispatch()
     */
    public function onDispatch(MvcEvent $e)
    {
        $this->sm = $this->getServiceLocator();

        if(($result = $this->init($e)) !== NULL)
        {
            return $result;
        }
        if(($result = $this->postInit($e)) !== NULL)
        {
             return $result;
        }

        $method = $this->getMethod($e);

        $actionResponse = $this->$method();

        $e->setResult($actionResponse);

        return $actionResponse;
    }
    protected function getMethod(MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();
        if (!$routeMatch) {
            /**
             * @todo Determine requirements for when route match is missing.
             *       Potentially allow pulling directly from request metadata?
             */
            throw new Exception\DomainException('Missing route matches; unsure how to retrieve action');
        }

        $action = $routeMatch->getParam('action', 'not-found');

        $suffix = $routeMatch->getParam("action_suffix", "Action");
        $method = static::getMethodFromAction($action, $suffix);
        $httpmethod = $routeMatch->getParam('method');
        if(!isset($httpmethod) && method_exists($e->getRequest(), "getMethod"))
        {
            $httpmethod = $e->getRequest()->getMethod();
        }
        if(isset($httpmethod))
        {
            $httpmethod = $method.$httpmethod;

            if (method_exists($this, $httpmethod)) {
                $method = $httpmethod;
            }
        }
        //remove from Post on console
        if(!($e->getRequest() instanceof \Zend\Console\Request))
        {
            $requestMethod = $this->params()->fromQuery("method", $this->params()->fromPost("method", NULL));
        }
        if(isset($requestMethod))
        {
            $requestMethod = $method.$requestMethod;

            if (method_exists($this, $requestMethod)) {
                $method = $requestMethod;
            }
        }
        if (!method_exists($this, $method)) {
            $method = 'notFoundAction';
        }
        return $method;
    }

    public function isDebug()
    {
        if(isset($this->identity) && $this->identity->isLoggued())
        {
            if($this->identity->user->isAdmin())
            {
                return true;
            }
        }
       return $this->isLocal();
    }
    public function isLocal()
    {
        return $this->sm->get("AppConfig")->isLocal();
        /*if(!array_key_exists("HTTP_HOST", $_SERVER))
        {
            return False;
        }
        return mb_strpos($_SERVER['HTTP_HOST'], ".local")!==FALSE;*/
    }
    public function getEnv()
    {
        return $this->sm->get("AppConfig")->getEnv();
        /*if(!array_key_exists("HTTP_HOST", $_SERVER))
        {
            return False;
        }
        return mb_strpos($_SERVER['HTTP_HOST'], ".local")!==FALSE;*/
    }
}
