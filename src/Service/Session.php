<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 28/09/2014
 * Time: 20:01
 */

namespace Core\Service;

use Zend\Session\Container;

class Session extends CoreService
{
    /**
     * @var \Zend\Session\Container
     */
    private $container;
    private $name;
    private $_sessions;
    private $root = False;
    public function __construct($name = "default__", $root = NULL)
    {
        if($name == "default__" || $root === True)
        {
            $this->root = true;
        }
        $this->name = $name;
    }
    public function init()
    {
        $this->container = new Container($this->name);
        if(!isset($this->container->___keys) || !is_array($this->container->___keys))
        {
            $this->container->___keys = [];
        }
        $this->_sessions = array();
        $this->generateDeviceToken();
    }

    /**
     * Clear session data
     * @param string $name If specifies will erase a child session otherwise will erase this session (not including its children)
     * @return bool
     */
    public function clear($name = NULL)
    {
        if(empty($name))
            return $this->container->getManager()->getStorage()->clear($this->name);
        if(array_key_exists($name, $this->_sessions))
        {
            return $this->_sessions[$name]->clear();
        }
        return FALSE;
    }

    /**
     * Clears all storage (this storage and these children)
     * @return bool
     */
    public function clearAll()
    {
        /*foreach($this->_sessions as $key=>$storage)
        {
            $storage->clearAll();
        }*/
        if(isset($this->container->___keys))
        {
            foreach($this->container->___keys as $name)
            {
                $this->$name()->clearAll();
            }
        }
        $result = $this->clear();
        $this->generateDeviceToken();
        return $result;
    }
    public function generateDeviceToken()
    {
        if($this->root && !isset($this->device_token))
        {
            $this->device_token = generate_token();
        }
    }
    public function __get($name)
    {
        return $this->container->{$name};
    }
    public function __set($name, $value)
    {
        return $this->container->{$name} = $value;
    }
    public function __isset($name)
    {
        return isset($this->container->{$name});
    }
    public function __unset($name)
    {
        unset($this->container->{$name});
    }
    public function __call($name, $params)
    {
        if(!isset($this->_sessions))
        {
            $this->init();
        }
        if(array_key_exists($name, $this->_sessions))
            return $this->_sessions[$name];
        $this->_sessions[$name] = new Session($this->name."_".$name);
        $this->_sessions[$name]->setServiceLocator($this->sm);
        $keys = $this->container->___keys;
        $keys[] = $name;
        $this->container->___keys = $keys;
        return $this->_sessions[$name];
    }
    public function toArray(){
        $data = $this->container->getArrayCopy();
        foreach($this->_sessions as $key=>$storage)
        {
            $data[$this->name."/".$key] = $storage->toArray();
        }
        return $data;
    }
} 