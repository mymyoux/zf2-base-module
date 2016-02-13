<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 30/09/2014
 * Time: 21:00
 */

namespace Core\Service\Manager;



use Zend\ServiceManager\InitializerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ServiceManagerInitializer implements InitializerInterface
{
    public function initialize($instance, ServiceLocatorInterface $serviceLocator)
    {
        if($instance instanceof \Zend\ServiceManager\ServiceLocatorAwareInterface)
        {
           // $instance->setServiceLocator($serviceLocator);
        }
    }
} 