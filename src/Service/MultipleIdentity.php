<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 28/09/2014
 * Time: 19:13
 */

namespace Core\Service;


class MultipleIdentity extends CoreService implements IIdentity
{
    private $_identities;
    private $_cases_apis;
    private $_configured_apis;
    /**
     * @var UserModel
     */
    public $user;
    /**
     * @var \Core\Service\ACL
     */
    public $acl;
    public function setACL($acl)
    {
        $this->acl;
    }
    public function getACL()
    {
        return $this->acl;
    }
    public function init()
    {

        $this->_cases_apis = $this->sm->get("APIManager")->getAll();
        $this->_configured_apis = array();
        foreach($this->_cases_apis as $api)
        {
            $this->_configured_apis[] = mb_strtolower($api);
        }
        $this->_identities = array();

        if(!empty($this->session->id_user))
        {
            $token_table = $this->sm->get("TokenTable");
            if($token_table->isTokenValid($this->session->id_user, $this->session->token, $this->session->device_token))
            {
                //GOOD
                $user_table = $this->sm->get("UserTable");
                $this->user = $user_table->getUser($this->session->id_user);
                $apis = $user_table->getApis($this->session->id_user);
                foreach($apis as $api)
                {
                    //instantiate apis
                    $this->$api;
                }
                $this->logConnection();
            }else
            {
                //BAD
                $this->session->clearAll();
                $this->user = NULL;
            }
        }
    }
    public function logConnection()
    {
        if(isset($this->user) && !$this->user->isAdmin())
        {
            $delay = 300000;//5minutes
            $timestamp = intval(microtime(True)*1000);
            if($this->user->last_connection+$delay<$timestamp)
            {
                $this->getUserTable()->updateLoginConnection($this->user);
            }
            $delay = 1800000; //30min
            if($this->user->last_connection+$delay<$timestamp)
            {
                $this->getUserTable()->updateConnectionCount($this->user);
                if(($this->user->isCabinetEmployee() || $this->user->isCompanyEmployee()) && !$this->sm->get("AppConfig")->isLocal())
                {
                    $this->getNotificationManager()->login($this->user);
                }
            }
            $this->user->last_connection = $timestamp;

        }
    }
    /**
     * Get url for api authentificaiton
     * @param $api
     * @param null $data
     * @return mixed
     */
    public function authenticate($api, $data = NULL)
    {
        return $this->{$api}->authenticate($data);
    }

     public function manualAuthenticate($login, $password)
    {
        //return $this->{$api}->authenticate($data);
    }
    /**
     * @param $api
     * @return \Core\Service\Identity
     * @throws \Exception
     */
    public function __get($api)
    {
        if(array_key_exists($api, $this->_identities))
        {
            return $this->_identities[$api];
        }
        if(!in_array(mb_strtolower($api), $this->_configured_apis))
            throw new \Exception($api." is not configured");
        //lazy loading
        $indexAPI = array_search($api, $this->_configured_apis);
        $caseAPI = $this->_cases_apis[$indexAPI];
        if ($caseAPI === 'linkedin')
            $caseAPI = 'LinkedIn';
        $name = '\Core\Service\Identity\\'.ucfirst($caseAPI).'Identity';
        $this->_identities[$api] = new $name();
        $this->_identities[$api]->setServiceLocator($this->sm);
        return $this->_identities[$api];
    }

    /**
     * Get user from an api and save it to session
     * @param $api
     */
    public function setUserFromAPI($api, $type)
    {

        $user = $this->$api->getUser($type);
        $this->sm->get("UserTable")->updateAccessToken($this->$api->getAccessToken(), $api);
        $this->setUser($user);
    }
    public function setUser($user)
    {
        $this->user = $user;
        $this->session->id_user = $this->user->getRealID();
        $this->sm->get("TokenTable")->generateUserToken();
    }

    public function getUser()
    {
        return $this->user;
    }

    public function addUserFromAPI($api, $id_user = NULL)
    {
        $this->sm->get("UserTable")->addAPIToUser($api, $this->{$api}->api->getUserForDatabase(), $id_user);
    }
    public function removeAPI($api)
    {
        $this->sm->get("UserTable")->removeApiToUser($api);
    }
    public function isLoggued($api = NULL)
    {
        if(!isset($api))
        {
            return $this->user !== NULL;
        }
        return $this->{$api}->isLoggued();
    }
    public function logout()
    {
        if(!$this->sm->get("Identity")->isLoggued())
        {
            return;
        }
        $apis = $this->sm->get("UserTable")->getApis();
        foreach($apis as $api)
        {
            $this->$api->logout();
        }
        $device_token = $this->session->device_token;
        $this->sm->get("TokenTable")->removeToken($device_token, $this->session->token);
        $this->session->clearAll();
        $id_user =  $this->session->createUser()->id_user;
        if($device_token !== NULL)
        {
            $this->session->device_token = $device_token;
        }

    }
    public function getAPIs()
    {
        return $this->sm->get("UserTable")->getApis();
    }

    /**
     * @return \Application\Table\UserTable
     */
    public function getUserTable()
    {
        return $this->sm->get("UserTable");
    }

    /**
     * @return \Application\Service\Notifications
     */
    public function getNotificationManager()
    {
        return $this->sm->get("Notifications");
    }
}
