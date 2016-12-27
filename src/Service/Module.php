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

        $this->loadConfig( $module_to_load );
    }

    public function loadConfig( $module )
    {
      $config_array     = $module->getConfig();

      if (!is_array($config_array)) return;

      if (true === isset($config_array['service_manager']))
      {
        $config           = new Config( $config_array['service_manager'] );
        $config->configureServiceManager( $this->sm );
      }

      if (true === isset($config_array['controllers']))
      {
        $config           = new Config( $config_array['controllers'] );

        foreach ($config->getInvokables() as $name => $invokable) {
            $this->sm->get("ControllerManager")->setInvokableClass($name, $invokable);
        }
      }
    }

    public function fullLoad($name)
    {
        $name = ucfirst($name);
        $manager = $this->sm->get("ModuleManager");
        $module = $manager->loadModule($name);

        $this->loadConfig( $module );
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
    public function getLoaded()
    {
        return $this->sm->get("ModuleManager")->getLoadedModules();
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
        $configuration = $this->sm->get("ApplicationConfig");

        $initial = $configuration["initial_modules"];
        $news = array_diff($configuration["modules"], $configuration["initial_modules"]);
        if(!empty($news))
        {
            $modules = array_values(array_filter($modules, function($item) use($configuration)
            {
                return in_array($item["name"], $configuration["modules"]);
            }));
        }
        //dd($configuration["modules"]);
        usort($modules, function($a, $b) use ($initial, $news)
        {   
            $is_origina = array_search($a["name"], $initial);
            $is_originb = array_search($b["name"], $initial);
            $is_newa = array_search($a["name"], $news);
            $is_newb = array_search($b["name"], $news);

            //both not present or same place
            if($is_newa === $is_newb && $is_origina === $is_originb)
            {
                var_dump(["a"=>$a, "b"=>$b]);
                return 0;
            }
            if($is_newa !== False && $is_newb === False)
            {
                return -1;
            }
            if($is_newb !== False && $is_newa === False)
            {
                return 1;
            }
            if($is_newb !== False && $is_newa !== False)
            {
                return $is_newb - $is_newa;
            }
             if($is_origina !== False && $is_originb === False)
            {
                return 1;
            }
            if($is_originb !== False && $is_origina === False)
            {
                return -1;
            }
            if($is_origina !== False && $is_originb !== False)
            {
                return $is_originb - $is_origina;
            }
            return 0;
        });
       return $modules;
    }
}
