<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class UserObject extends CoreObject implements ICoreObjectValidation
{
    public $id_user;
    public function exchangeRequest($data)
    {
    }
    public function isValid($sm, $apiRequest)
    {
        return True;
    }
    public function getUser()
    {
        return $this->sm->get("UserTable")->getUser($this->id_user);
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class User extends CoreAnnotation
{
    protected $_key = "user";
    protected $_object = "UserObject";
    public $id_user;
    protected function _parse($value, $request)
    {
        $object = parent::_parse($value, $request);
        return $object;
    }
    public function validate( $object )
    {
        try
        {
           dd($object->getUser());
        }
        catch (\Exception $e)
        {
            throw new ApiException($object->name . " doesn't exist in the ServiceLocator", 10);
        }
        return $object;
    }
     public function getUser()
    {
        return $this->sm->get("UserTable")->getUser($this->id_user);
    }

}
