<?php

namespace Core\Service\Api\Ats;

use Zend\Http\Request;

trait GreenhouseTrait
{
    protected $harvest_key;

    protected $access_token;
    protected $refresh_token;

    protected $has_refresh = false;

    public function setAccessToken($access_token)
    {
        $this->access_token     = $access_token;
    }

    public function setUserHarvestKey( $harvest_key )
    {
        $this->harvest_key = $harvest_key;
    }

    public function getLoginUrl($data)
    {
        // mob.local/company/user/login/greenhouse
        $scopes         = $this->config['scopes'];
        $redirect_uri   = $this->config['redirect_uri'];
        $url            = 'https://app.greenhouse.io/oauth/authorize?client_id=' . $this->consumer_key . '&redirect_uri=' . rawurlencode($redirect_uri) . '&scope=' . rawurlencode(implode(' ', $scopes)) . '&response_type=token';

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

    public function post( $ressource, $is_harvest = false, $_params = [] )
    {
        return $this->request('POST', $ressource, ['body' => $_params], $is_harvest);
    }

    public function put( $ressource, $is_harvest = false, $_params = [] )
    {
        return $this->request('PUT', $ressource, ['json' => $_params], $is_harvest);
    }

    public function patch( $ressource, $is_harvest = false, $_params = [] )
    {
        return $this->request('PATCH', $ressource, ['json' => $_params], $is_harvest);
    }

    public function json( $ressource, $is_harvest = false, $_params = [] )
    {
        return $this->request('POST', $ressource, ['json' => $_params], $is_harvest);
    }

    public function get( $ressource, $is_harvest = false, $_params = [] )
    {
        return $this->request('GET', $ressource, ['query' => $_params], $is_harvest);
    }

    public function request( $method, $ressource, $_params, $is_harvest = false )
    {
        // https://developers.greenhouse.io/harvest.html#throttling
        // API requests are limited to 40 calls per 10 seconds
        // 500 ms sleep
        usleep(500000);

        if (true === $is_harvest)
        {
            if (true === empty($this->harvest_key))
                return null;
            // https://developers.greenhouse.io/harvest.html?shell#authentication
            // The username is your Greenhouse API token and the password should be blank
            $path   = 'https://harvest.greenhouse.io/v1/';
            $auth   = 'Basic ' . base64_encode($this->harvest_key . ':');
        }
        else
        {
            $path   = 'https://api.greenhouse.io/v1/partner/';
            $auth   = 'Bearer ' . $this->access_token;
        }

        try
        {
            if (!empty($this->access_token) || $is_harvest)
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
            // Client error response [url] https://api.smartrecruiters.com/configuration/company [status code] 401 [reason phrase] Unauthorized
            preg_match('/Client error response \[url\] (.+) \[status code\] (\d+) \[reason phrase\] (.+)/', $e->getMessage(), $matches);
            if (count($matches) > 0)
            {
                list($original_message, $e_url, $e_code, $e_message) = $matches;
                $error_message = (isset($error->message) ? $error->message : $e_message);

                $e = new GreenHouseException($error_message, $e_code);

                if ($error_message === 'Resource not found')
                    $id_error = null;
                else
                    $id_error = $this->sm->get('ErrorTable')->logError($e);

                $this->sm->get('Log')->error((isset($error->message) ? $error->message : $e_message));

                // log error
                $this->logApiCall($method, $ressource, $params, false, null, $id_error);
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

    /**
     * Must be called when the callback url for an api is called
     * @param Request $request
     */
    public function callbackRequest(Request $request)
    {
        $error          = null;
        $access_token   = $request->getQuery()->get( 'access_token', NULL );

        if (null === $access_token)
        {
            throw new \Core\Exception\NewLayoutException('greenhouse', 4);
        }

        try
        {
            if ( NULL === $error )
            {
                if ( NULL !== $access_token )
                {
                    $this->setAccessToken( $access_token );

                    $user = $this->get('current_user');

                    $user['id_greenhouse'] = 'yb' . generate_token(30);

                    $this->user = $user;

                    return $user;
                }
                else
                {
                    throw new GreenHouseException( 'SmartRecruiters API error' );
                }
            }
            else
            {
                throw new GreenHouseException($error_description, $code);
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
        return array("id", "email", "first_name", "last_name", "access_token", 'id_greenhouse');
    }

    public function searchCompany( $query )
    {
        if (empty($query)) return null;

        try
        {
            $data = $this->client->get('https://api.greenhouse.io/v1/boards/' . $query . '/embed/jobs');

            return $data->json();
        }
        catch(\Exception $e)
        {
            return null;
        }
    }

    public function getExcludeFunctions()
    {
        return (isset($this->config['exclude_function']) ? $this->config['exclude_function'] : []);
    }

    public function isCandidateHired( $state )
    {
        return $state === 'hired';
    }

    public function isCandidateProcessClose( $state )
    {
        return $state === 'rejected';
    }
}

class GreenHouseException extends Exception
{

}
