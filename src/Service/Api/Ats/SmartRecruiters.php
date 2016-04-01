<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:43
 */

namespace Core\Service\Api\Ats;

use Zend\Http\Request;
use Core\Service\Api\AbstractAts;

class SmartRecruiters extends AbstractAts
{
    /**
     * @var \Twitter\Twitter
     */
    private $api;
    private $consumer_key;
    private $consumer_secret;

    private $access_token;
    private $refresh_token;

    public function __construct($consumer_key, $consumer_secret)
    {
        $this->client           = new \GuzzleHttp\Client();
        $this->consumer_key    = $consumer_key;
        $this->consumer_secret = $consumer_secret;
        // $this->api = new \Twitter\Twitter($consumer_key, $consumer_secret);
    }

    public function init()
    {

    }

    public function setApi( $consumer_key, $consumer_secret )
    {
        // $this->api = new \Twitter\Twitter($consumer_key, $consumer_secret);
    }

    public function setAccessToken($key, $secret)
    {
        // $this->api->setAccessToken($key, $secret);
    }

    public function getLoginUrl($data)
    {
        // mob.local/company/user/login/smartrecruiters
        $scopes         = [
            'candidates_read',
            'candidates_create',
            'candidates_offers_read',
            'candidates_manage',
            'candidate_status_read',
            'configuration_read',
            'configuration_manage',
            'jobs_read',
            'jobs_manage',
            'jobs_publications_manage',
            'users_read',
            'users_manage',
            'messages_write',
        ];
        $redirect_uri   = 'https://app.yborder.com/company/user/login/smartrecruiters/response';//$data['redirect_uri'];
        $url            = 'https://www.smartrecruiters.com/identity/oauth/allow?client_id=' . $this->consumer_key . '&redirect_uri=' . rawurlencode($redirect_uri) . '&scope=' . rawurlencode(implode(' ', $scopes));

        return $url;

    }

    public function isAuthenticated()
    {
        dd('a');
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
    public function callbackRequest(Request $request)
    {
        $code               = $request->getQuery()->get( 'code', NULL );
        $error              = $request->getQuery()->get( 'error', NULL );
        $error_description  = $request->getQuery()->get( 'error_description', NULL );

        try
        {
            if ( NULL === $error )
            {
                if ( NULL !== $code )
                {
                    $res = $this->client->post('https://www.smartrecruiters.com/identity/oauth/token', [
                        'body' => [
                            'grant_type'    => 'authorization_code',
                            'code'          => $code,
                            'client_id'     => $this->consumer_key,
                            'client_secret' => $this->consumer_secret
                        ]
                    ]);

                    // echo $res->getStatusCode();
                    // 200
                    // echo $res->getHeaderLine('content-type');
                    // 'application/json; charset=utf8'
                    $json = $res->json();

                    if (isset($json['access_token']) && isset($json['refresh_token']))
                    {
                        $this->access_token = $json['access_token'];
                        $this->refresh_token = $json['refresh_token'];
                        $user = $this->client->get('https://api.smartrecruiters.com/v1/configs', [
                            'headers'         => ['Authorization' => 'Bearer ' . $this->access_token],

                            // 'access_token' => $this->access_token
                        ]);

                        dd($user->json());
                    }
                    else
                    {
                        throw new \Exception( 'SmartRecruiters API error' );
                    }
                    dd($code);
                    // $this->api->getAccessToken( $oauthToken, $oauthVerifier );
                    // $twitterAccountVerifyCredentials = $this->api->get( 'account/verify_credentials', ['include_email' => 'true', 'skip_status' => true] );

                    // $this->formatUser($twitterAccountVerifyCredentials);

                    // $this->api->setAccessToken($twitterAccountVerifyCredentials->access_token,$twitterAccountVerifyCredentials->access_token_secret);


                    // return $twitterAccountVerifyCredentials;
                }
                else
                {
                    // throw new \Exception( 'Twitter API error' );
                }
            }
            else
            {
                throw new \Exception($error_description, $code);
            }
        }
        catch( \Exception $e )
        {
            dd($e->getMessage());
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
