<?php
namespace Core\ServiceManager;

use Zend\ServiceManager\ServiceManager as BaseServiceManager;

class ServiceManager extends BaseServiceManager
{
    protected $autoAddInvokableClass = true;
    protected $allowOverride = true;

    public function get($name, $options = array(), $usePeeringServiceManagers = true)
    {
        // Allow specifying a class name directly; registers as an invokable class
        if (!$this->has($name) && $this->autoAddInvokableClass && class_exists($name)) {
            $this->setInvokableClass($name, $name);
        }
        $service = parent::get($name, $options, $usePeeringServiceManagers);
        if(isset($service) && is_object($service) && method_exists($service, "setServiceLocator"))
        {
            $sm = $service->getServiceLocator();
            if(!isset($sm))
            {
                $service->setServiceLocator($this);
            }
        }
        return $service;
    }


}
