<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 16:11
 */

namespace Core\Service;

use Core\Core\CoreObject;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;

class CoreService extends CoreObject ////implements ServiceLocatorAwareInterface
{
    /**
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $sm;
    /**
     * @var \Core\Service\Session
     */
    protected $session;
    protected $initialized = False;
    public function __construct()
    {

    }
    protected function init()
    {

    }
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        if($this->initialized)
        {
            return;
            throw new \Exception("already initialized");
        }
        $this->sm = $serviceLocator;
        $this->session = $this->sm->get("session");
        if($this->session->getServiceLocator() === NULL)
        {
            $this->session->setServiceLocator($this->sm);
        }
        $this->init();
        $this->initialized = True;
    }

    /**
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->sm;
    }
} 
