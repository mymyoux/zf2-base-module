<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 17:45
 */

namespace Core\Model;


class UserModel extends CoreModel
{
    const ROLE_TEMP = "user_temp";
    public $id;
    public $first_name;
    public $last_name;
    protected $isIdentity = False;
    protected $roles;
    protected $_ids;
    public $role;
    /**
     *  Login token
     * @var string
     */
    public $token;
    /**
     * @var string
     */
    public $type;
    /**
     * @var \Core\Model\UserModel
     */
    protected $real_user;
    public $last_connection;
    public $num_connection;
    public $email;
    public $source;
    public function __construct()
    {
        $this->roles = array();
        $this->role = 0;

        $this->addRole(UserModel::ROLE_TEMP);
    }
    public function setIsIdentity($value)
    {
        $this->isIdentity = $value;
    }
    public function toString()
    {

    }
    public function getRealID()
    {
        if($this->isImpersonated())
        {
            return $this->real_user->getRealID();
        }
        return $this->id;
    }
    public function setRealUser($user)
    {
        $this->real_user = $user;
    }
    public function isAdmin()
    {
        return $this->type == "admin" || ($this->isImpersonated() && $this->real_user->isAdmin());
    }
    public function isAdminType()
    {
        return $this->type == "admin";
    }
    public function getRealUser()
    {
        return $this->real_user;
    }
    public function isImpersonated()
    {
        return isset($this->real_user);
    }
       public function addRole($role)
    {
        if(!in_array($role, $this->roles))
        {
            $this->roles[] = $role;
        }
        $this->invalidate();
    }
    public function hasRole($role)
    {
        return in_array($role, $this->roles);
    }
    public function removeRole($role)
    {
        if(($key = array_search($role, $this->roles)) !== False)
        {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }
    }
    public function addAPI($name, \ArrayObject $data)
    {
        $this->_ids[$name] = $data["id_".$name];
        $this->removeRole(UserModel::ROLE_TEMP);
        $this->invalidate();
    }

    public function getAPIID( $name )
    {
        return isset($this->_ids[$name]) ? $this->_ids[$name] : null;
    }
    public function getRoles()
    {
        return $this->roles;
    }
    /**
     * Check if the user's id is a Database id
     * @return bool
     */
    public function hasID()
    {
        return isset($this->id) && $this->id!=0;
    }
}
