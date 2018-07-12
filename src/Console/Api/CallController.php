<?php

namespace Core\Console\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class CallController extends \Core\Console\CoreController
{
    CONST DESCRIPTION   = '';

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    /**
     * @return
     */
    public function startAction()
    {
        global $argv;
        $data = $this->params()->fromRoute("data");
        $debug = $this->params()->fromRoute("debug");
        if(isset($debug))
        {
            $debug = True;
        }
        $data = json_decode(base64_decode($data), True);
        $controller = $data["controller"];
        $method = $data["method"];
        $action = $data["action"];
        $params = $data["params"];
        if(isset($data["module"]))
            $module = $data["module"];
        if(isset($data["api_user"]))
        {
            $user = $this->getUserTable()->getUser($data["api_user"]);
        }
        $api = $this->api->$controller->method($method);
        if(isset($module))
        {
            $api->module($module);
        }
        if(isset($user))
        {
            $api = $api->user($user);
        }
        try
        {
            $result = $api->$action($params);
            $object = ["data"=>$result->value,
                "api_data"=>$result->api_data];
        }
         catch(\Core\Exception\ApiException $e)
        {
            $exception = [];
            $exception["message"] = $e->getCleanErrorMessage();
            $exception["line"] = $e->getLine();
            $exception["file"] = $e->getFile();
            $exception["type"] = get_class($e);
            $exception["fatal"] = $e->fatal;
            $exception["core"] = $e->getCode();
            $exception["trace"] = $e->getTraceAsString();
            $object = ["exception" => $exception];
        }
        catch(\Exception $e)
        {
            $exception = [];
            $exception["message"] = $e->getMessage();
            $exception["line"] = $e->getLine();
            $exception["file"] = $e->getFile();
            $exception["type"] = get_class($e);
            $exception["fatal"] = False;
            $exception["core"] = $e->getCode();
            $exception["trace"] = $e->getTraceAsString();
            $object = ["exception" => $exception];
        }
        if($debug)
        {
            dd($object);
        }
        echo "------start-data-----\n";
        echo json_encode(
            $object)."\n";
        echo "------end-data-----\n";
    }
     protected function getUserTable()
    {
        return $this->sm->get("UserTable");
    }
}
