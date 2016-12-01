<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 18:05
 */

namespace Core\Service\Identity;


use Zend\Http\Request;

class GoogleIdentity extends APIIdentity
{
    /**
     * @var \Core\Service\Api\Facebook
     */
    public $api;
    protected function init()
    {
        parent::init();
        $this->name = "google";
        $configuration = $this->sm->get("AppConfig")->getConfiguration();
        $this->initAPI();
    }
    protected function initAPI()
    {
        $this->api = $this->sm->get("APIManager")->get("google");
        // new LinkedIn($this->api_key, $this->secret_key);
    }

    public function authenticate($data = NULL)
    {
        $url_helper = $this->sm->get("application")->getServiceManager()->get('ViewHelperManager')->get('ServerUrl');
         if(!isset($data))
            $data = array();
             $data =
            array_merge(
                array("redirect_uri" => $url_helper(strtok($_SERVER["REQUEST_URI"],'?'))."/response")
                ,$data
            );
        return $this->api->getLoginUrl($data);
    }

    public function getAccessTokenSecret()
    {
        return $this->api->getAccessTokenSecret();
    }

    /**
     * @inheritDoc
     */
    public function callbackRequest(Request $request)
    {
        $user = $this->api->callbackRequest($request);
        if(isset($user))
        {
            /*$this->getUser()
            $user = $this->_formatUserData($user);
            $userTable = $this->sm->get('UserTable');
            $user_twitter = $userTable->getUserFromAPIID($this->name, array("id_twitter"=>$user["id"]));
            if(!isset($user_twitter))
            {
                if($this->sm->get("Identity")->isLoggued())
                {
                    $userTable->addAPIToUser($this->name, $user);

                }else
                {
                    $userTable->createUserFromAPI($this->name, $user);
                }
            }*/
        }
        parent::callbackRequest($request);


        return $user;
        //TODO:why ?
        //return $user;
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
        $keys = $this->_getDefaultColumns();
        $sanitazed_user = array();
        foreach($keys as $key=>$value)
        {
            if(is_numeric($key))
            {
                $key = $value;
            }
            if(array_key_exists($key, $user))
            {
                $sanitazed_user[$value] = $user[$key];
            }
        }
        $sanitazed_user["id_app"] = $this->sm->get("App")->getAppID();
        return $sanitazed_user;
    }
    protected function _getDefaultColumns()
    {
        return $this->api->getDatabaseColumns();//array("id", "email","first_name","last_name","access_token");

    }
    /*
    protected function _getDefaultColumns()
    {
        return array_merge(parent::_getDefaultColumns(), array());
    }*/
}
