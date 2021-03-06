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

/**
 * Class API
 * @package Core\Service
 */
class API extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    private $_forward;

    public function __construct()
    {
        //$folder = ROOT_PATH.'/module/Core/src/Annotations/';
        $folder = ROOT_PATH.'/vendor/Core/src/Annotations/';

        AnnotationRegistry::registerFile($folder.'Paginate.php');
        AnnotationRegistry::registerFile($folder.'Roles.php');
        AnnotationRegistry::registerFile($folder.'Param.php');
        AnnotationRegistry::registerFile($folder.'Filters.php');
        AnnotationRegistry::registerFile($folder.'Table.php');
        AnnotationRegistry::registerFile($folder.'Response.php');
        AnnotationRegistry::registerFile($folder.'Order.php');
        AnnotationRegistry::registerFile($folder.'Back.php');
        AnnotationRegistry::registerFile($folder.'Doc.php');
        AnnotationRegistry::registerFile($folder.'Header.php');
        AnnotationRegistry::registerFile($folder.'JSONP.php');
        AnnotationRegistry::registerFile($folder.'User.php');
        AnnotationRegistry::registerFile($folder.'Excel.php');
    }

    /**
     * Gets forward controller plugin
     * @return mixed
     */
    protected function forward()
    {
        if(!isset($this->_forward))
        {
            $this->_forward = $this->sm->get('controllerpluginmanager')->get('forward');
        }
        return $this->_forward;
    }

    /**
     * Called when using the api
     * @param $name
     * @return _Binder
     */
    protected function _controller($name)
    {
        $binder = new _Binder($this, $name);
        return $binder;
    }

    /**
     * Called when the complete call of the API has been done
     * @param $controller Controller
     * @param $action Action
     * @param $arguments [ID?, aditional parameters]
     * @return mixed Result
     * @throws \Exception
     */
    public function _action($controller, $action, $arguments, $context, $module)
    {
        $this->sm->get('Route')->setServiceLocator($this->sm);
        $type = (null !== $module ? ucfirst($module) : ucfirst($this->sm->get('Route')->getType()));
        $controller = ucfirst($controller);

        $request = array(
            'action' => $action,
        );

         if(isset($arguments))
        {
            $index = 0;
            if(sizeof($arguments)>$index)
            {
                if(is_string($arguments[$index]))
                {
                    $index--;
                }else
                {
                    $request['id'] = $arguments[$index];
                }
            }
            $index++;
            if(sizeof($arguments)>$index)
            {
                if(is_array($arguments[$index]))
                {
                    $index--;
                }else
                {
                    $request['method'] = $arguments[$index];
                }
            }
            $index++;
            if(sizeof($arguments)>$index)
            {
                $request['params'] = $arguments[$index];
            }
        }

        $methodName = $request['action'].'API';
        // check if '&method=' exist
        $params = $this->sm->get('ControllerPluginManager')->get('Params');

        if(!isset($request['method']))
        {
            if(null !== $params->fromQuery('method', $params->fromPost('method')))
            {
                $method = $params->fromQuery('method', $params->fromPost('method'));
            }
            else
            {
                // if not, get the current method
                $method = $this->sm->get('Request')->getMethod();
            }
        }else
        {
            $method = $request["method"];
        }



        $request['action'] = camel($request['action']);

        $modules = $module === NULL?$this->sm->get("ApplicationConfig")["modules"]:[$module];
        if(isset($module) && !in_array(ucfirst($module), $this->sm->get("ApplicationConfig")["modules"]))
        {
            $this->sm->get("Module")->lightLoad($module);
              //TODO: mediumLoad ? (full without routes?)

        }
        $modules =  array_reverse($modules);
        $controllerFound = False;
        // check with ucfirst
        foreach($modules as $module)
        {
            $object_name = '\\'.ucfirst($module).'\Controller\\' . $controller."Controller";
            if (false === class_exists($object_name) || !method_exists('\\'.$module.'\Controller\\'.$controller.'Controller', $request['action'].'API'.$method))
            {
                if(false !== class_exists($object_name))
                {
                    $controllerFound = True;
                }
                continue;
            }
            $type = $module;
            break;
        }

        // check
        if (false === class_exists($object_name))
        {
            foreach($modules as $module)
            {
                $object_name = '\\'.ucfirst($module).'\Controller\\' . strtoupper($controller)."Controller";
                if (false === class_exists($object_name) || !method_exists('\\'.$module.'\Controller\\'.strtoupper($controller).'Controller', $request['action'].'API'.$method))
                {
                    if(false !== class_exists($object_name))
                    {
                        $controllerFound = True;
                    }
                    continue;
                }
                $type = $module;
                break;
            }
        }
        /*
        if (false === class_exists($object_name) && isset($module))
        {
            $path = join_paths(ROOT_PATH,'/module/',ucfirst($module),'src','Controller',  $controller."Controller.php");
            include_once $path;
            dd($path);
        }*/
        if (false === class_exists($object_name))
        {
            if($controllerFound)
            {
                throw new ApiException('bad_method:'.$request['action'].'API'.$method, 1);
            }else
            {
                throw new ApiException('bad_controller:'.$controller, 1);
            }
        }
        $namespace = '\\'.$type.'\Controller\\'.$controller;



        $annotationReader = new AnnotationReader();



        if(method_exists($namespace.'Controller', $request['action'].'API'.$method))
        {
            $methodName = $request['action'].'API'.$method;
        }
        else
        {
            throw new ApiException('Ressource not exist "' . $request['action'] . 'API" with the method : "' . $method . '"', 4);
        }

        $apiRequest = new Request();
        $apiRequest->fromFront = $context->isFromFront();

        // Check Annotations for the class
        $reflectedClass = new \ReflectionClass($namespace.'Controller');

        $annotations = $annotationReader->getClassAnnotations($reflectedClass);
        $keys = array();

        foreach($annotations as $annotation)
        {
            $annotation->setServiceLocator( $this->sm );

            $parse = $annotation->parse($request);
            if(isset($parse))
            {
                $keys[] = 'class_' . $annotation->key();
                $apiRequest->add('class_' . $annotation->key(), $parse);
            }else
            {
                dd($annotation);
            }
        }
        // free memory
        unset( $annotations );



        // Check Annotations for the method
        $reflectedMethod = new \ReflectionMethod($namespace.'Controller', $methodName);
        $apiRequest->setServiceLocator($this->sm);
        $apiRequest->setGivenParams(count($arguments)>2?$arguments[2]:[]);
        $apiRequest->setUser($context->hasUser()?$context->getUser():$this->sm->get("Identity")->getUser());
        $annotations = $annotationReader->getMethodAnnotations($reflectedMethod);
        //TODO: faire un choix pour params=> soit un property / param soit $requests->params-> ..
        //


        //TODO:checkp our usertable

        $keys = array();
        foreach($annotations as $annotation)
        {
            //search for table
            if($annotation instanceof Doc || $annotation instanceof Table)
            {
                if($annotation instanceof Table && !$annotation->useDoc)
                {
                    continue;
                }
                if(!isset($annotation->method))
                {
                    continue;
                }
                  $annotation->setServiceLocator( $this->sm );
                //$annotation->
                $table =  $annotation->getTable();
                if(!isset($table) && isset($apiRequest->class_table))
                {
                    $table = $apiRequest->class_table->getTable();
                }
                $table_method   = $annotation->method;
                if(isset($table) && isset($table_method) && method_exists($table, $table_method))
                {
                   $reflectedMethod = new \ReflectionMethod($table, $table_method);
                   $annotationsTable = $annotationReader->getMethodAnnotations($reflectedMethod);
                   if(!empty($annotationsTable))
                    $annotations = array_merge($annotations, $annotationsTable);
                }
            }
        }
        $headers = [];
        $use_jsonp = false;
        $use_user = NULL;
        foreach($annotations as $annotation)
        {
            if($annotation->key() == "header")
            {
                $headers[] = $annotation;
            }
            if($annotation->key() == "jsonp")
            {
                $use_jsonp = true;
            }
           
        }
        foreach($annotations as $annotation)
        {

            $annotation->setServiceLocator( $this->sm );
            $annotation->api = $context;
             if($annotation->key() == "user")
            {
                $use_user = $annotation->getUser();
            }
            // get parent class annotation value if not set in method
            if (true === isset($apiRequest->{'class_' . $annotation->key() }))
            {
                foreach ($apiRequest->{'class_' . $annotation->key() } as $key => $value)
                {
                    if (!isset($annotation->{$key}) && $annotation->{$key} != $value)
                    {
                        $annotation->{$key} = $value;
                    }
                }
            }
            $parse = $annotation->parse($request);
            if(isset($parse))
            {
                $keys[] = $annotation->key();
                if(!$apiRequest->exists($annotation->key(), $parse))
                {
                    $parse = $annotation->validate($parse);
                    $apiRequest->add($annotation->key(), $parse);
                }
            }else
            {
                dd($annotation);
            }
        }
        

        // free memory
        unset( $annotations );

        //additional key for manually change type of request
        $keys[] = 'method';

        $request['request'] = $apiRequest;
        $request['action_suffix'] = 'API';
        //dd($apiRequest->class_table);
        //check table annotations
        $use_excel = False;
        if($context->isFromFront() && isset($apiRequest->excel) && $apiRequest->excel->use && isset($apiRequest->paginate) && isset($apiRequest->paginate->limit) && $apiRequest->paginate->limit>0)
        {
            //remove paginate
            $apiRequest->paginate->reset();
            $use_excel = True;
        }

        if(!$apiRequest->isValid($apiRequest))
        {
            $formatted_result           = new \StdClass();
            $formatted_result->value    = $apiRequest->getError();
            $formatted_result->success  = false;
            return $formatted_result;
        }

        if(isset($use_user))
        {
            $apiRequest->setUser($use_user);
            $context->setUser($use_user);
        }
        $result = null;
        $result_name = (isset($apiRequest->response) ? $apiRequest->response->name : $request['action']);
        // check if table set
        if (true === isset($apiRequest->class_table) || true === isset($apiRequest->table))
        {

            $table          = (true === isset($apiRequest->table) ? $apiRequest->table->getTable() : $apiRequest->class_table->getTable());
            $table_method   = (true === isset($apiRequest->table) && null !== $apiRequest->table->method ? $apiRequest->table->method : $methodName);

            if (true === method_exists($table, $table_method))
            {

                $result      = [];
                $result_name = (isset($apiRequest->response) ? $apiRequest->response->name : $request['action']);
                $result[ $result_name ] = $table->{ $table_method }($context->hasUser()?$context->getUser():$this->sm->get("Identity")->getUser(), $apiRequest);
            }else
            {
                if (true === isset($apiRequest->table) && null !== $apiRequest->table->method)
                    throw new ApiException(get_class($table).'->'.$table_method.' doesn\'t exist for ' . $request['action'] . 'API" with the method : "' . $method . '"', 4);
            }
        }

        if (null === $result)
        {
            $result = $this->forward()->dispatch($namespace, $request);
        }
        if(isset($request['params']))
        {
            $params_keys = array_keys($request['params']);
            $diff = array_diff($params_keys, $keys);
            $index = array_search("__timestamp", $diff);
            if($index !== False)
            {
                unset($diff[$index]);
            }
            $index = array_search("ltoken", $diff);
            if($index !== False)
            {
                unset($diff[$index]);
            }
            $index = array_search("rtoken", $diff);
            if($index !== False)
            {
                unset($diff[$index]);
            }
            if(!empty($diff))
            {
                if (is_array($result))
                    $result['warning_non_valid_params'] = array_values($diff);
                else if ($result instanceof ViewModel)
                    $result->setVariable('warning_non_valid_params', array_values($diff));
            }
        }

        if ($result instanceof ViewModel)
        {
            if ($result instanceof ConsoleModel)
            {
                $result = $result->getVariables();


                if (isset($result['result']))
                    $result = $result['result'];

            }
            else
                $result = $result->getVariables();

            if (false === $context->isFromFront()) // in APP call
            {
                if (!is_array($result) && method_exists($result, 'count') && $result->count() === 0)
                    $result = null;
            }

            if (!isset($result[$result_name]))
            {
                $result = [ $result_name => ($result instanceof Variables) ? (array) $result : $result ];
            }
        }
        if (!is_array($result))
        {
            $result = array($result_name=>$result);
        }

        $formatted_result = new \StdClass();
        $formatted_result->value = $result;
        $api_data = new \StdClass();
        $api_data->key = $result_name;

        if(isset($result[$result_name]))
        {
            $api_data->count = sizeof($result[$result_name]);
            foreach($apiRequest as $key => &$annotation)
            {

                if(isset($annotation) && true === method_exists($annotation, 'exchangeResult')) //why is there a null value  ?
                    $annotation->exchangeResult($result[$result_name]);
                else
                {
                    // string(5) "roles"
                    // string(6) "params"
                    // string(7) "filters"
                    // string(7) "session"
                    // normal ?
                    // var_dump($key);
                }
            }
            /*if(isset($apiRequest->paginate))
            {
                $apiRequest->paginate->exchangeResult($result[$result_name]);
            }*/
            $api_data->paginate = $apiRequest->paginate;
        }

        foreach ($apiRequest->getAPIData() as $key => $value)
        {
            $api_data->{ $key } = $value;
        }
        $formatted_result->api_data = $api_data;
        $formatted_result->request  = $this;
        $formatted_result->success  = true;
        $formatted_result->use_excel  = $use_excel;

        if(!$context->isJSON() || !$context->isFromFront())
        {
            $formatted_result->value = $result[$result_name];
        }
        if($context->isFromFront())
        {
            if(!empty($headers))
            {
                $formatted_result->headers = array_reduce($headers,function($previous, $item)
                {
                    $previous[$item->name] = $item->value;
                    return $previous;
                }, []);
            }
            if($use_jsonp)
            {
                $formatted_result->use_jsonp = true;
            }
        }
        return $formatted_result;
    }

    /**
     * Dynamic api call
     * @param $controller
     * @return _Binder
     */
    public function __get($controller)
    {
        return $this->_controller($controller);
    }
}

/**
 * Helper to be able to chain call to the api
 * Class _Binder
 * @package Application\Service
 */
class _Binder
{
    private $api;
    private $controller;
    private $_user;
    private $_json;
    private $_module;
    private $_method;
    public function __construct(API $api, $controller)
    {
        $this->_json        = false;
        $this->_user        = null;
        $this->_front       = false;
        $this->_module      = null;
        $this->api          = $api;
        $this->controller   = $controller;
    }
    public function __call($action, $arguments)
    {

        if(isset($this->_method))
        {
            if(!empty($arguments))
            {
                $temp =  $arguments;
                $arguments = [$this->_method];
                $arguments[] = $temp[0];
            }else
            {
                $arguments = [$this->_method];
            }
        }
        return $this->api->_action($this->controller, $action, $arguments, $this, $this->_module);
    }
    public function hasUser()
    {
        return isset($this->_user);
    }
    public function getUser()
    {
        return $this->_user;
    }
    public function setUser($value)
    {
        $this->_user = $value;
    }
    public function isJSON()
    {
        return $this->_json === True;
    }
    public function isFromFront()
    {
        return $this->_front === true;
    }
    public function user($user)
    {
        $this->_user  = $user;
        return $this;
    }
    public function method($method)
    {
        $this->_method = strtoupper($method);
        return $this;
    }
    public function post()
    {
        return $this->method("post");
    }
    public function module($module)
    {
        $this->_module  = $module;
        return $this;
    }
    public function front($front)
    {
        $this->_front  = $front;
        return $this;
    }
    public function json()
    {
        $this->_json = True;
        return $this;
    }
}
