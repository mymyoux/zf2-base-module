<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 16:12
 */

namespace Core\Service;


use Core\Service\Api\Request;
use Core\Annotations\Paginate;
use Core\Annotations\Doc;
use Core\Annotations\Table;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\View\Variables;
use Zend\View\Model\ConsoleModel;
use Core\Exception\ApiException;
use Core\Jobs\Api as JobAPI;
/**
 * Class API
 * @package Core\Service
 */
class APIL extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    protected $path;
    protected $id_user;
    protected $params;
    public function __construct()
    {
    }

    public function path($path)
    {
        $this->path = $path;
        return $this;
    }
    public function params($params)
    {
        $this->params = $params;
        return $this;
    }
    public function param($name, $value)
    {
        if(!isset($this->params))
        {
            $this->params = [];
        }
        $this->params[$name] = $value;
        return $this;
    }
    public function user($user)
    {
        if(is_numeric($user))
        {
            $user = $this->sm->get("UserTable")->getUser($user);
        }
        $this->id_user = $user->id;
        return $this;
    }
    public function send()
    {
        $job = $this->sm->get('QueueService')->createLJob(JobAPI::class, ["path"=>$this->path,"api_user"=>$this->id_user,"params"=>$this->params]);
        $job->send();
        $this->id_user = NULL;
        $this->path = NULL;
        $this->params = NULL;
    }
    public function sendNow()
    {
        $laravel = $this->sm->get("AppConfig")->get("laravel_folder");
        if(!isset($laravel))
        {
            return $this->send();
        }
        $params = ["artisan","api:call","--path=".$this->path];
        if(isset($this->id_user))
            $params[] = "--id_user=".$this->id_user;
        if(isset($this->params))
            $params[] = base64_encode(json_encode(["params"=>$this->params]));
        $result = $this->execute("php", $params, True, $laravel);
        $this->id_user = NULL;
        $this->path = NULL;
        $this->params = NULL;
        if(!isset($result["output"]))
        {
            throw new \Exception('no result');
        }
        $result = $result["output"];
        $start = False;
        $i = 0;
        $len = count($result);
        while(!$start && $i<$len)
        {
            if(starts_with($result[$i],"------start-data-----"))
            {
                $start = True;
            }
            $i++;
        }
        if(!$start)
            throw new \Exception('no result');
        $data = "";
        while($start && $i<$len)
        {
            if(starts_with($result[$i],"------end-data-----"))
            {
                $start = False;
            }else
            {
                $data.=$result[$i];
            }
            $i++;
        }
        $data = json_decode($data);
        if(isset($data->exception))
            throw new \Exception($data->exception->message);
        if(isset($data->stats) && isset($data->stats->log))
        {
            $logs = $data->stats->log;
            foreach($logs as $log)
            {
                $parts = explode(": ",$log);
                $type = array_shift($parts);
                $this->getLogger()->log("[api:call] ".join(": ",$parts), (int)$type);
            }
        }
        $data = $data->value;
        return $data;
    }
    protected function execute($command, $params = NULL, $execute = True, $cwd = NULL)
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

            $process = proc_open($command, $descriptorspec, $pipes, $cwd);
            if (is_resource($process)) {
                while ($s = fgets($pipes[1])) {
                  // echo $s;
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
    public function getLogger()
    {
        return $this->sm->get('Log');
    }
}
