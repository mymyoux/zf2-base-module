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

    private $user = null;

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
        return true;
        dd('a');
        $user = $this->api->getUser();
        if($this->api->getUser()!=0)
        {
            return true;
        }

        return false;
    }
    /**
     * @inheritDoc
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }

    public function getAccessTokenSecret()
    {
        return $this->api->getAccessTokenSecret();
    }

    public function request( $method, $ressource, array $_params )
    {
        $path   = 'https://api.smartrecruiters.com/';

        if (!empty($this->access_token))
        {
            $params = [
                'headers'         => ['Authorization' => 'Bearer ' . $this->access_token]
            ] + $_params;
        }
        else
        {
            $params = $_params;
        }

        if ('GET' === $method)
            $data = $this->client->get($path . $ressource, $params);
        else
        {
            if ('identity/oauth/token' === $ressource)
                $path = 'https://www.smartrecruiters.com/';
            $data = $this->client->post($path . $ressource, [
                'body' => [
                    $params
                ]
            ]);
        }

        $data = $data->json();

        return $data;
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
                    $json = $this->request('POST', 'identity/oauth/token', [
                        'grant_type'    => 'authorization_code',
                        'code'          => $code,
                        'client_id'     => $this->consumer_key,
                        'client_secret' => $this->consumer_secret
                    ]);
                    // $res = $this->client->post('https://www.smartrecruiters.com/identity/oauth/token', [
                    //     'body' => [
                    //         'grant_type'    => 'authorization_code',
                    //         'code'          => $code,
                    //         'client_id'     => $this->consumer_key,
                    //         'client_secret' => $this->consumer_secret
                    //     ]
                    // ]);

                    // $json = $res->json();

                    if (isset($json['access_token']) && isset($json['refresh_token']))
                    {
                        $this->access_token = $json['access_token'];
                        $this->refresh_token = $json['refresh_token'];

                        $user = $this->request('GET', 'users/me', []);
                    }
                    else
                    {
                        throw new \Exception( 'SmartRecruiters API error' );
                    }

                    $user = $this->formatUser( $user );

                    $this->user = $user;

                    return $user;
                }
                else
                {
                    throw new \Exception( 'SmartRecruiters API error' );
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

    private function formatUser( $user )
    {
        $data = [
            'id'                => $user['id'],
            'first_name'        => $user['firstName'],
            'last_name'         => $user['lastName'],
            'active'            => (int) $user['active'],
            'role'              => $user['role'],
            'email'             => $user['email'],
            'access_token'      => $this->access_token,
            'refresh_token'     => $this->refresh_token
        ];

        return $data;
    }

    public function getUser()
    {
        return $this->user;
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
