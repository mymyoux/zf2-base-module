<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 03/10/2014
 * Time: 11:40
 */

namespace Core\Service;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

/**
 * Manages all supported API
 * Class ApiManager
 * @package Core\Service
 */
class ApiManager extends CoreService
{
    private $apis;
    private $configuration;
    protected function init()
    {

        $this->apis = array();
        $this->configuration = $this->sm->get("AppConfig")->get("apis");
        foreach($this->configuration as $key=>$value)
        {
            if(isset($value["disabled"]) && $value["disabled"] === true)
            {
                unset($this->configuration[$key]);
            }
        }
        $keys = array_keys($this->configuration);
        foreach($keys as $key)
        {
            if(mb_strtolower($key)!= $key)
            {
                $this->configuration[mb_strtolower($key)] = $this->configuration[$key];
            }
        }
    }

    /**
     * Gets instance of API
     * @param $name Api's name
     * @return \Core\Service\Api\IIAPI
     */
    public function get($name)
    {
        if(isset( $this->apis[mb_strtolower($name)]))
        {
            return $this->apis[mb_strtolower($name)];
        }
        if($this->_has($name))
        {
            $this->_createNewAPI($name);
            return $this->apis[mb_strtolower($name)];
        }

        return NULL;
    }

    /**
     * Tests if the api allow a user log in
     * @param $name Api's name
     * @return bool
     */
    public function canLogin($name)
    {
        if($name == "manual")
        {
            return True;
        }
        if(!$this->has($name))
        {
            return False;
        }
        $api = $this->get($name);

        return $api->canLogin();
    }

    public function typeAuthorize($name, $type)
    {
        if($name == "manual")
        {
            return True;
        }
        if(!$this->has($name))
        {
            return False;
        }
        $api = $this->get($name);

        return in_array($type, $api->typeAuthorize());
    }
    /**
     * Tests if the api allow mutiple connectors
     * @param $name Api's name
     * @return bool
     */
    public function canMultiple($name)
    {if($name == "manual")
        {
            return False;
        }
        if(!$this->has($name))
        {
            return False;
        }
        $api = $this->get($name);

        return $api->canMultiple();
    }
    /**
     * Tests if An account can be shared between multiple users
     * @param $name Api's name
     * @return bool
     */
    public function isSharable($name)
    {
        if($name == "manual")
        {
            return False;
        }
        if(!$this->has($name))
        {
            return False;
        }
        $api = $this->get($name);

        return $api->isSharable();
    }


    /**
     * Get all api's names
     * @return mixed
     */
    public function getAll()
    {
        $keys = array_keys($this->configuration);
        $lowerKeys = array();
        foreach($keys as $key)
        {
            $key = mb_strtolower($key);
            if(!in_array($key, $lowerKeys))
            {
                $lowerKeys[] = $key;
            }
        }
        return $lowerKeys;
    }
    public function getAllLoggable()
    {
        $apis = $this->getAll();
        $loggable = array();
        foreach($apis as $api)
        {
            if($this->canLogin($api))
            {
                $loggable[] = $api;
            }
        }
        return $loggable;
    }

    /**
     * Checks if an API is currently supported
     * @param $name
     * @return bool
     */
    public function has($name)
    {
        return $this->_has($name);
    }

    /**
     * Checks if the api is configured
     * @param $name Api's name
     * @return bool True or False
     */
    private function _has($name)
    {
        if(array_key_exists($name, $this->configuration))
        {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Instanciates a new api
     * @param $name Api's name
     */
    private function _createNewAPI($name)
    {
        $api = $this->configuration[$name];
        $cls = $api["class"];
        $reflection_class = new \ReflectionClass($cls);
        $params = array_key_exists("params", $api)?$api["params"] : [];
        $name = mb_strtolower($name);
        $this->apis[$name] = $reflection_class->newInstanceArgs($params);
        $this->apis[$name]->setConfig($api);
        if($this->apis[$name] instanceof ServiceLocatorAwareInterface)
        {
            $this->apis[$name]->setServiceLocator($this->sm);
        }
    }
}
