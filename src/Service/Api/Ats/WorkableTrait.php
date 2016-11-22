<?php

namespace Core\Service\Api\Ats;

use Zend\Http\Request;
use \Core\Service\Api\AbstractCoreAts;

Trait WorkableTrait
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
        // no auth
        return null;
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

    public function request( $method, $ressource, $_params = false )
    {
        // 500 ms sleep
        usleep(500000);

        // The username is your Lever API token and the password should be blank
        $path   = 'https://www.workable.com/spi/v3/accounts/';
        $auth   = 'Bearer ' . $this->access_token;

        if (null !== $this->ats_user)
            $path .= $this->ats_user->subdomain . '/';

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

                $e = new WorkableException((isset($error->error) && is_string($error->error) ? $error->error : $e_message), $e_code);

                $id_error = $this->sm->get('ErrorTable')->logError($e);
                $this->sm->get('Log')->error((isset($error->error) ? $error->error : $e_message));

                // log error
                $this->logApiCall($method, $ressource, $params, false, null, $id_error);
            }

            throw $e;
        }
        $data   = $data->json();

        // var_dump($data);
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

            if (isset($data[$ressource]) && is_array($data[$ressource]))
            {
                $is_content = true;
                foreach ($data[$ressource] as $key => $d)
                    if (!is_numeric($key))
                        $is_content = false;
            }

            if (true === $is_content)
            {
                $data[$ressource] = array_map(function($item) use ($modelClass, $sm){
                    $model = new $modelClass();

                    $model->setServiceLocator( $sm );
                    $model->exchangeArray($item);

                    return $model;
                }, $data[$ressource]);
            }
            else
            {
                $model = new $modelClass();

                $model->setServiceLocator( $sm );
                if (isset($data[$ressource]))
                    $model->exchangeArray($data[$ressource]);
                elseif ($ressource === 'candidates' && isset($data['candidate']))
                    $model->exchangeArray($data['candidate']);
                else
                    $model->exchangeArray($data);

                $data = $model;
            }
        }

        return $data;
    }

    public function callbackRequest(Request $request)
    {
        // no auth
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
            'access_token'      => $this->access_token
        ];

        return $data;
    }

    public function getUser()
    {
        return $this->ats_user;
    }
    protected function getDatabaseColumns()
    {
        return array("id", "email", "first_name", "last_name", "access_token", 'id_workable');
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
        return $state === 'Hired';
    }

    public function isCandidateProcessClose( $state )
    {
        return true === in_array($state, ['Disqualified']);
    }
}

class WorkableException extends Exception
{

}
