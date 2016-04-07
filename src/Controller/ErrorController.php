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

class ErrorController extends FrontController
{
    public function replayAction()
    {
        $view = new JsonModel();
        $id_error = $this->params()->fromRoute("id_error");
        $host = $this->params()->fromRoute("url", "mob.local");
        
        $error = $this->getErrorTable()->getError($id_error);
        if(!isset($error))
        {
            $view->setVariable("error","no id_error with this id");
            return $view;
        }
        $url = $error["url"];

        $parsed = parse_url($url);
        $parsed["host"] = $host;
        if(starts_with($parsed["path"], "//"))
        {
            $parsed["path"] = mb_substr($parsed["path"], 1);
        }
        $url = $this->unparse_url($parsed);
        $error["get"] = json_decode($error["get"], True);
        $error["post"] = json_decode($error["post"], True);
        if(!empty($error["get"]))
        {
            dd($error["get"]);
            $url.= "?".http_build_query($error["get"]);
        }
        else
        if(!empty($error["post"]))
        {
            $error["post"]["__method"] = "post";
            $url.= "?".http_build_query($error["post"]);
        }
        return $this->redirect()->toUrl($url);
    }
    public function getErrorTable()
    {
        return $this->sm->get("ErrorTable");
    }
    private function unparse_url($parsed_url)
    {
          $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : ''; 
          $host     = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
          $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
          $user     = isset($parsed_url['user']) ? $parsed_url['user'] : ''; 
          $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : ''; 
          $pass     = ($user || $pass) ? "$pass@" : ''; 
          $path     = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
          $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : ''; 
          $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : ''; 
          return "$scheme$user$pass$host$port$path$query$fragment";
    }
}
