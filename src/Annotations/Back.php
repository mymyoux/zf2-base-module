<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class BackObject extends CoreObject implements ICoreObjectValidation
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
    public function isValid($sm, $apiRequest)
    {
        if($apiRequest->isFromFront())
        {
               return ApiException::ERROR_NOT_ALLOWED_FROM_FRONT;
        }
        return True;
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Back extends CoreAnnotation
{
    protected $_key = "back";
    protected $_object = "BackObject";
    /**
     * @var array<string>
     */
    public $allowed;
    /**
     * @var array<string>
     */
    public $default;
    /**
     * @var integer
     */
    public $direction;
    public function __construct(array $values)
    {
        if(isset($values["allowed"]))
        {
           $this->allowed = explode(",",$values["allowed"]);
        }
        if(isset($values["default"]))
        {
            $this->default = $values["default"];
        }
        if(isset($values["direction"]))
        {
            $this->direction = $values["direction"];
        }
    }


}
