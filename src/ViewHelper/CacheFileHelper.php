<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 20/10/14
 * Time: 12:30
 */

namespace Core\ViewHelper;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Helper\AbstractHelper;

/**
 * Class TypeToRouteHelper
 * Helps to find route from current type
 * @package Application\ViewHelper
 */
class CacheFileHelper extends AbstractHelper  implements ServiceLocatorAwareInterface{

    private $config;
    private $files;
    private $basePath;
    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return CustomHelper
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }
    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
    public function __invoke($file)
    {
        $sm = $this->getServiceLocator()->getServiceLocator();
        if(!isset($this->basePath))
        {
            $this->basePath = $sm->get('viewhelpermanager')->get("basePath");
        }
        $basePath = $this->basePath;
        $str = $basePath().$file;

        if(!isset($this->files) && !isset($this->config))
        {
            $this->config = $sm->get("AppConfig")->getConfiguration();
            if(isset($this->config["cache_helper"]["files"]))
            {
                $this->files = $this->config["cache_helper"]["files"];
            }
        }
        if(isset($this->files))
        {
            if(isset($this->files["public".$file]))
            {
                if(isset($this->files["public".$file]["file"]))
                {
                    $str = $basePath().mb_substr($this->files["public".$file]["file"], 6);
                }
                if($sm->get("Identity")->isLoggued() && $sm->get("Identity")->user->isAdmin() && isset($this->files["public".$file]["file_with_map"]))
                {
                    $str = $basePath().mb_substr($this->files["public".$file]["file_with_map"], 6);
                }
                $str.="?v=".$this->files["public".$file]["label"];
            }
        }
        return $str;
    }
}
