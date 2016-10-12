<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class JSONPObject extends CoreObject implements ICoreObjectValidation
{
    public function exchangeRequest($data)
    {
    }
    public function isValid($sm, $apiRequest)
    {
        return True;
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class JSONP extends CoreAnnotation
{
    protected $_key = "jsonp";
    protected $_object = "JSONPObject";
    public function __construct(array $values)
    {
    }


}
