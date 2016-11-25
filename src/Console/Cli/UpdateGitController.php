<?php
/**
 * @author Andreosso Benjamin <benjamin.andreosso@gmail.com>
 * @version 1.0
 */

namespace Core\Console\Cli;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

class UpdateGitController extends \Core\Console\CoreController
{
    CONST DESCRIPTION = 'Update Git project';
    CONST CACHE_FILE = "config/autoload/cache.json";
    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    protected $current_directory;
    protected $config;
    protected $cache;
    protected $options = 
    [
        "checkerrors"=>["desc"=>"Check errors mapping", "choices"=>[0, 1]],
        "pull"=>["desc"=>"Git pull all repositories", "choices"=>[0, 1]],
        "migrate"=>["desc"=>"Migrate all tables", "choices"=>[0, 1]],
        "compile"=>["desc"=>"Compile Javascript/CSS files", "choices"=>[0, 1]],
        "templates"=>["desc"=>"Template calculations", "choices"=>[0, 1, 2]],
        "composer"=>["desc"=>"Update composer", "choices"=>[0, 1]],
        "cachefiles"=>["desc"=>"Minify and cache JS/CSS files", "choices"=>[0, 1, 2]],
        "tests"=>["desc"=>"Unit tests executions", "choices"=>[0, 1]],
        "supervisor"=>["desc"=>"Restart supervisor", "choices"=>[0, 1]],
    ];
    protected $update_config;
    protected function getCurrentConfig()
    {
        $this->update_config = $this->config->get('update-git');
        if(!isset($this->update_config))
        {
            $this->update_config = [];
        }
        $reset_action =  $this->params()->fromRoute("reset-action", False);
        if($reset_action)
        {   
            foreach($this->options as $key=>$value)
            {
                $this->update_config[$key] = 0;
            }
        }
        foreach($this->options as $key=>$value)
        {
            $v = $this->params()->fromRoute($key);
            if(isset($v))
            {
                $this->update_config[$key] = $v;
            }else
            {
                if(!isset($this->update_config[$key]))
                {
                    $this->update_config[$key] = 0;
                }
            }
            if($this->update_config[$key] === true || $this->update_config[$key] === "true")
            {
                $this->update_config[$key] = 1;
            }else
            if($this->update_config[$key] === false || $this->update_config[$key] === "false")
            {
                 $this->update_config[$key] = 0;
            }
            if(!in_array($this->update_config[$key], $value["choices"], true))
            {
                $this->update_config[$key] = 1;
            }
        }
        return $this->update_config;
    }
    /**
     * @return
     */
    public function startAction()
    {
        $this->config = $this->sm->get("AppConfig");

        $help = $this->params()->fromRoute("help", False);
        $noaction = $this->params()->fromRoute("no-action", False);
        if($help || $noaction)
        {
            $this->displayUsage();
            return;
        }
        $crawl_zip = join_paths(ROOT_PATH, "crawl","crawl.zip");
        if(file_exists($crawl_zip))
        {
            unlink($crawl_zip);
        }
        $this->current_directory = getcwd();
        $update_config = $this->getCurrentConfig();
        foreach($update_config as $key=>$value)
        {
            $$key = $value;
        }
        /*
        $checkerrors = $this->params()->fromRoute("checkerrors", $this->config->isProduction()?'1':'0') == 1;
        $pull = $this->params()->fromRoute("pull", 1) == 1;
        $compile = $this->params()->fromRoute("compile", $this->config->isProduction()?'1':'0') == 1;
        $migrate = $this->params()->fromRoute("migrate", 1) == 1;
        $templates = $this->params()->fromRoute("templates", $this->config->isProduction()?'1':'0') == 1;
        $delete_template = $this->params()->fromRoute("templates", 0)  == 2;
        $composer = $this->params()->fromRoute("composer", 1) == 1;
        $cachefiles = $this->params()->fromRoute("cachefiles", $this->config->isProduction()?'1':'0') == 1;
        $delete_cachefiles = $this->params()->fromRoute("cachefiles", 0)  == 2;
        $tests = $this->params()->fromRoute("tests", 1)  == 1;
        $supervisor = $this->params()->fromRoute("supervisor", $this->config->isProduction()?'1':'0') == 1;*/

        $this->loadCache();
        if($checkerrors)
        {
            $this->checkErrors();
        }
        //git pull
        if($pull)
        {
            $this->pullGit();
            $this->pullGit("public/ts/framework");
            $this->pullGit("vendor/Core");
        }
        if($compile)
        {
            $this->compileJS();
            $this->compileCSS();
        }

        //tables migratations
        if($migrate)
        {
            $this->migrate();
        }

        //templates calculations
        if($templates!=2 && $templates)
        {
            $this->templatesCalculation();
        }
        if($templates == 2)
        {
            $this->deleteTemplates();
        }

        if($composer)
        {
            $this->updateComposer();
        }

        //minify-caches files
        if($cachefiles!=2 && $cachefiles)
        {
            $this->cacheFiles();
        }
        if($cachefiles == 2)
        {
            $this->deleteCacheFiles();
        }
        $this->writeCache();

        if($tests)
        {
            $this->executeTests();
        }

        if($supervisor)
        {
            $this->restartSupervisor();
        }
    }
    protected function loadCache()
    {
        if(file_exists(UpdateGitController::CACHE_FILE))
        {
            $cache = json_decode(file_get_contents(UpdateGitController::CACHE_FILE), True);
        }else
        {
            $cache = array();
        }
        //cache helper
        $this->cache = isset($cache["cache_helper"])?$cache["cache_helper"]:array();
        if(!isset($this->cache["files"]))
        {
            $this->cache["files"] = [];
        }
    }
    public function displayUsage()
    {
        $this->getLogger()->info("Usage:");
        $this->getLogger()->normal("php console cli:update-git [--no-action] [--reset-action] [--help] [options...]\tLaunch update script\n");

        $this->getLogger()->info("Options:");
        $this->getLogger()->normal("--help\tShow this message");
        $this->getLogger()->normal("--no-action\tShow current config");
        $this->getLogger()->normal("--reset-action\tCurrent config set to no action but given parameters are used");
        $this->getLogger()->normal("");
        foreach($this->options as $key=>$value)
        {
            $this->getLogger()->normal($key."=".implode("|",$value["choices"])."\t\t\t".$value["desc"]);
        }
        /*
        $this->getLogger()->normal("checkerrors=0|1\tDisable(default except prod)/Enable(default on prod) check errors mapping");
        $this->getLogger()->normal("pull=0|1\t\tDisable/Enable(default) git pull");
        $this->getLogger()->normal("migrate=0|1\tDisable/Enable(default) tables migrations");
        $this->getLogger()->normal("compile=0|1\tDisable(default except prod)/Enable(default on prod) css/js compilation");
        $this->getLogger()->normal("templates=0|1\tDisable(default except prod)/Enable(default on prod) templates recalculation");
        $this->getLogger()->normal("template=2\t\tDelete templates");
        $this->getLogger()->normal("composer=0|1\tDisable/Enable composer update");
        $this->getLogger()->normal("cachefiles=0|1\tDisable(default except prod)/Enable(default on prod) files caching (js/css)");
        $this->getLogger()->normal("cachefiles=2\tRemove files cache");
        $this->getLogger()->normal("tests=0|1\tDisable/Enable composer update");
        $this->getLogger()->normal("supervisor=0|1\tDisable/Enable restart of queue (via supervisor)\n");*/

        $this->getLogger()->info("Current configuration:");
        $config_update = $this->getCurrentConfig();
        foreach($config_update as $key=>$value)
        {
            $this->getLogger()->normal($key."=".$value);
        }
        
        /*
        $checkerrors = $this->params()->fromRoute("checkerrors", $this->config->isProduction()?'1':'0') == 1;
        $pull = $this->params()->fromRoute("pull", 1) == 1;
        $compile = $this->params()->fromRoute("compile", $this->config->isProduction()?'1':'0') == 1;
        $migrate = $this->params()->fromRoute("migrate", 1) == 1;
        $templates = $this->params()->fromRoute("templates", $this->config->isProduction()?'1':'0') == 1;
        $delete_template = $this->params()->fromRoute("templates", 0)  == 2;
        $composer = $this->params()->fromRoute("composer", 1) == 1;
        $cachefiles = $this->params()->fromRoute("cachefiles", $this->config->isProduction()?'1':'0') == 1;
        $delete_cachefiles = $this->params()->fromRoute("cachefiles", 0)  == 2;
        $tests = $this->params()->fromRoute("tests", 1)  == 1;
        $supervisor = $this->params()->fromRoute("supervisor", $this->config->isProduction()?'1':'0') == 1;
        */

    }
    protected function checkErrors()
    {
        $this->getLogger()->info("Check errors");
        $result = $this->execute("php", ["console","cli:source-map"]);
        if(!$result["success"])
        {
            throw new \Exception("Error during git pull: ".$directory);
        }
    }
    protected function pullGit($directory = NULL)
    {
        if(!isset($directory))
        {
            $directory = $this->current_directory;
        }else
        {
            $directory = join_paths($this->current_directory, $directory);
        }
        $this->getLogger()->info("git pull: ".$this->getRelativePath($directory));
        chdir($directory);
        $result = $this->execute("git", ["pull"]);
        if(!$result["success"])
        {
            throw new \Exception("Error during git pull: ".$directory);
        }
        chdir($this->current_directory);
    }
    public function compileJS()
    {
        $this->getLogger()->info("Compile JS");
        chdir("public/ts");
        $result = $this->execute("metatypescript", ["--once"]);
        if(!$result["success"])
        {
            throw new \Exception("Error during typescript compilation");
        }
        chdir($this->current_directory);
    }
    public function compileCSS()
    {
        $this->getLogger()->info("Compile CSS");
        chdir("scripts/css");
        $result = $this->execute("gulp", ["sass".($this->config->isProduction()?':prod':'')]);
        if(!$result["success"])
        {
            throw new \Exception("Error during css compilation");
        }
        chdir($this->current_directory);
    }

    public function migrate()
    {
        $this->getLogger()->info("Tables migrations");
        $result = $this->execute("php phinx", ["migrate","-e",$this->config->getEnv()]);
        if(!$result["success"])
        {
            throw new \Exception("Error during tables migrations");
        }
    }
    public function templatesCalculation()
    {
        $this->getLogger()->info("Templates calculations");
        //$result = $this->execute("php public/index.php", ["templates"]);
        $result = $this->execute("php", ["console","calcul-templates"]);
        if(!$result["success"])
        {
            throw new \Exception("Error during templates calculations");
        }
    }
    public function deleteTemplates()
    {
        $this->getLogger()->info("Delete Templates cache");
        $this->getTemplateTable()->clearTemplates();
    }
    public function updateComposer()
    {
        $this->getLogger()->info("Composer update");
        $old_md5 = isset($this->cache["composer"])?$this->cache["composer"]:NULL;
        $new_md5 = md5(file_get_contents("composer.lock"));
        if($new_md5 != $old_md5)
        {
            $this->getLogger()->info("Composer needs update");
            $result = $this->execute("composer", ["install"]);
            if(!$result["success"])
            {
                throw new \Exception("Error during composer install");
            }
            $this->cache["composer"] = $new_md5;
        }
    }
    public function cacheFiles()
    {
         $this->getLogger()->info("Cache and compress files");
           $config = $this->config->get("update");
            if(!isset($config["folders"]))
            {
                 $this->getLogger()->warn("No folder to cache");
                 return;
            }
            $folders = $config["folders"];
            $files = isset($config["files"])?$config["files"]:array();
            foreach($folders as $folder)
            {
                 $files = array_merge($files, $this->getFilesRecursive($folder["path"], ["*.".$folder["extension"]], ["min","minmap","*.map.css"]));
            }

            $this->cache["files"] = array_filter($this->cache["files"] , function($item) use($files)
            {
                return in_array($item, $files);
            }, \ARRAY_FILTER_USE_KEY);

            foreach($files as $file)
            {
                if(!file_exists($file))
                {
                    continue;
                }
                if(isset($this->cache["files"][$file]))
                {
                    $old = $this->cache["files"][$file];
                }else
                {
                    $old = array("md5"=>NULL, "label"=>NULL, "count"=>0);
                }
                $new_md5 = md5(file_get_contents($file));
                if($new_md5 != $old["md5"])
                {
                    $date = new \DateTime();
                    $this->cache["files"][$file] = array("md5"=>$new_md5, "label"=>$date->format('Y/m/d-H:i:s')."-".($old["count"]+1), "count"=>$old["count"]+1);
                    if(strpos($file, ".js")!==False && strpos($file, ".min.")===False)
                    {
                        $new_file = substr($file, 0, strlen($file)-3).".min.js";
                        $relative_file = substr($new_file, strlen("public/js/"));
                        chdir("scripts/js");
                        $result = $this->execute("gulp", ["js", '--file="'.$file.'"']);
                        if(!$result["success"])
                        {
                            throw new \Exception("Error during minify js");
                        }
                        $this->cache["files"][$file]["file"] = "public/js/min/".$relative_file;
                        $this->cache["files"][$file]["file_with_map"] = "public/js/minmap/".$relative_file;
                        chdir($this->current_directory);
                    }elseif(strpos($file, ".css")!==False )
                    {
                        $relative_file = substr($file, strlen("public/css/"));
                        $relative_file = basename($relative_file,".css");
                        if(file_exists("public/css/".$relative_file.".css"))
                        {
                            $this->cache["files"][$file]["file"] = "public/css/".$relative_file.".css";
                        }elseif(isset($this->cache["files"][$file]["file"]))
                        {
                            unset($this->cache["files"][$file]["file"] );
                        }
                        if(file_exists("public/css/".$relative_file.".map.css"))
                        {
                            $this->cache["files"][$file]["file_with_map"] = "public/css/".$relative_file.".map.css";
                        }elseif(isset($this->cache["files"][$file]["file_with_map"]))
                        {
                            unset($this->cache["files"][$file]["file_with_map"] );
                        }
                    }

                }
            }
    }

    public function deleteCacheFiles()
    {

        $this->getLogger()->info("Delete cache");
        unset($this->cache["files"]);
        $this->rmdir("public/js/min");
        $this->rmdir("public/js/minmap");
        $this->rmdir("public/css/min");
        $this->rmdir("public/css/minmap");
    }
    protected function writeCache()
    {
        if(file_exists(UpdateGitController::CACHE_FILE))
        {
            $cache = json_decode(file_get_contents(UpdateGitController::CACHE_FILE), True);
        }else
        {
            $cache = array();
        }
        $cache["cache_helper"] = $this->cache;
        if(!isset($cache["prod_count"]))
        {
            $cache["prod_count"] = 0;
        }
        $cache["prod_count"]++;
        $date = new \DateTime();
        $cache["last_update"] = $date->format('Y/m/d H:i:s');
        file_put_contents(UpdateGitController::CACHE_FILE, json_encode($cache, \JSON_PRETTY_PRINT));
    }
    public function executeTests()
    {
        if(!isset($directory))
        {
            $directory = $this->current_directory;
        }else
        {
            $directory = join_paths($this->current_directory, $directory);
        }
        $this->getLogger()->info("git pull: ".$this->getRelativePath($directory));
        chdir("public/ts/framework/tests");
        $result = $this->execute("mocha");
        if(!$result["success"])
        {
            throw new \Exception("Error during git pull: ".$directory);
        }
        chdir($this->current_directory);
    }

    public function restartSupervisor()
    {
        $result = $this->execute("supervisorctl", ["restart","all"]);
    }
    public function getFilesRecursive($path, $filters = NULL, $exclude = NULL)
    {
        $content = [];
        $files = scandir($path);
        foreach($files as $file)
        {
            if($file == "." || $file == "..")
            {
                continue;
            }
            if(isset($exclude) && $this->match($file, $exclude))
            {
                continue;
            }
            if(is_dir(join_paths($path, $file)))
            {
                $content = array_merge($this->getFilesRecursive(join_paths($path, $file), $filters, $exclude), $content);
                continue;
            }
            if(!isset($filters) || $this->match($file, $filters))
            {
                $content[] = join_paths($path, $file);
            }
        }

        return $content;
    }
    public function disable_ob() {
            // Turn off output buffering
            ini_set('output_buffering', 'off');
            // Turn off PHP output compression
      //      ini_set('zlib.output_compression', false);
            // Implicitly flush the buffer(s)
            ini_set('implicit_flush', true);
            ob_implicit_flush(true);
            // Clear, and turn off output buffering
            while (ob_get_level() > 0) {
                // Get the curent level
                $level = ob_get_level();
                // End the buffering
                ob_end_clean();
                // If the current level has not changed, abort
                if (ob_get_level() == $level) break;
            }
            // Disable apache output buffering/compression
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
                apache_setenv('dont-vary', '1');
            }
        }
    protected function execute($command, $params = NULL, $execute = True)
    {
        if(isset($params))
        {
            $command.= " ".implode(" ", $params);
        }
        $this->getLogger()->normal("execute: ".$command);
        $command.=" 2>&1";
        $output = [];
        $returnValue = NULL;
        if($execute)
        {
            $descriptorspec = array(
               0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
               1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
               2 => array("pipe", "w")    // stderr is a pipe that the child will write to
            );

            $process = proc_open($command, $descriptorspec, $pipes);
            if (is_resource($process)) {
                while ($s = fgets($pipes[1])) {
                   echo $s;
                   $output[] = $s;
                }
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $returnValue = proc_close($process);
            }
        }
        return ["output"=>$output, "returnValue"=>$returnValue, "success"=>$returnValue==0];
    }
    protected function getRelativePath($path)
    {
        $relative = mb_substr($path, strlen($this->current_directory));
        if(strlen($relative) === 0)
        {
            return ".";
        }
        return $relative;
    }
    private function rmdir($folder)
    {
        if(!file_exists($folder))
        {
            return;
        }
        $dir = opendir($folder);
        $has_files = False;
        while(false !== ( $file = readdir($dir)) )
        {
            if($file == "." || $file == "..")
            {
                continue;
            }
            if(!is_dir($folder . '/' . $file))
            {
                unlink($folder."/".$file);
              //  $this->getLogger()->normal($folder . '/' . $file);
                continue;
            }
            $this->rmdir($folder . '/' . $file);
        }
        closedir($dir);
        rmdir($folder);
       // $this->getLogger()->error($folder);
    }
    public function recurse_copy($src,$dst, $exclude = array(), $src_root = NULL) {
        $dir = opendir($src);
        if(!isset($src_root))
        {
            $src_root = $src;
        }
        @mkdir($dst);
     //    $this->getLogger()->info($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {

                if($this->match($file, $exclude))
                {
                     $this->getLogger()->error($dst . '/' . $file);
                    continue;
                }


                if ( is_dir($src . '/' . $file) ) {
                    $this->recurse_copy($src . '/' . $file,$dst . '/' . $file, $exclude, $src_root);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);

                    $this->getLogger()->normal($dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    private function cleanEmptyFolders($folder)
    {
        $dir = opendir($folder);
        $has_files = False;
        while(false !== ( $file = readdir($dir)) )
        {
            if($file == "." || $file == "..")
            {
                continue;
            }
            if(!is_dir($folder . '/' . $file))
            {
                $has_files = True;
                continue;
            }
            $this->cleanEmptyFolders($folder . '/' . $file);
        }
        closedir($dir);
        if(!$has_files)
        {
            //if folder has no file we check
            if(count(scandir($folder)) == 2)
            {
              // $this->getLogger()->error($folder);
               rmdir($folder);
            }
        }
    }
    private function match($file, $exclude)
    {
        if(in_array($file, $exclude))
        {
            return True;
        }
        foreach($exclude as $exclusion)
        {
            if(starts_with($exclusion, "*."))
            {
                $exclusion = substr($exclusion, 1);
                if(ends_with($file, $exclusion))
                {
                    return True;
                }
            }
        }

        return False;
    }
    /**
     * @return \Application\Table\CabinetTable
     */
    public function getCabinetTable()
    {
        return $this->sm->get("CabinetTable");
    }
    /**
     * @return \Application\Table\AdminTable
     */
    public function getAdminTable()
    {
        return $this->sm->get("AdminTable");
    }
    /**
     * @return \Application\Service\Picture
     */
    public function getPictureManager()
    {
        return $this->sm->get("PictureManager");
    }
    /**
     * @return \Application\Table\UserTable
     */
    public function getUserTable()
    {
        return $this->sm->get("UserTable");
    }
    /**
     * @return \Application\Table\PlaceTable
     */
    public function getPlaceTable()
    {
        return $this->sm->get("PlaceTable");
    }

    /**
     * @return \Application\Table\CompanyTable
     */
    public function getCompanyTable()
    {
        return $this->sm->get("CompanyTable");
    }
    /**
     * @return \Application\Table\CVTable
     */
    public function getCVTable()
    {
        return $this->sm->get("CVTable");
    }
    /**
     * Email Manager
     * @return \Core\Service\Email [description]
     */
    public function getEmailManager()
    {
        return $this->sm->get("Email");
    }
    /**
     * @return \Application\Service\Notifications
     */
    public function getNotificationManager()
    {
        return $this->sm->get("Notifications");
    }
      /**
     * @return \Application\Table|TemplateTable
     */
    public function getTemplateTable()
    {
        return $this->sm->get("TemplateTable");
    }
}
