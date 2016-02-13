<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 16:10
 */

namespace Core\Service;
use Core\Model\UserModel;

class Identity extends CoreService implements IIdentity
{
    /**
     * @var UserModel
     */
    public $user;

    public $acl;
    public function setACL($acl)
    {
        $this->acl;
    }
    public function getACL()
    {
        return $this->acl;
    }
    protected function init()
    {

    }
    public function isLoggued()
    {
        return $this->user !== NULL;
    }
    public function authenticate()
    {
        return NULL;
    }
    public function hasEmail()
    {
        return isset($this->user["email"]);
    }
    public function getEmail()
    {
        return $this->hasEmail()?$this->user["email"]:NULL;
    }
    /**
     * @return \Core\Model\UserModel
     */
    public function getUser($type = NULL)
    {
        return $this->user;
    }
    public function logout()
    {

    }
}