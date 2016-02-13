<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 18:05
 */

namespace Core\Service\Identity;
use HappyR\LinkedIn\LinkedIn;
use HappyR\LinkedIn\Exceptions\LinkedInApiException;

class LinkedInIdentity extends APIIdentity
{
    /**
     * @var \Core\Service\Api\LinkedIn
     */
    public $api;
    protected function init()
    {
        parent::init();
        $this->name = "linkedin";
        $configuration = $this->sm->get("AppConfig")->getConfiguration();
        $this->initAPI();
    }
    protected function initAPI()
    {
        $this->api = $this->sm->get("APIManager")->get("linkedIn");// new LinkedIn($this->api_key, $this->secret_key);
        $this->api->setServiceLocator($this->sm);
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
    protected function _getDefaultColumns()
    {
        return array_merge(parent::_getDefaultColumns(),array("firstName","lastName","headline","link"));

    }
    public function getUser($type = NULL)
    {
        $user = parent::getUser($type);
        return toArray($user, True);
    }
} 
