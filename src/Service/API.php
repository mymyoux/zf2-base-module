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
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\View\Variables;
use Zend\View\Model\ConsoleModel;

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
        $namespace = '\\'.$type.'\Controller\\'.$controller.'Controller';

       $modules = $this->sm->get("ApplicationConfig")["modules"];
       $modules =  array_reverse($modules);
        foreach($modules as $module)
        {
            $object_name = '\\'.ucfirst($module).'\Controller\\' . $controller."Controller";
            if (false === class_exists($object_name))
            {
                continue;
            }
            $type = $module;
            break;
        }
        if (false === class_exists($object_name))
        {
            throw new \Exception('bad_controller:'.$controller, 1);
        }
        $namespace = '\\'.$type.'\Controller\\'.$controller;


        $request = array(
            'action' => $action,
        );
        if(isset($arguments))
        {
            if(sizeof($arguments))
            {
                $request['id'] = $arguments[0];
            }
            if(sizeof($arguments)>1)
            {
                $request['method'] = $arguments[1];
            }
            if(sizeof($arguments)>2)
            {
                $request['params'] = $arguments[2];
            }
        }

        $annotationReader = new AnnotationReader();

        $request['action'] = camel($request['action']);


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

        if(method_exists($namespace.'Controller', $request['action'].'API'.$method))
        {
            $methodName = $request['action'].'API'.$method;
        }
        else
        {
            throw new \Core\Exception\ApiException('Ressource not exist "' . $request['action'] . 'API" with the method : "' . $method . '"', 4);
        }

        $apiRequest = new Request();

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
        $keys = array();
        foreach($annotations as $annotation)
        {
            $annotation->setServiceLocator( $this->sm );
            $annotation->api = $context;

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
                $apiRequest->add($annotation->key(), $parse);
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

        if(!$apiRequest->isValid($apiRequest))
        {
            $formatted_result           = new \StdClass();
            $formatted_result->value    = $apiRequest->getError();
            $formatted_result->success  = false;

            return $formatted_result;
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
                    throw new \Core\Exception\ApiException(get_class($table).'->'.$table_method.' doesn\'t exist for ' . $request['action'] . 'API" with the method : "' . $method . '"', 4);
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
        $formatted_result->api_data = $api_data;
        $formatted_result->request  = $this;
        $formatted_result->success  = true;

        if(!$context->isJSON() || !$context->isFromFront())
        {
            $formatted_result->value = $result[$result_name];
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
