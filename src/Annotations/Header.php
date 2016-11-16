<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class HeaderObject extends CoreObject implements IMetaObject
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

    }
    public function getAPIKey()
    {
        return "header";
    }
    public function getAPIObject()
    {
        return new HeaderClass();
    }
}
class HeaderClass
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
class Header extends CoreAnnotation
{
    protected $_key = "header";
    protected $_object = "ParamObject";
    /**
     * @var string
     */
    public $name;
    /**
     * @var mixed
     */
    public $value;
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
//        $object->value = $this->validate( $object->value );
        $this->_key = $object->name;
        return $object;
    }

    public function validate( $object )
    {
        return $object;
    }
}
