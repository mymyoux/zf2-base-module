<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 16:12
 */

namespace Core\Service\Api;


use Core\Annotations\CoreObject;
use Core\Annotations\ICoreObjectValidation;
use Core\Annotations\Param;
use Core\Annotations\ParamObject;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
/**
 * Class API
 * @package Application\Service
 */
class Request implements ServiceLocatorAwareInterface
{
    /**
     * @var \Core\Annotations\PaginateObject
     */
    public $paginate;
    /**
     * @var \Core\Annotations\RolesObject
     */
    public $roles;
    /**
     * @var array<\Core\Annotations\ParamObject>
     */
    public $params;

    public $user;

    public $filters;

    private $errors;

    public $givenparams;

    public $fromFront = False;

    private $api_data = [];

    public function isFromFront()
    {
        return $this->fromFront;
    }

    public function addAPIData($key, $value)
    {
        $this->api_data[ $key ] = $value;
    }

    public function getAPIData()
    {
        return $this->api_data;
    }

    public function setGivenParams($givenparams)
    {
        $this->givenparams = $givenparams;
    }
    public function setUser($user)
    {
        $this->user = $user;
    }
    public function add($key, CoreObject $value)
    {
        //for params
        if(in_array("Core\Annotations\IMetaObject", class_implements($value)))
        {
            $metakey = $value->getAPIKey();
            if(!isset($this->$metakey))
            {
                $metainstance = $value->getAPIObject();
                $this->$metakey = $metainstance;
            }
            $this->$metakey->$key = $value;
        }else
        {
            $this->$key = $value;
        }
    }
    public function exists($key, CoreObject $value)
    {
        if(in_array("Core\Annotations\IMetaObject", class_implements($value)))
        {
            $metakey = $value->getAPIKey();
            if(!isset($this->$metakey))
            {
                $metainstance = $value->getAPIObject();
                $this->$metakey = $metainstance;
            }
            return isset($this->$metakey->$key);
        }else
        {
            return isset($this->$key);
        }
        return false;
    }
    public function isValid($apiRequest)
    {
        $this->errors = [];

        foreach($this as $key=>$value)
        {
            if($this->$key instanceof ICoreObjectValidation)
            {

                $valid = $this->$key->isValid($this->sm, $apiRequest);
                if($valid!==True)
                {
                    $this->errors[] = $valid;
                }
            }
        }
        return empty($this->errors);
    }
    public function toArray()
    {
        $reflect = new \ReflectionClass($this);
        $props   = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach($props as $value)
        {
           $data[$value->name] = $this->{$value->name};
        }
        return $data;
    }
    public function getError()
    {
        if(empty($this->errors))
        {
            return NULL;
        }
        return $this->errors[0];
    }
    /**
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    private $sm;
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;
        $this->session = $this->sm->get("session");
        if($this->session->getServiceLocator() === NULL)
        {
            $this->session->setServiceLocator($this->sm);
        }
    }

    /**
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->sm;
    }
}
