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
            $view->setVariable("api_error", $e->getMessage());
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

        return $view;
    }





    /**
     * @ghost\Paginate(limit=4)
     * @ghost\Roles(needs="admin",forbidden="visitor")
     * @ghost\Param(name="param1",requirements="\d+",required=true)
     * @ghost\Param(name="param2", required=true)
     * @ghost\Filters("filter1,filter2,filter3,filter4")
     * @return JsonModel
     */
    public function echoAPI()
    {
        $view = new JsonModel();
        /**
         * @var $request \Core\Service\Api\Request
         */
        $request = $this->params("request");

        var_dump($request->params);

        $request->filters->apply("test");
        $view->setVariable("test","ok");
        $view->setVariable("params",$request->params);

        return $view;
    }

}
