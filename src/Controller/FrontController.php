<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 14:51
 */

namespace Core\Controller;


use Core\Exception\FatalException;
use Core\Model\FlashModel;
use Zend\EventManager\StaticEventManager;
use Zend\View\Model\JsonModel;
use Zend\View\ViewEvent;

class FrontController extends CoreController
{

    /**
     * @var \Core\Service\MultipleIdentity
     */
    protected $identity;
    /**
     * @var \Core\Service\Session
     */
    protected $session;
    /**
     * @var \Core\Service\ACL
     */
    protected $acl;
    /**
     * @var \Core\Model\FlashModel object
     */
    protected $flash;
    public function __construct()
    {


    }

    /**
     * Initializes view variables
     * @param ViewEvent $event
     */
    protected function initView(\Zend\View\ViewEvent $event)
    {
        //disable for json
        if(!($event->getModel() instanceof JsonModel))
        {
            if($event->getModel()->getVariable("acl", NULL) === NULL)
                $event->getModel()->setVariable("acl", $this->acl);
            if($event->getModel()->getVariable("identity", NULL) === NULL)
                $event->getModel()->setVariable("identity", $this->identity);
            if($event->getModel()->getVariable("js_constant", NULL) === NULL)
                $event->getModel()->setVariable("js_constant", $this->sm->get("AppConfig")->get("javascript"));
            if(($this->identity->isLoggued() && $this->identity->user->isAdmin()))
            {
                $config = $this->sm->get("AppConfig")->getConfiguration();
                if(isset($config["last_update"]))
                {
                    $event->getModel()->setVariable("last_update", $config["last_update"]);
                }
            }
        }else
        {
            if($this->isDebug())
            {
                $event->getModel()->setVariable("id_user", $this->identity->isLoggued()?$this->identity->user->id:null);
            }
        }
    }
    protected function isMobiskill()
    {
        return in_array($_SERVER['REMOTE_ADDR'], array("128.79.4.185","109.26.232.254","127.0.0.1","::1"));
    }
    protected function init(\Zend\Mvc\MvcEvent $event)
    {
        $this->session = $this->sm->get("session");



        $this->session->setServiceLocator($this->sm);
        parent::init($event);


        //inject variables into view scripts
        $events = $this->getEventManager()->getSharedManager();
        $events->attach('Zend\View\View', ViewEvent::EVENT_RENDERER_POST, function(\Zend\View\ViewEvent $event)
        {
           return $this->initView($event);
        });

        //ACL
        $this->acl = $this->sm->get("ACL");
        $config =  $this->sm->get("AppConfig")->getConfiguration();
        if(array_key_exists("roles", $config))
        {
            foreach($config["roles"] as $role=>$roles)
            {
                $this->acl->add_role_definition($role, $roles);
            }
        }


        $router = $this->sm->get('router');
        $request = $this->sm->get('request');
        $matchedRoute = $router->match($request);

        $params = $matchedRoute->getParams();
        if(array_key_exists("roles", $params))
        {
            foreach($params["roles"] as $category => $roles)
            {
                if(!empty($roles))
                {
                    if(is_string($roles))
                    {
                        $roles = array($roles);
                    }
                    for($i=0; $i<count($roles); $i++)
                    {
                        $role = $roles[$i];
                        $this->acl->add($role, $category);
                    }
                    /*dd($roles);
                    foreach($roles as $role)
                    {
                        
                    }*/
                    
                }
            }
        }

        if($this->identity->isLoggued())
        {
            $this->identity->addRoleToUser();
        }

    }

    /**
     * Checks ACL and registration complete
     * @return \Zend\Http\Response
     * @throws FatalException
     */
    protected function postInit(\Zend\Mvc\MvcEvent $event)
    {
        parent::postInit($event);
        $acl_return = $this->checkACL();

        if($acl_return !== NULL)
        {
            return $this->redirect()->toURL($acl_return);
        }
        //test registration
        if($this->identity->isLoggued())
        {
            $result = $this->shortCircuit();
            if(isset($result))
            {
                return $result;
            }
        }
    }
    protected function shortCircuit()
    {
        //call before route
    }
    protected function addFlash($text, $type = FlashModel::TYPE_MESSAGE)
    {
        if(!isset($this->flash))
        {
            $this->flash = $this->getFlashes();
        }
        $this->flash[] = new FlashModel($text, $type);
        $this->session->flash()->list = $this->flash;
    }

    /**
     * @return mixed
     */
    protected function getFlashes()
    {
        if(isset($this->flash))
        {
            return $this->flash;
        }
        if(isset($this->session->flash()->list))
        {
            $this->flash = $this->session->flash()->list;
        }else
        {
            $this->flash = array();
        }
        return $this->flash;
    }
    public function flushFlashes()
    {
        unset($this->session->flash()->list);
        $this->flash = array();
    }
    protected function checkACL()
    {
        if(!$this->acl->is_allowed())
        {
            throw new FatalException("You are not allowed to be on this page");
        }
    }

} 
