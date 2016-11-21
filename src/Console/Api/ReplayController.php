<?php

namespace Core\Console\Api;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class ReplayController extends \Core\Console\CoreController
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
        $id = $this->params()->fromRoute("id", NULL);
        if(!isset($id))
        {
            throw new Exception('you must specify an id');
        }
        $call = $this->getAdminTable()->getStatsCall($id);
        $controller = $call["controller"];
        $action = $call["action"];
        $method = $call["method"];
        $params = json_decode($call["params"], True);


        if(isset($call["id_user"]))
        {
            $user = $this->getUserTable($call["id_user"]);
        }

        $api = $this->api->$controller->method($method); 
        if(isset($user))
        {
            $api = $api->user($user);
        }
        $result = $api->$action($params)->value;
        var_dump($result);
    }
    protected function getAdminTable()
    {
        return $this->sm->get("AdminTable");
    }
    protected function getUserTable()
    {
        return $this->sm->get("UserTable");
    }
}
