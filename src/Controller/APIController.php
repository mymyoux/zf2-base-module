<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 12/01/15
 * Time: 11:10
 */

namespace Core\Controller;
use Core\Service\Api\Request;
use Core\Annotations as ghost;


use Core\Exception\Exception;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;

class APIController extends FrontController
{
    public function indexAction()
    {
        $view       = new JsonModel();
        $controller = $this->params()->fromRoute("cont", NULL);
        $action     = $this->params()->fromRoute("act", "index");
        $id         = $this->params()->fromRoute("id", NULL);

        $params     = array_merge($this->params()->fromPost(), $this->params()->fromQuery());
        $method     = isset($params["method"])?$params["method"]:$this->getRequest()->getMethod();

        $view->setVariable("controller", $controller);
        $view->setVariable("action", $action);
        $view->setVariable("id", $id);


        //stats
        $id_user = $this->identity->isLoggued()?$this->identity->user->getRealID():NULL;
        $impersonated_id_user = $this->identity->isLoggued()?$this->identity->user->id:NULL;
        if($impersonated_id_user == $id_user)
        {
            $impersonated_id_user = NULL;
        }
        /**/
        $timestamp = NULL;
        $timestamp_micro = "";
        if(isset($params["_timestamp"]))
        {
            $timestamp = $params["_timestamp"];
            $old = $timestamp;
            $timestamp = intval($timestamp/1000);
            $micro = $old - $timestamp*1000;
            if($micro>0)
            {
                $timestamp_micro = ".".$micro;
            }
            unset($params["_timestamp"]);
        }
        if(!isset($timestamp))
        {
            $timestamp = time();
        }
        $reloaded_count = 0;
        if(isset($params["_reloaded_count"]))
        {
            $reloaded_count = $params["_reloaded_count"];
            unset($params["_reloaded_count"]);
        }
        if(isset($params["extension"]))
        {
            unset($params["extension"]);
        }
        if(isset($params["id_impersonate"]))
        {
            unset($params["id_impersonate"]);
        }

        $call_token = NULL;
        if(isset($params["_id"]))
        {
            $call_token = $params["_id"];
            unset($params["_id"]);
        }
        $session_token = NULL;
        if(isset($params["_instance"]))
        {
            $session_token = $params["_instance"];
            unset($params["_instance"]);
        }

        $id_exception = NULL;
        $api_stats = array("id_user_impersonated"=>$impersonated_id_user,"session_token"=>$session_token,"controller"=>$controller,"action"=>$action,"params"=>json_encode($params, \JSON_PRETTY_PRINT), "method"=>$method,"id_user"=>$id_user,"date"=>date("Y-m-d H:i:s",$timestamp).$timestamp_micro,"reloaded_count"=>$reloaded_count,"call_token"=>$call_token);
        try
        {
            $result = $this->api->$controller->json()->front(true)->$action($id, $method, $params);
            if (!$result->success)
                throw new \Core\Exception\ApiException($result->value, 1);

            $returnView = $result->value;
            if ($returnView instanceof JsonModel)
            {
                $view = $returnView;
            }
            else
            if (is_array($returnView))
            {
                $view->setVariables([
                    'data'  => $returnView,

                ]);

            }
            else if($returnView instanceof ViewModel)
            {

                $view->setVariable("data", $returnView->getVariables());
            }
            else
            {

                $view->setVariable("data", $returnView);
            }
            $view->setVariable("api_data", $result->api_data);
        }
        catch(\Core\Exception\ApiException $e)
        {
            $view->setVariable("error", $e->getMessage());
            $view->setVariable("api_error", $e->getCleanErrorMessage());
            $view->setVariable("api_error_code", $e->getCode());
            $id_exception = $this->getErrorTable()->logError($e);

            if ($e->getCleanErrorMessage() === \Core\Exception\ApiException::ERROR_NOT_ALLOWED)
                $e->fatal = false;
            $view->setVariable("fatal", $e->fatal);
            if($this->isLocal())
            {
                $view->setVariable("api_error_stack", explode("\n", $e->getTraceAsString()));
                $view->setVariable("api_error_line", $e->getLine());
                $view->setVariable("api_error_file", $e->getFile());
            }
        }
        catch(\Exception $e)
        {
            if($e instanceof \Core\Exception\Exception && isset($e->object))
            {
                $view->setVariable("object", $e->object);
            }
            $view->setVariable("error", $e->getMessage());

            if ($this->isLocal())
            {
                $view->setVariable("code", $e->getCode());
                $view->setVariable("trace", explode("\n",$e->getTraceAsString()));
                $view->setVariable("file", $e->getFile());
                $view->setVariable("line", $e->getLine());
            }


            $id_exception = $this->getErrorTable()->logError($e);
        }
        $state_user = array();
        if($this->identity->isLoggued())
        {
            if($this->identity->user->isCandidate())
            {
                $state_user["state"] = $this->identity->user->state;
            }
            $state_user["id_user"] = $this->identity->user->id;
        }else{
            $state_user["id_user"] = 0;
        }

        $view->setVariable("state_user", $state_user);
        try
        {
            $error = $view->getVariable("error");
            if(isset($error) && ($this->isLocal() || (isset($params["debug"]) && $params["debug"])))
                $view->setVariable("original_request", array("get"=>$_GET, "post"=>$_POST));
            $api_stats["success"] = !isset($error);
            if(isset($error))
            {
                $api_stats["error_type"] = $view->getVariable("api_error")===NULL?1:2;
            }
            if(isset($id_exception))
            {
                $api_stats["id_error"] = $id_exception;
            }

            $api_stats["value"] = json_encode($view->getVariables(), \JSON_PRETTY_PRINT);
            $api_stats["type"] = $this->sm->get("Route")->getType();
            $this->getStatsTable()->recordAPICall($api_stats);

        }catch(\Exception $e)
        {
            //silent
        }

        //dd((array)$view->getVariables()["data"]);
        return $view;
    }
    public function getStatsTable()
    {
        return $this->sm->get("StatsTable");
    }

    public function getErrorTable()
    {
        return $this->sm->get("ErrorTable");
    }

}
