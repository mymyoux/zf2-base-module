<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 06/10/2014
 * Time: 23:11
 */

namespace Core\Service\Api;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractCoreAts
{
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;

        $this->init();
    }

    public function getServiceLocator()
    {
        return $this->sm;
    }
}
