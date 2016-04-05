<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 06/10/2014
 * Time: 18:17
 */

namespace Core\Service\Identity;


use Core\Service\Identity;
use Zend\Http\Request;

class APIIdentity extends Identity
{
    public $api;
    /**
     * @var string
     */
    protected $name;
    protected function init()
    {
        $this->name = "default";
    }

    public function getAPIUser()
    {
        if($this->user !== NULL)
            return $this->user;
        $user = $this->api->getUser();
        return $user;
    }
    public function getUser($type = NULL)
    {
        if($this->user !== NULL)
            return $this->user;
        $user = $this->api->getUser();
        if(!$user)
        {
            return NULL;
        }
        $this->user = $this->_getUser($user, $type);
        return $this->user;
    }
    private function _getUser($user, $type)
    {
        $userTable = $this->sm->get('UserTable');
        $_user = $userTable->getUserFromAPIID($this->name, $user["id"]);

        if(empty($_user))
        {
            if($type == "login" && $this->sm->get("Identity")->isLoggued())
            {
                $userTable->addAPIToUser($this->name, $this->_formatUserData($user));

            }else
            {
                if($type == "register")
                {
                    $userTable->createUserFromAPI($this->name, $this->_formatUserData($user));
                }else
                {
                    throw new \Exception("no_user");
                    return NULL;
                }
            }
            $_user = $userTable->getUserFromAPIID($this->name, $user["id"]);
        }else
        {
            if($type != "login")
            {
                //should not have an api user
                throw new \Exception("api_already_used");
                return NULL;
            }
        }
        if(empty($_user))
        {
            throw new \Exception("A problem occurred when trying to register ".$this->name." user ".$user["id"]);
        }
        return $_user;
    }
    public function isLoggued()
    {
        if(parent::isLoggued())
        {
            return True;
        }

        if($this->api->isAuthenticated())
        {
            //TODO
            return True;
        }else
        {
            if($this->sm->get("Identity")->isLoggued())
            {
                $user = $this->sm->get("UserTable")->getAPIUserFromUserID($this->name, $this->sm->get("Identity")->user->id);
                if(isset($user))
                {
                    $this->user = $user;
                    return True;
                }
            }
        }
        return False;
    }

    /**
     * Must be called when the callback url for an api is called
     * @param Request $request
     */
    public function callbackRequest(Request $request)
    {
        $this->updateInformationToDatabase();
    }
    /**
     * @return string current access token's user
     */
    public function getAccessToken()
    {
        return $this->api->getAccessToken();
    }

    /**
     * Logout the user from the API
     */
    public function logout()
    {
        $this->api->logout();
    }

    public function updateInformationToDatabase()
    {
        if($this->isLoggued())
        {
            $user = $this->api->getUser();
            if(!empty($user))
            {
                $userTable = $this->sm->get("UserTable")->updateUser($this->_formatUserData($user), $this->name);
            }
        }
    }
    protected function _formatUserData($user)
    {
        if(!isset($user))
        {
            return NULL;
        }

        $user = toArray($user, True);
        $columns = $this->_getDefaultColumns();
        $user_formatted = array();
        foreach($columns as $column)
        {
            if(array_key_exists($column, $user))
                $user_formatted[$column] = $user[$column];
        }
        return $user_formatted;
    }
    protected function _getDefaultColumns()
    {
        return array("id", "email","first_name","last_name","access_token");

    }
}
