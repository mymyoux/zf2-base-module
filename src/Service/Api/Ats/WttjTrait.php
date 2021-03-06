<?php

namespace Core\Service\Api\Ats;

use Zend\Http\Request;
use \Core\Service\Api\AbstractCoreAts;

Trait WttjTrait
{
    protected $api;

    private $access_token;

    public function getEmailFieldReplyTo()
    {
        return null;
    }

    public function setAccessToken($access_token)
    {
        $this->access_token     = $access_token;
    }

    public function getLoginUrl($data)
    {
        $scopes         = $this->config['scopes'];
        $redirect_uri   = $this->config['redirect_uri'];
        $url            = 'https://www.welcomekit.co/oauth/authorize?client_id=' . $this->consumer_key . '&redirect_uri=' . rawurlencode($redirect_uri) . '&scope=' . rawurlencode(implode(' ', $scopes)) . '&response_type=code';

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

    public function post( $ressource, $_params = [] )
    {
        return $this->request('POST', $ressource, ['body' => $_params]);
    }

    public function put( $ressource, $_params = [] )
    {
        return $this->request('PUT', $ressource, ['json' => $_params]);
    }

    public function patch( $ressource, $_params = [] )
    {
        return $this->request('PATCH', $ressource, ['json' => $_params]);
    }

    public function json( $ressource, $_params = [] )
    {
        return $this->request('POST', $ressource, ['json' => $_params]);
    }

    public function get( $ressource, $_params = [] )
    {
        return $this->request('GET', $ressource, ['query' => $_params]);
    }

    public function request( $method, $ressource, $_params = [] )
    {
        // 500 ms sleep
        usleep(500000);

        // The username is your Lever API token and the password should be blank
        $path   = 'https://www.welcomekit.co/api/v1/external/';
        $auth   = 'Bearer ' . $this->access_token;

        if ($ressource === 'oauth/token')
            $path = 'https://www.welcomekit.co/';

        try
        {
            if (!empty($this->access_token))
            {
                $headers = [];
                if (isset($_params['body']) && isset($_params['body']['headers']))
                {
                    $headers = $_params['body']['headers'];
                    unset($_params['body']['headers']);
                }
                else if (isset($_params['json']) && isset($_params['json']['headers']))
                {
                    $headers = $_params['json']['headers'];
                    unset($_params['json']['headers']);
                }

                $params = [
                    'headers'         => $headers + ['Authorization' => $auth]
                ] + $_params;
            }
            else
            {
                $params = $_params;
            }

            $this->sm->get('Log')->normal('[' . $method . '] ' . $path . $ressource . ' ' . json_encode($params));

            $data = $this->client->{ strtolower($method) }($path . $ressource, $params);

            $this->logRessource( $method, $ressource );
        }
        catch (\Exception $e)
        {
            if ($e instanceof \GuzzleHttp\Exception\ClientException)
                $error = json_decode( $e->getResponse()->getBody()->__toString() );
            else
                $error = null;


            preg_match('/Client error response \[url\] (.+) \[status code\] (\d+) \[reason phrase\] (.+)/', $e->getMessage(), $matches);
            if (count($matches) > 0)
            {
                list($original_message, $e_url, $e_code, $e_message) = $matches;

                $e = new WttjException((isset($error->error) && is_string($error->error) ? $error->error : $e_message), $e_code);

                if ($e->getCode() !== 404)
                    $id_error = $this->sm->get('ErrorTable')->logError($e);
                else
                    $id_error = null;

                if (isset($error->error))
                {
                    if (isset($error->error->error) && isset($error->error->error->description) && isset($error->error->error->reason))
                    {
                        if ($error->error->error->description === 'The access token was revoked' && $error->error->error->reason === 'revoked')
                        {
                            // set null token
                            $this->sm->get('Log')->error('TOKEN revoked (' . $this->access_token. ') now set to NULL.');

                            $this->sm->get('AtsTable')->unsetAccessToken( 'wttj', $this->access_token );
                        }
                    }
                }

                $error_message = (isset($error->error) && is_string($error->error) ? $error->error : $e_message);

                $this->sm->get('Log')->error($error_message);

                // log error
                $this->logApiCall($method, $ressource, $params, false, null, $id_error);

                if ($e->getCode() == 401 && $error_message === 'unauthorized')
                {
                    $this->sm->get('AtsTable')->unsetAccessToken( 'wttj', $this->access_token );
                    
                    return null;
                }
            }

            throw $e;
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
            $is_content = false;

            $tmp_ressource = explode('/', $ressource);
            $ressource = end($tmp_ressource);

            if (is_array($data))
            {
                $is_content = true;
                foreach ($data as $key => $d)
                    if (!is_numeric($key))
                        $is_content = false;
            }

            if (true === $is_content)
            {
                $data = array_map(function($item) use ($modelClass, $sm){
                    $model = new $modelClass();

                    $model->setServiceLocator( $sm );
                    $model->exchangeArray($item);

                    return $model;
                }, $data);
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
                    $json = $this->post('oauth/token', [
                        'grant_type'    => 'authorization_code',
                        'code'          => $code,
                        'client_id'     => $this->consumer_key,
                        'client_secret' => $this->consumer_secret,
                        'redirect_uri'  => $this->config['redirect_uri']
                    ]);

                    if (isset($json['access_token']))
                    {
                        $this->setAccessToken( $json['access_token'] );

                        $user = $this->get('users/current', ['organizations' => true]);
                    }
                    else
                    {
                        throw new WttjException( 'Welcome to the Jungle API error' );
                    }

                    $user = $this->formatUser( $user );

                    $this->user = $user;

                    return $user;
                }
                else
                {
                    throw new WttjException( 'Welcome to the Jungle API error' );
                }
            }
            else
            {
                throw new WttjException($error_description, $code);
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
        $id_organization = null;
        foreach ($user['organizations'] as $organization)
        {
            $id_organization = $organization['reference'];
            break;
        }

        $data = [
            'id_wttj'           => 'yborder_' . $user['email'],
            'first_name'        => $user['firstname'],
            'last_name'         => $user['lastname'],
            'avatar_url'        => $user['avatar_url'],
            'id_organization'   => $id_organization,
            'email'             => $user['email'],
            'access_token'      => $this->access_token
        ];

        return $data;
    }

    protected function getDatabaseColumns()
    {
        return array("id", "email", "first_name", "last_name", "access_token", 'avatar_url', 'id_organization', 'id_wttj');
    }

    public function getExcludeFunctions()
    {
        return (isset($this->config['exclude_function']) ? $this->config['exclude_function'] : []);
    }

    public function getJob( $id )
    {
        return $this->get('jobs/' . $id, ['stages' => true]);
    }

    public function isCandidateHired( $state )
    {
        return $state === 'hired';
    }

    public function isCandidateProcessClose( $state )
    {
        return true === in_array($state, ['refused']);
    }
}

class WttjException extends Exception
{

}
