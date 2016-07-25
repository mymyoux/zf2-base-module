<?php

namespace Core\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\Loader\AutoloaderFactory;
use Zend\ServiceManager\Config;
class Module extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{

    public function __construct()
    {
    }

    public function lightLoad($name)
    {
        $name = ucfirst($name);
         $path = join_paths(ROOT_PATH,'/module/',$name, "Module.php");
         if(!file_exists($path))
         {
             throw new \Exception('bad_module:'.$module,2);
         }
         include_once $path;
         $manager = $this->sm->get("ModuleManager");
         $module_name = $name."\\Module";
         $module_to_load = new $module_name();
         $module_to_load->init($manager);
        AutoloaderFactory::factory($module_to_load->getAutoloaderConfig());
    }
    public function fullLoad($name)
    {
         $name = ucfirst($name);
         $manager = $this->sm->get("ModuleManager");
         $module = $manager->loadModule($name); 
    }
    public function exists($name)
    {
        $name = ucfirst($name);
        $modules = $this->sm->get("ApplicationConfig")["modules"];
        if(!in_array($name, $modules))
        {
              $path = join_paths(ROOT_PATH,'/module/',$name, "Module.php");
              if(!file_exists($path))
              {
                    return False;
              }
        }
        return True;
    }
    public function loaded($name)
    {
        $name = ucfirst($name);
        $modules = $this->sm->get("ApplicationConfig")["modules"];
        return in_array($name, $modules);
    }
    public function getPaths()
    {
        $configuration = $this->sm->get("ApplicationConfig");
        if(isset($configuration["module_listener_options"]["module_paths"]))
        {
            $paths = $configuration["module_listener_options"]["module_paths"];
            return $paths;
        }
        return [];
    }
    public function getAvailableModules()
    {
        $paths = $this->getPaths();
        $modules = [];
        foreach($paths as $path)
        {
            if($path == "./vendor")
            {
                $modules[] = ["name"=>"Core", "path"=>$path];
                continue;
            }
            $modules = array_merge($modules, array_map(function($item) use($path)
                {
                    return ["name"=>$item, "path"=>$path];
                },array_filter(scandir(join_paths(ROOT_PATH, $path)), function($item) use ($path)
            {
                return !in_array($item, [".", ".."]) &&  is_dir(join_paths(ROOT_PATH, $path, $item));   
            })));
        }
        $modules = array_values($modules);
       return $modules;
    }
}
