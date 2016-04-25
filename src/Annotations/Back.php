<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class BackObject extends CoreObject implements ICoreObjectValidation
{
    public function exchangeRequest($data)
    {
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
