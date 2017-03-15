<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 18:05
 */

namespace Core\Service\Identity;


use Zend\Http\Request;

class WttjIdentity extends APIIdentity
{
    /**
     * @var \Core\Service\Api\Facebook
     */
    public $api;
    protected function init()
    {
        parent::init();
        $this->name = "wttj";
        $configuration = $this->sm->get("AppConfig")->getConfiguration();
        $this->initAPI();
    }
    protected function initAPI()
    {
        $this->api = $this->sm->get("APIManager")->get("wttj");// new LinkedIn($this->api_key, $this->secret_key);
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

        // always call parent::callbackRequest
        parent::callbackRequest($request);

        // always return null
        return null;
    }

    protected function _getDefaultColumns()
    {
        return array_merge(parent::_getDefaultColumns(), ['access_token', 'avatar_url']);
    }
}
