<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 18:05
 */

namespace Core\Service\Identity;


class FacebookIdentity extends APIIdentity
{
    /**
     * @var \Core\Service\Api\Facebook
     */
    public $api;
    protected function init()
    {
        parent::init();
        $this->name = "facebook";
        $configuration = $this->sm->get("AppConfig")->getConfiguration();
        $this->initAPI();
    }
    protected function initAPI()
    {
        $this->api = $this->sm->get("APIManager")->get("facebook");// new LinkedIn($this->api_key, $this->secret_key);
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
        return array("id","name","first_name","last_name", "email", "link","locale","name","timezone","verified","updated_time","gender");

    }
} 
