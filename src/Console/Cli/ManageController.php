<?php

namespace Core\Console\Cli;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class ManageController extends \Core\Console\CoreController
{
    CONST DESCRIPTION   = 'Cli system management';

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    /**
     * @return
     */
    public function startAction()
    {
        $command = $this->params()->fromRoute("command", "create");
        if(method_exists($this, $command."Action"))
        {
            $method = $command."Action";
            return call_user_func(array($this, $method));
        }
    }
    public function createAction()
    {   
       $module = $this->params()->fromRoute("module");
       if(!isset($module))
       {
          $this->getLogger()->fatal("You must specify a module");
       }
       $module = ucfirst($module);

       $modules = $this->sm->get("ApplicationConfig")["modules"];
       if(!in_array($module, $modules))
       {
            if(!$this->getModuleManager()->exists($module))
            {
                $this->getLogger()->fatal("Module ".$module." doesn't exist");
            }
            $this->getModuleManager()->lightLoad($module);
       }
       $name = $this->params()->fromRoute("name");
       if(!isset($name))
       {
          $this->getLogger()->fatal("You must specify a name");
       }
       $data = explode("/", $name);
       if(count($data) != 2)
       {
          $this->getLogger()->fatal("Name must follow the format: folder/controller");
       }
       $folder = ucfirst($data[0]);
       $name = ucfirst($data[1]);
       $description = $this->params()->fromRoute("description", "");

       $paths = array_map(function($path){return join_paths(ROOT_PATH, $path);},$this->getModuleManager()->getPaths());

       $module_path = NULL;
       foreach($paths as $path)
       {
            if(file_exists(join_paths($path, $module,"src")))
            {   
                $module_path = join_paths($path, $module,"src");
                break;
            }
       }
       if(!isset($module_path))
       {
            $this->getLogger()->fatal("Module path of ".$module." not found");
       }

       $module_config = join_paths($path, $module, "config","module.config.php");
       if(!file_exists($module_config))
       {
         $this->getLogger()->fatal("module.config.php ".$module." not found");
       }
       $auto_config = join_paths($path, $module, "config","auto.config.php");
       if(!file_exists($auto_config))
       {
         $module_config = join_paths($path, $module, "Module.php");
         if(!file_exists($module_config))
         {
           $this->getLogger()->fatal("Module.php ".$module." not found");
         } 

         $this->getLogger()->info("Create auto.config.php");
         $merge_config =  join_paths($path, $module, "config","merge.config.php");

         $content = file_get_contents($module_config);
         $content = $this->replace($content, ["module.config.php"=>"merge.config.php"]);
         file_put_contents($auto_config, "<?php return ".var_export([], True).";");
         file_put_contents($merge_config, "<?php return smart_merge(include('module.config.php'), include('auto.config.php'));");
         file_put_contents($module_config, $content);
       }

       $config = include($auto_config);
       if(!isset($config["controllers"]))
       {
            $config["controllers"] = [];
       }
       if(!isset($config["controllers"]["invokables"]))
       {
            $config["controllers"]["invokables"] = [];
       }
       $config["controllers"]["invokables"][$module.'\Console\\'.$folder.'\\'.$name] = $module.'\Console\\'.$folder.'\\'.$name.'Controller';

        file_put_contents($auto_config, "<?php return ".var_export($config, True).";");


       $console_path = join_paths($module_path, "Console", $folder);

       if(!file_exists($console_path))
       {
            mkdir($console_path, 0777, True);
       }
       $file_path = join_paths($console_path, $name."Controller.php");
       if(file_exists($file_path))
       {
            $this->getLogger()->fatal($folder."/".$name." already exists");
       }
       $content = file_get_contents(join_paths(dirname(__FILE__), "CliTemplate.php"));
       $content = $this->replace($content, ["%module%"=>$module,"%name%"=>$name, "%description%"=>$description,"%folder%"=>$folder]);
       file_put_contents($file_path, $content);

    }
    protected function replace($content, $replacements = [])
    {
        foreach($replacements as $key=>$value)
        {
            $content = str_replace($key, $value, $content);
        }
        return $content;
    }
    protected function getFiles($folder, $filter = NULL)
    {
        if(isset($filter) && !is_array($filter))
        {
            $filter  = [$filter];
        }
        return array_filter(scandir($folder), function($path)
        {
            if(in_array($path, [".",".."]))
            {
                return False;
            }
            if(!empty($filter))
            {
                foreach($filter as $test)
                {
                    if(strpos($path, $test) !== False)
                    {
                        return True;
                    }
                }
                return False;
            }
            return True;
        });
    }
    protected function getModuleManager()
    {
        return $this->sm->get("Module");
    }
}
