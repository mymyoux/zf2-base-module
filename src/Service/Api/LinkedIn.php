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
use Happyr\LinkedIn\LinkedIn as HLinkedIn;

class LinkedIn extends HLinkedIn implements IAPI
{

    protected $sm;
    /**
     * @inheritDoc
     */
    protected $config;

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
    public function setConfig($config)
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
            if(array_key_exists("emailAddress", $data))
            {
                $data["email"] = $data["emailAddress"];
                unset($data["emailAddress"]);
            }
            if(isset($data["publicProfileUrl"]) && !empty($data["publicProfileUrl"]))
            {
                $data["link"] = $data["publicProfileUrl"];
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
        $this->getStorage()->clearAll();
    }

    /**
     * @inheritDoc
     */
    public function getUserForDatabase()
    {
        $user = $this->getUser();
        $keys = $this->getDatabaseColumns();
        $sanitazed_user = array();
        foreach($keys as $key)
        {
            if(array_key_exists($key, $user))
            {
                $sanitazed_user[$key] = $user[$key];
            }
        }
        return $sanitazed_user;
    }

    /**
     * @inheritDoc
     */
    protected function getDatabaseColumns()
    {
        //camelCase from Linkedin
        return array("id" ,"headline","firstName","lastName","access_token","email", "link");
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
    public function company($id_company, $fields)
    {
        if(is_array($fields))
        {
            $fields = implode(",", $fields);
        }
        return $this->request("v1/companies/".$id_company.":(".$fields.")");
    }
    public function setServiceLocator($sm)
    {
       $this->sm = $sm;
    }
}
