<?php
//TODO:remplissage auto du formulaire JS à partir de linkedin
//regarder github
//
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 03/10/2014
 * Time: 12:08
 */

namespace Core\Service\Api;
use Google_Client;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class Google extends Google_Client implements IAPI, ServiceLocatorAwareInterface
{

    protected $sm;
    /**
     * @inheritDoc
     */
    protected $config;
    protected $user;
    protected $access_token;
    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->sm;
    }
    public function typeAuthorize()
    {
        return NULL;
    }
    public function getUser()
    {
        $user = $this->user;
        if(isset($user))
        {
           /* $user = $user->toSimpleObject();
            dd($this->verifyIdToken());
            dd($user);*/
        }
        return $user;
    }
    public function callbackRequest($request)
    {
        $code = $request->getQuery()->get("code");
        if(isset($code))
        {
            $this->access_token = $access_token = $this->fetchAccessTokenWithAuthCode($code);
            $service = new \Google_Service_Games($this);
            $me = $service->players->get('me');
            $this->user = $this->verifyIdToken();
            $this->user["player"] = $me->toSimpleObject();
            $this->user["access_token"] = $access_token;
            $this->user["id"] = $this->user["sub"];
            $this->user["id_player"] = $this->user["player"]->playerId;
            $this->user["login"] = $this->user["player"]->displayName;
            $this->user = array_merge($this->user, $this->access_token);
            $this->user["expires"] = ($this->user["created"] + $this->user["expires_in"])*1000;
            return $me;
        }
    }
    public function getLoginUrl()
    {
        $url = $this->createAuthUrl();
        return $url;
    }
   public function isAuthenticated()
    {
        return (null !== $this->access_token);
    }
    /**
     * @inheritDoc
     */
    public function canLogin()
    {
        return array_key_exists("login", $this->config) && $this->config["login"] === True;
    }
    /**
     * @inheritDoc
     */
    public function canMultiple()
    {
        return array_key_exists("multiple", $this->config) && $this->config["multiple"] === True;
    }
    /**
     * @inheritDoc
     */
    public function isSharable()
    {
        return array_key_exists("sharable", $this->config) && $this->config["sharable"] === True;
    }
    /**
     * @inheritDoc
     */
    public function setAPIConfig($config)
    {
        $this->config = $config;
    }

    public function isAts()
    {
        return false;
    }

    public function canRegister()
    {
        return true;
    }

    /**
     *  @inheritDoc
     */
    protected function getUserFromAccessToken()
    {
        try {
            $data = $this->request('/v1/people/~:(id,firstName,lastName,headline,email-address,public-profile-url)');

            // $data = $this->request('/v1/people/~:(id,firstName,lastName,headline,email-address,public-profile-url,positions,picture-urls::(original),summary,specialties)');
            // echo "<pre>";
            // print_r($data);
            // echo "</pre>";
            // exit();
            if(array_key_exists("emailAddress", $data))
            {
                $data["email"] = $data["emailAddress"];
                unset($data["emailAddress"]);
            }
            if(isset($data["publicProfileUrl"]) && !empty($data["publicProfileUrl"]))
            {
                $data["link"] = $data["publicProfileUrl"];
                if(!ends_with($data["link"], "/en"))
                {
                    $data["link"] .= "/en";
                }
                unset($data["publicProfileUrl"]);
            }else
            {
                unset($data["publicProfileUrl"]);
                if(isset($data["firstName"]) && isset($data["lastName"]))
                {
                    $data["link"] = "https://www.linkedin.com/vsearch/p?trk=vsrp_people_sel&keywords=".$data["firstName"]." ".$data["lastName"];
                }
            }
            return $data;
        } catch (LinkedInApiException $e) {
            return null;
        }
    }
    public function fetchNewAccessToken()
    {
        $token = parent::fetchNewAccessToken();
        if(!isset($token))
        {
            if($this->sm->get("Identity")->isLoggued())
            {

                $user = $this->sm->get("UserTable")->getAPIUserFromUserID("linkedin", $this->sm->get("Identity")->user->id);
                if(isset($user) && isset($user["access_token"]))
                {
                    $token = $user["access_token"];
                }
            }
        }
        return $token;
    }

    /**
     * @inheritDoc
     */
    public function getAccessToken()
    {
        return parent::getAccessToken();
    }

    /**
     * @inheritDoc
     */
    public function logout()
    {

     //   $this->getStorage()->clearAll();
    }

    /**
     * @inheritDoc
     */
    public function getUserForDatabase()
    {
        $user = $this->getUser();
        $keys = $this->getDatabaseColumns();
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
        return $sanitazed_user;
    }

    /**
     * @inheritDoc
     */
    public function getDatabaseColumns()
    {
        //camelCase from Linkedin
        return array("id" ,"picture","id_player","given_name"=>"first_name","family_name"=>"last_name","email","login","access_token","refresh_token","token_type","id_token","expires");
    }

    public function request($resource, array $urlParams=array(), $method='GET', $postParams=array(), $language = "fr-FR, en-US, en-EN")
    {
       //   * Add token and format
       //
       //  if (!isset($urlParams['oauth2_access_token'])) {
       //      $urlParams['oauth2_access_token'] = $this->getAccessToken()->__toString();
       //  }
       //  if (!isset($urlParams['format'])) {
       //      $urlParams['format'] = 'json';
       //  }


       //  //generate an url
       //  $url=$this->getUrlGenerator()->getUrl('api', $resource, $urlParams);
       //  do
       //  {
       //      $index = mb_strpos($url, "?");
       //      $index2 = mb_strrpos($url, "?");
       //      if($index!==False && $index!=$index2)
       //      {
       //          $url = mb_substr($url, 0, $index2)."&".mb_substr($url, $index2+1);
       //      }
       //  }while($index!==False && $index!=$index2);

        //$method that url
        if(!$postParams)
        {
            $postParams = array();
        }
        if(!array_key_exists("headers", $postParams))
        {
            $postParams["headers"] = array();
        }
        $postParams["headers"]["Accept-Language"] = $language;
       // // jj($postParams);
       //  // var_dump($urlParams['format']);exit();
       //  // $result = $this->getRequest()->send($url, $postParams, $method, $urlParams['format']);
       //  //
       //  var_dump($urlParams, $postParams, $url);
       //  $result = $this->getRequest()->send($method, $url, array_merge($postParams, $urlParams));
       //  // dd($result);
       //  // var_dump(htmlentities($result));

       //  if ($urlParams['format']=='json') {
       //      return json_decode($result, true);
        // }

        $result = $this->api($method, $resource, array_merge($postParams, $urlParams));

        return $result;
    }

    public function setServiceLocator(ServiceLocatorInterface $sm)
    {
       $this->sm = $sm;
        $this->sm->get("App")->setServiceLocator($this->sm);
        $app = $this->sm->get("App")->getApp();
        if(isset($app))
        {
            $file_config = join_paths(ROOT_PATH,"module",ucfirst($app->name),"config","google.json");
            if(file_exists($file_config))
            {
                $this->setAuthConfig($file_config);
                $this->setAccessType('offline');
                if(isset($this->config["scopes"]))
                {
                    foreach($this->config["scopes"] as $scope)
                    {
                        $this->addScope($scope);
                    }
                }
                return;
            }
        }
        //bad app
        $this->config = [];
    }
}
