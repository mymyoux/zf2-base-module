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
    private $files_laravel;
    private $laravel_url;
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
    protected function _laravel($file, $link = true)
    {
        $sm = $this->getServiceLocator()->getServiceLocator();
          if(!isset($this->files_laravel))
        {
            $javascript = $sm->get("AppConfig")->get('javascript');
            $this->laravel_url = $javascript["laravel"];
            $folder = $sm->get("AppConfig")->get('laravel_folder');
            $folder = join_paths($folder, "storage/framework/cache/assets.php");
            if(file_exists($folder))
                $this->files_laravel = require $folder;
            if(empty($this->files_laravel))
            {
                $this->files_laravel = [];
            }
        }
        $original = $file;
        $prefix = "";
        if(starts_with($file, '/js/'))
        {
            $prefix.="js/";
            $file = substr($file, 4);
        }else
        if(starts_with($file, '/css/'))
        {
            $prefix.="css/";
            $file = substr($file, 5);
        }
        if(isset($this->files_laravel[$file]))
        {
            // if($sm->get('AppConfig')->isLocal())
            // {
            //     return $this->laravel_url.$prefix.$file.'?t='.$this->files_laravel[$file]["suffix"];
            // }
            return $this->laravel_url.$prefix.$this->files_laravel[$file]["min"].'?t='.$this->files_laravel[$file]["suffix"];
        }
        if($link)
        {
             return $this->laravel_url.$prefix.$file;
        }
        //TODO:uncomment
        // if(ends_with($original, ".css"))
        // {
        //     return $this->laravel_url.substr($original,1);
        // }
        return $original;
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
                if(isset($this->files["public".$file]["is_link"]))
                {
                
                    return $this->_laravel($file);
                }
                if(isset($this->files["public".$file]["file"]))
                {
                    $str = $basePath().mb_substr($this->files["public".$file]["file"], 6);
                }
                if($sm->get("Identity")->isLoggued() && $sm->get("Identity")->user->isAdmin() && isset($this->files["public".$file]["file_with_map"]))
                {
                    $str = $basePath().mb_substr($this->files["public".$file]["file_with_map"], 6);
                }
                $str.="?v=".$this->files["public".$file]["label"];
            }else {
                return $this->_laravel($file, False);
            }
        }
        return $str;
    }
}
