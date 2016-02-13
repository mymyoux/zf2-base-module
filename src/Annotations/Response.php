<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class ResponseObject extends CoreObject
{
    /**
     * @var string
     */
    public $name = null;

    public function hasData()
    {
        return true;//isset($this->name) || isset($this->value);
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Response extends CoreAnnotation
{
    protected $_key = "response";
    protected $_object = "ResponseObject";
    /**
     * @var string
     */
    public $name = null;

    protected function _parse($value, $request)
    {
        $object = parent::_parse($value, $request);

        // $this->validate( $object->value );
        // $this->_key = $object->name;
        $this->name = $object->name;

        return $object;
    }

    protected function validate( $value )
    {
       // nohting to validate
    }

}
