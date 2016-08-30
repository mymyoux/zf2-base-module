<?php

namespace Core\Service\Api\Ats;

use Zend\Http\Request;

trait SmartRecruitersTrait
{
    protected $access_token;
    protected $refresh_token;

    protected $has_refresh = false;
    protected $user;

    public function setAccessToken($access_token, $refresh_token, $refresh = true)
    {
        $this->access_token     = $access_token;
        $this->refresh_token    = $refresh_token;

        if ($refresh)
            $this->has_refresh      = false;
    }

    public function getLoginUrl($data)
    {
        $scopes         = $this->config['scopes'];
        $redirect_uri   = $this->config['redirect_uri'];
        $url            = 'https://www.smartrecruiters.com/identity/oauth/allow?client_id=' . $this->consumer_key . '&redirect_uri=' . rawurlencode($redirect_uri) . '&scope=' . rawurlencode(implode(' ', $scopes));

        return $url;
    }

    public function isAuthenticated()
    {
        return (null !== $this->access_token);
    }

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
        // 500 ms sleep
        usleep(500000);

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

            $this->sm->get('Log')->normal('[' . $method . '] ' . $path . $ressource . ' ' . json_encode($params));

            $data = $this->client->{ strtolower($method) }($path . $ressource, $params);

            $this->logRessource( $method, $ressource );
        }
        catch (\Exception $e)
        {
            // Client error response [url] https://api.smartrecruiters.com/configuration/company [status code] 401 [reason phrase] Unauthorized
            preg_match('/Client error response \[url\] (.+) \[status code\] (\d+) \[reason phrase\] (.+)/', $e->getMessage(), $matches);
            if (count($matches) > 0)
            {
                if ($e instanceof \GuzzleHttp\Exception\ClientException)
                    $error = json_decode( $e->getResponse()->getBody()->__toString() );
                else
                    $error = null;

                list($original_message, $e_url, $e_code, $e_message) = $matches;
                $e = new SmartrecruitersException((isset($error->message) ? $error->message : $e_message), $e_code);

                $id_error = $this->sm->get('ErrorTable')->logError($e);
                $this->sm->get('Log')->error($e->getMessage());

                // log error
                $this->logApiCall($method, $ressource, $params, false, null, $id_error);
            }
            else
            {
                // not an API Exception
                throw $e;
            }

            if (401 === $e->getCode() && false === $this->has_refresh && $path !== 'https://www.smartrecruiters.com/')
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

                        $this->setAccessToken( $json['access_token'], $json['refresh_token'], false );

                        return $this->request( $method, $ressource, $_params );
                    }
                    else
                    {
                        throw $e;
                    }
                }
                catch (\Exception $ee)
                {
                    // invalid access token
                    if (null !== $this->ats_user)
                    {
                        $this->sm->get('UserTable')->updateNetworkByUser( 'smartrecruiters', $this->ats_user->id_user, [
                            'access_token'  => null,
                            'refresh_token' => null
                        ] );
                    }
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

        // log success
        $this->logApiCall($method, $ressource, $params, true, $data);

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
                        throw new SmartrecruitersException( 'SmartRecruiters API error' );
                    }

                    $user = $this->formatUser( $user );

                    $this->user = $user;

                    return $user;
                }
                else
                {
                    throw new SmartrecruitersException( 'SmartRecruiters API error' );
                }
            }
            else
            {
                throw new SmartrecruitersException($error_description, $code);
            }
        }
        catch( \Exception $e )
        {
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
        return array("id","name", "email", "first_name", "last_name", "access_token", 'refresh_token', "role", "active");
    }

    public function searchCompany( $query )
    {
        $data = $this->get('companyNames', ['q' => $query]);

        return $data['results'];
    }

    public function getExcludeFunctions()
    {
        return (isset($this->config['exclude_function']) ? $this->config['exclude_function'] : []);
    }

    public function getJob( $id )
    {
        return $this->get('jobs/' . $id);
    }

    public function isCandidateHired( $state )
    {
        return $state === 'HIRED';
    }

    public function isCandidateProcessClose( $state )
    {
        return $state === 'WITHDRAWN' || $state === 'REJECTED';
    }

    public function getCompanyInformation()
    {
        try
        {
            return $this->get('configuration/company', []);
        }
        catch (SmartrecruitersException $e)
        {
            return null;
        }
    }
}

class SmartrecruitersException extends Exception
{

}
