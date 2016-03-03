<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;

class RolesObject extends CoreObject implements ICoreObjectValidation
{
    /**
    * @var array<string>
    */
    public $needs;
    /**
     * @var array<string>
     */
    public $forbidden;
    protected $_acl;

    public function hasData()
    {
        return isset($this->needs) || isset($this->forbidden);
    }

    public function exchangeArray($data)
    {
    }
    public function apply($request)
    {

        return $request;
    }
    public function isValid($sm, $apiRequest)
    {
        $acl = $sm->get("ACL")->newInstance();

        if (true === is_array($this->needs) && true === is_array($this->forbidden))
        {
            $this->needs = array_diff($this->needs, $this->forbidden);
        }

        if (isset($this->needs)) {
            foreach ($this->needs as $need) {
                $acl->add($need, $need);
            }
        }

        if (isset($this->forbidden)) {
            foreach ($this->forbidden as $forbidden) {
                $acl->add($forbidden, \Core\Service\ACL::FORBIDDEN);
            }
        }

        if ($acl->is_allowed($apiRequest->user)) {
            return True;
        }
        return ApiException::ERROR_NOT_ALLOWED;
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class Roles extends CoreAnnotation
{
    protected $_key = "roles";
    protected $_object = "RolesObject";
    /**
     * @var array<string>
     */
    public $needs;
    /**
     * @var array<string>
     */
    public $forbidden;

    public function __construct(array $values)
    {
        if(isset($values["value"]))
        {
            $values["needs"] = $values["value"];
            unset($values["value"]);
        }
        if(isset($values["needs"]))
        {
            $this->needs = explode(",", $values["needs"]);
        }
        if(isset($values["forbidden"]))
        {
            $this->forbidden = explode(",", $values["forbidden"]);
        }
    }


}
