<?php

namespace Core\Annotations;

use Core\Exception\Exception;
use Zend\ServiceManager\ServiceLocatorInterface;

class CoreAnnotation
{
    /**
     * @var string Annotation key
     */
    protected $_key = "paginate";
    protected $_object = NULL;

    protected $sm;

    public function setServiceLocator( ServiceLocatorInterface $sm )
    {
        $this->sm = $sm;
    }

    public function key()
    {
        return $this->_key;
    }


    public $offset;
    public $limit;
    public function parse($request)
    {
        if (isset($request["params"][$this->key()]))
        {
            $value = $request["params"][$this->key()];
        }else
        {
            $value = array();
        }

        return $this->_parse($value, $request);
    }

    /**
     * @param $value
     * @param $request
     * @return mixed
     * @throws Exception
     */
    protected function _parse($value, $request)
    {
        if(!isset($this->_object))
        {
            throw new Exception("No class linked to the annotation - specify annotation->_object");
        }
        if(strchr($this->_object,'\\')===False)
        {
            $cls = '\Core\Annotations\\'.$this->_object;
        }else
        {
            $cls = $this->_object;
        }
        $object = new $cls();
        $object->setServiceLocator( $this->sm );
        $object->exchangeAnnotation($this);
        $object->exchangeArray($value);
        $object->exchangeRequest($request);

        return $object;
    }
    public function validate($object)
    {
        return $object;
    }
}

