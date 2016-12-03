<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 21/10/14
 * Time: 10:58
 */

namespace Core\Controller;
use Core\Annotations as ghost;
use Core\Exception\ApiException;
use Zend\View\Model\JsonModel;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


/**
 * @ghost\Table(name="UserTable")
 * @return JsonModel
 */
class UserController extends FrontController
{
    /**
     * @ghost\Roles("user")
     * @ghost\Table(name="PushTable", method="pushRegistration", useDoc=true)
     */
    public function pushRegistrationAPIPOST()
    {

    }
     /**
     * @ghost\Back
     * @ghost\Table(name="PushTable", method="invalidPushRegistration", useDoc=true)
     */
    public function invalidPushRegistrationAPIPOST()
    {

    }
      /**
     * @ghost\Back
     * @ghost\Table(name="PushTable", method="getPushRegistrations", useDoc=true)
     */
    public function getPushRegistrationsAPIGET()
    {

    }
    /**
     * @ghost\Param(name="api", required=true)
     * @ghost\Param(name="code", required=true) // google
     */
    public function loginAPIPOST()
    {
        $apirequest = $this->params()->fromRoute("request");
        $user = $apirequest->user;

        $api = $apirequest->params->api->value;
        if(!$this->apis->canLogin($api))
        {
            throw new ApiException("bad_api");
        }
        if($api == "manual")
        {
            //handle it
            return false;
        }
        $user = $this->identity->$api->callbackRequest($this->getRequest());
        if(!$this->identity->isLoggued($api))
        {
            throw new ApiException("bad_log");
            return false;
        }
        $email = $this->identity->$api->getAPIUserEmail();

        //Tests if current user exists
        $users = $this->getUserTable()->getUsersFromEmail($email);
        if(!empty($users))
        {
            try
            {
                //add api to user
                $user = $this->api->user->post()->authenticate(array("id_user"=>$users[0]["id_user"]))->value;

                //new api
                if($user->getAPIID($api) === NULL)
                { 
                    $this->identity->addUserFromAPI($api, $user->id);
                }
                $this->identity->$api->updateInformationToDatabase();
                return  $user = $this->getUserTable()->getUser($user->id);
            }catch(\Exception $error)
            {
                throw $error;
            }
        }else
        {
            //new user
            $this->identity->setUserFromAPI($api, "register");
            $id_user = $this->identity->user->id;
            $user = $this->identity->user;
            $this->getUserTable()->updateLoginConnection($user);
            $this->getAppTable()->createAppUser($user);
            $user = $this->getUserTable()->getUser($id_user);
            return $user;
        }

        return null;
    }
    /**
     * @ghost\Param(name="uuid", required=False)
     */
    public function logoutAPIPOST()
    {
        $apirequest = $this->params()->fromRoute("request");
        $user = $apirequest->user;
        if(isset($user))
        {
            $this->api->user->user($user)->post()->invalidPushRegistration(["uuid"=>$apirequest->params->uuid->value]);
        }
        if($this->identity->isLoggued())
        {
            if($this->identity->user->isImpersonated())
            {
                /*
                $this->getAdminTable()->unimpersonate($this->identity->user->getRealUser(), $this->identity->user->id);
                $urlhelper = $this->sm->get('viewhelpermanager')->get("urlApp");
                return $this->plugin("redirect")->toUrl($urlhelper($this->identity->user->getRealUser()->type,"application"));*/
            }
        }
        //TODO:save the token_device + remove token from BDD
        $this->sm->get("Identity")->logout();
        return true;
    }
    protected function getUserTable()
    {
        return $this->sm->get("UserTable");
    }
    protected function getAppTable()
    {
        return $this->sm->get("AppTable");
    }
}
