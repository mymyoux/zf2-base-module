<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:43
 */

namespace Core\Service\Api;
use Zend\Http\Request as RQ;

require_once(ROOT_PATH."/vendor/twitter/twitter.php");

class Twitter extends AbstractAPI
{
    /**
     * @var \Twitter\Twitter
     */
    private $api;
    public function __construct($consumer_key, $consumer_secret)
    {
        $this->api = new \Twitter\Twitter($consumer_key, $consumer_secret);
    }
    public function init()
    {

    }

    public function setApi( $consumer_key, $consumer_secret )
    {
        $this->api = new \Twitter\Twitter($consumer_key, $consumer_secret);
    }

    public function setAccessToken($key, $secret)
    {
        $this->api->setAccessToken($key, $secret);
    }

    public function getLoginUrl($data)
    {
        $url_callback = $data["redirect_uri"];
        try {
            $twitterOAuthRequestToken = $this->api->getRequestToken(  $url_callback );
            $url = $this->api->getAuthorizeURL( $twitterOAuthRequestToken, FALSE ) /*. '&force_login=true'*/;
            if(array_key_exists("login", $data))
            {
                $url.="&screen_name=".$data["login"];
            }
        }
        catch ( Exception $e ) {
            $url= $url_callback;
        }
        return $url;
    }

    public function isAuthenticated()
    {
        $user = $this->api->getUser();
        if($this->api->getUser()!=0)
        {
            return True;
        }

        return False;
        if($this->session)
        {
            return True;
        }
        //retrieve given URL
        $url_helper = $this->sm->get("application")->getServiceManager()->get('ViewHelperManager')->get('ServerUrl');
        $url = $url_helper($_SERVER['REQUEST_URI']);
        $position = mb_strpos($url, "?");
        $url = mb_substr($url, 0,$position);
        $helper = new FacebookRedirectLoginHelper($url);
        try {
            $this->session = $helper->getSessionFromRedirect();
        } catch(FacebookRequestException $ex) {
            // When Facebook returns an error
            return False;
        } catch(\Exception $ex) {
            // When validation fails or other local issues
            return False;
        }
        if ($this->session) {
            // Logged in
            return True;
        }

        return False;
    }
    /**
     * @inheritDoc
     */
    public function getAccessToken()
    {
        return $this->api->getAccessToken();
    }

    public function getAccessTokenSecret()
    {
        return $this->api->getAccessTokenSecret();
    }


    /**
     * Must be called when the callback url for an api is called
     * @param Request $request
     */
    public function callbackRequest(RQ $request)
    {
        $oauthToken = $request->getQuery()->get( 'oauth_token', NULL );
        $oauthVerifier = $request->getQuery()->get( 'oauth_verifier', NULL );
        $denied = $request->getQuery()->get( 'denied', NULL );
        try {

            if ( NULL === $denied ) {

                if ( NULL !== $oauthToken && NULL !== $oauthVerifier ) {
                    $this->api->getAccessToken( $oauthToken, $oauthVerifier );
                    $twitterAccountVerifyCredentials = $this->api->get( 'account/verify_credentials', ['include_email' => 'true', 'skip_status' => true] );

                    $this->formatUser($twitterAccountVerifyCredentials);

                    $this->api->setAccessToken($twitterAccountVerifyCredentials->access_token,$twitterAccountVerifyCredentials->access_token_secret);


                    return $twitterAccountVerifyCredentials;
                }
                else {
                    throw new \Exception( 'Twitter API error' );
                }
            }
            else {
                return NULL;
            }
        }
        catch( \Exception $e ) {
            return NULL;
        }
        return NULL;
    }

    private function formatUser( &$twitterAccountVerifyCredentials )
    {
        $twitterAccountVerifyCredentials->access_token        = $this->api->getAccessToken();
        $twitterAccountVerifyCredentials->access_token_secret =  $this->api->getAccessTokenSecret();

        // create the first_name & last_name
        if (false === mb_strpos($twitterAccountVerifyCredentials->name, ' '))
        {
            $twitterAccountVerifyCredentials->first_name = $twitterAccountVerifyCredentials->name;
            $twitterAccountVerifyCredentials->last_name  = null;
        }
        else
        {
            $twitterAccountVerifyCredentials->first_name = mb_substr($twitterAccountVerifyCredentials->name, 0, mb_strpos($twitterAccountVerifyCredentials->name, ' '));
            $twitterAccountVerifyCredentials->last_name  = mb_substr($twitterAccountVerifyCredentials->name, mb_strpos($twitterAccountVerifyCredentials->name, ' ') + 1);
        }
        $twitterAccountVerifyCredentials->link = 'https://www.twitter.com/' . $twitterAccountVerifyCredentials->screen_name;

        return $twitterAccountVerifyCredentials;
    }

    public function getUser()
    {
        if($this->api->getUser()!=0)
        {
            $user = $this->get("account/verify_credentials", ['include_email' => 'true', 'skip_status' => true]);
            $this->formatUser( $user );
            $user = toArray( $user );

            return $user;
        }
        return NULL;
    }
    protected function getDatabaseColumns()
    {
        return array("id","name", "email", "first_name", "last_name", "access_token", "access_token_secret", "followers_count", "friends_count","screen_name","link");
    }
    public function get($resource, array $params = array())
    {
        return $this->api->get($resource, $params);
    }
    public function post($resource, array $params = array())
    {
        return $this->api->post($resource, $params);
    }
}
