<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class ParamObject extends CoreObject implements IMetaObject
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var mixed
     */
    public $value;

    public function hasData()
    {
        return isset($this->name) || isset($this->value);
    }
    public function exchangeRequest($data)
    {
        if(isset($data["params"][$this->name]))
        {
            $this->value = $data["params"][$this->name];
        }
    }
    public function getAPIKey()
    {
        return "params";
    }
    public function getAPIObject()
    {
        return new ParamClass();
    }
}
class ParamClass
{
    public function toArray(...$args)
    {
        $all = empty($args);
        $keys = [];
        foreach($args as $key=>$value)
        {
            if(is_array($value))
            {
                $keys = array_merge($keys, $value);
            }else
            {
                $keys[] = $value;
            }
        }
        $data = [];
        foreach($this as $key=>$value)
        {
            if($all || in_array($key, $keys))
            {
                $data[$key] = $value->value;
            }
        }
        return $data;
    }

}
/**
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Param extends CoreAnnotation
{
    protected $_key = "param";
    protected $_object = "ParamObject";
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $requirements = null;
    /**
     * @var boolean
     */
    public $required = false;
    /**
     * @var boolean
     */
    public $is_console = false;
    /**
     * @var boolean
     */
    public $array = false;
    /**
     * @var mixed
     */
    public $value;
    /**
     * @var mixed
     */
    public $default;
    /**
     * @param $value
     * @param $request
     * @return mixed
     * @throws Exception
     */

    protected function _parse($value, $request)
    {
        $object = parent::_parse($value, $request);
        //get casted value
        $object->value = $this->validate( $object->value );
        $this->_key = $object->name;
        return $object;
    }

    protected function isConsole()
    {
        if (php_sapi_name() !== 'cli')
            return false;

        return true;
    }

    protected function validate( $value )
    {
        // check if param if required
        if (true === $this->required && null === $value)
            throw new ApiException($this->name . " is required", 10);

        // check if param if required
        if (null !== $this->requirements && isset($value) && true === $this->array && false === is_array($value))
            throw new ApiException($this->name . " must be an array.", 10);

        // check if param if required
        if (null !== $this->requirements  && isset($value) && false === $this->array && true === is_array($value))
            throw new ApiException($this->name . " musn't be an array.", 10);

        if (null !== $value && true === $this->is_console && !$this->isConsole() && $this->api->isFromFront())
            throw new ApiException($this->name . " need to be set in console.", 10);

        // check requirements (regex)
        if (null !== $this->requirements && null !== $value)
        {
            $data = (false === $this->array) ? [ $value ] : $value;

            foreach ($data as $k=>$d)
            {
                if($this->requirements == "boolean")
                {
                    if($d == "true")
                    {
                        //cast $data for the paramObject
                        $data[$k] = $d = True;
                    }else
                    if($d == "false")
                    {
                        //cast $data for the paramObject
                        $data[$k] = $d = False;
                    }
                    if($d == 1)
                    {
                         $data[$k] = $d = True;
                    }
                    if($d == 0)
                    {
                        $data[$k] = $d = False;
                    }
                    if(!is_bool($d))
                    {
                        throw new ApiException($this->name . " should be boolean", 10);
                    }
                }
                else
                if($this->requirements == "email")
                {
                   if(!is_email($d))
                   {
                      throw new ApiException($this->name . " must be an email format", 10);
                   }
                }
                else
                if (preg_match('/^' . $this->requirements . '$/', $d) === 0)
                    throw new ApiException($this->name . " requirements syntax error : " . $this->requirements." ".is_string($value)?": ".$value:"", 10);
            }
            return  (false === $this->array) ? $data[0]:$value;
        }
        return $value;
    }
}
