<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 18:05
 */

namespace Core\Service\Identity;


use Zend\Http\Request;

class SmartrecruitersIdentity extends APIIdentity
{
    /**
     * @var \Core\Service\Api\Facebook
     */
    public $api;
    protected function init()
    {
        parent::init();
        $this->name = "smartrecruiters";
        $configuration = $this->sm->get("AppConfig")->getConfiguration();
        $this->initAPI();
    }
    protected function initAPI()
    {
        $this->api = $this->sm->get("APIManager")->get("smartrecruiters");// new LinkedIn($this->api_key, $this->secret_key);
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
        // if(isset($user))
        // {
        //     $user->link= "https://www.twitter/".$user->screen_name;
        //     $this->getUser()
        //     $user = $this->_formatUserData($user);
        //     $userTable = $this->sm->get('UserTable');
        //     $user_twitter = $userTable->getUserFromAPIID($this->name, array("id_twitter"=>$user["id"]));
        //     if(!isset($user_twitter))
        //     {
        //         if($this->sm->get("Identity")->isLoggued())
        //         {
        //             $userTable->addAPIToUser($this->name, $user);

        //         }else
        //         {
        //             $userTable->createUserFromAPI($this->name, $user);
        //         }
        //     }
        // }
        // parent::callbackRequest($request);
        //TODO:why ?
        return $user;
    }
    protected function _getDefaultColumns()
    {
        return array_merge(parent::_getDefaultColumns()/*, array("screen_name","name","access_token_secret","followers_count","friends_count","link")*/);
    }
}
