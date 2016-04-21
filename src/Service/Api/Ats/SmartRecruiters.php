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
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class SmartRecruiters extends AbstractAts implements ServiceLocatorAwareInterface
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
    private $has_refresh = false;

    public function __construct($consumer_key, $consumer_secret)
    {
        $this->client           = new \GuzzleHttp\Client();
        $this->consumer_key     = $consumer_key;
        $this->consumer_secret  = $consumer_secret;

        $this->models           = [
            'jobs(\/[^\/]+){0,1}$'          => '\Application\Model\Ats\Smartrecruiters\JobModel',
            'candidates(\/[^\/]+){0,1}$'    => '\Application\Model\Ats\Smartrecruiters\CandidateModel',
        ];
    }

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;

        $this->init();
    }

    public function getServiceLocator()
    {
        return $this->sm;
    }

    public function init()
    {
        $apis           = $this->sm->get('AppConfig')->get('apis');
        $this->config   = $apis['smartrecruiters'];
    }

    public function setAccessToken($access_token, $refresh_token)
    {
        $this->access_token     = $access_token;
        $this->refresh_token    = $refresh_token;
    }

    public function getLoginUrl($data)
    {
        // mob.local/company/user/login/smartrecruiters
        $scopes         = $this->config['scopes'];
        $redirect_uri   = $this->config['redirect_uri'];
        $url            = 'https://www.smartrecruiters.com/identity/oauth/allow?client_id=' . $this->consumer_key . '&redirect_uri=' . rawurlencode($redirect_uri) . '&scope=' . rawurlencode(implode(' ', $scopes));

        return $url;

    }

    public function isAuthenticated()
    {
        return (null !== $this->access_token);
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

    public function post( $ressource, $_params = [] )
    {
        return $this->request('POST', $ressource, ['body' => $_params]);
    }

    public function put( $ressource, $_params = [] )
    {
        return $this->request('PUT', $ressource, ['json' => $_params]);
    }

    public function json( $ressource, $_params = [] )
    {
        return $this->request('POST', $ressource, ['json' => $_params]);
    }

    public function get( $ressource, $_params = [] )
    {
        return $this->request('GET', $ressource, ['query' => $_params]);
    }

    public function request( $method, $ressource, $_params )
    {
        $path   = 'https://api.smartrecruiters.com/';

        try
        {
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

            if ('identity/oauth/token' === $ressource)
            {
                $path = 'https://www.smartrecruiters.com/';
            }

            if (php_sapi_name() === 'cli')
            {
                $this->sm->get('Log')->normal('[' . $method . '] ' . $path . $ressource . ' ' . json_encode($params));
            }

            $data = $this->client->{ strtolower($method) }($path . $ressource, $params);
        }
        catch (\Exception $e)
        {
            if (401 === $e->getCode() && false === $this->has_refresh)
            {
                $this->has_refresh = true;
                // no authorize, try to refresh

                $old_access_token   = $this->access_token;
                $this->access_token = null;

                try
                {
                    $json = $this->post('identity/oauth/token', [
                        'grant_type'    => 'refresh_token',
                        'refresh_token' => $this->refresh_token,
                        'client_id'     => $this->consumer_key,
                        'client_secret' => $this->consumer_secret
                    ]);

                    if (isset($json['access_token']) && isset($json['refresh_token']))
                    {
                        $this->sm->get('UserTable')->refreshToken( 'smartrecruiters', $old_access_token, $this->refresh_token, $json['access_token'], $json['refresh_token'] );

                        $this->setAccessToken( $json['access_token'], $json['refresh_token'] );

                        // $this->has_refresh = false;

                        return $this->request( $method, $ressource, $_params );
                    }
                    else
                    {
                        throw $e;
                    }
                }
                catch (\Exception $ee)
                {
                    throw $e;
                }
            }
            else
            {
                throw $e;
            }
        }
        $data   = $data->json();
        $found  = false;

        foreach ($this->models as $regex => $modelClass)
        {
            if (preg_match('/' . $regex . '/', $ressource) > 0)
            {
                $found = true;
                break;
            }
        }

        if (true === $found)
        {
            $sm         = $this->sm;

            if (isset($data['content']))
            {
                $data['content'] = array_map(function($item) use ($modelClass, $sm){
                    $model = new $modelClass();

                    $model->setServiceLocator( $sm );
                    $model->exchangeArray($item);

                    return $model;
                }, $data['content']);
            }
            else
            {
                $model = new $modelClass();

                $model->setServiceLocator( $sm );
                $model->exchangeArray($data);

                $data = $model;
            }
        }

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
                    'body'  => [
                        'grant_type'    => 'authorization_code',
                        'code'          => $code,
                        'client_id'     => $this->consumer_key,
                        'client_secret' => $this->consumer_secret
                        ]
                    ]);

                    if (isset($json['access_token']) && isset($json['refresh_token']))
                    {
                        $this->setAccessToken( $json['access_token'], $json['refresh_token'] );

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
            // dd($e->getMessage());
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

    /**
     * Send message into SM Home
     *
     * @param  string  $content             content of the message.
     * @param  boolean $share_with_everyone true if everyone can see the message.
     * @return array
     */
    public function sendMessage( $content, $share_with_everyone = false)
    {
        $params = [
            'content'   => 'YBorder: '. $content,
            'shareWith' => [
                'everyone'  => $share_with_everyone
            ]
        ];

        return $this->json('messages/shares', $params);
    }
    /**
     * Get candidate current state
     *
     * @param  string $id_api_candidate [description]
     * @return array                    [description]
     */
    public function getCandidateState( $id_api_candidate )
    {
        $histories  = $this->get('candidates/' . $id_api_candidate . '/status/history', ['limit' => 1]);
        $history    = array_pop($histories['content']);
        $state      = $history['status'];

        return $state;
    }

    /**
     * Ask in touch candidate by company
     *
     * @param  string $id_api_candidate [description]
     * @return array                    [description]
     */
    public function askInTouch( $id_api_candidate )
    {
        $content = 'Intouch request sent to #[CANDIDATE:' . $id_api_candidate  . ']';

        return $this->sendMessage( $content, true );
    }

    /**
     * API Public : search companies by name
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function searchCompany( $query )
    {
        $data = $this->get('companyNames', ['q' => $query]);

        return $data['results'];
    }
}
