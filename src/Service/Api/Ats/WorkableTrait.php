<?php

namespace Core\Service\Api\Ats;

use Zend\Http\Request;
use \Core\Service\Api\AbstractCoreAts;

Trait WorkableTrait
{
    protected $api;

    private $access_token;
    public $already_refresh = false;
    public $refresh_token = null;

    public function getEmailFieldReplyTo()
    {
        return null;
    }

    public function setAccessToken($access_token, $refresh_token = null, $refresh = true)
    {
        $this->access_token     = $access_token;

        if ($refresh_token)
            $this->refresh_token     = $refresh_token;

        if ($refresh)
            $this->has_refresh      = false;
    }

    public function getLoginUrl($data)
    {
        $client_id      = $this->consumer_key;
        $scopes         = $this->config['scopes'];
        $redirect_uri   = urlencode( $this->config['redirect_uri'] );
        $url            = $this->config['domain_login'] . 'oauth_signin?action=new&client_id=' . $client_id . '&controller=oauth_authorizations&redirect_uri=' . $redirect_uri . '&resource=user&response_type=code&scope=' . implode('+', $scopes) . '&type=oauth';

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

    public function request( $method, $ressource, $_params = false )
    {
        // 500 ms sleep
        usleep(500000);

        // The username is your Lever API token and the password should be blank
        $path   = $this->config['url'];
        $auth   = 'Bearer ' . $this->access_token;

        if (null !== $this->ats_user)
        {
            $path = str_replace('www.', $this->ats_user->subdomain . '.', $path);
        }

        if ($ressource === 'oauth/token')
        {
            $path = $this->config['domain_login'];
        }

        // dd($path);

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

                            $this->sm->get('AtsTable')->unsetAccessToken( 'workable', $this->access_token );
                        }
                    }
                }

                $error_message = (isset($error->error) && is_string($error->error) ? $error->error : $e_message);

                $this->sm->get('Log')->error($error_message);

                if ($e->getCode() == 401 && $error_message === 'Unauthorized')
                {
                    if ($this->already_refresh || !$this->refresh_token)
                    {
                         $this->sm->get('Log')->error('TOKEN revoked (' . $this->access_token. ') now set to NULL.');
                         $this->sm->get('AtsTable')->unsetAccessToken( 'workable', $this->access_token );

                         // log error
                         $this->logApiCall($method, $ressource, $params, false, null, $id_error);
                         return null;
                    }

                    $this->already_refresh = true;
                    // try to refresh access token
                    $json = $this->request('POST', 'oauth/token', [
                        'body'  => [
                            'grant_type'    => 'refresh_token',
                            'client_id'     => $this->consumer_key,
                            'client_secret' => $this->consumer_secret,
                            'refresh_token' => $this->refresh_token
                        ]
                    ]);

                    if (isset($json['access_token']) && isset($json['refresh_token']))
                    {
                        $this->sm->get('Log')->error('Replay action');
                        $this->sm->get('UserTable')->refreshToken( 'workable', $this->access_token, $this->refresh_token, $json['access_token'], $json['refresh_token'] );

                        $this->setAccessToken( $json['access_token'], $json['refresh_token'], false );

                        return $this->request( $method, $ressource, $_params );
                    }
                    else
                    {
                        $this->sm->get('Log')->error('TOKEN revoked (' . $this->access_token. ') now set to NULL.');
                        $this->sm->get('AtsTable')->unsetAccessToken( 'workable', $this->access_token );

                        // log error
                        $this->logApiCall($method, $ressource, $params, false, null, $id_error);
                    }
                    
                    return null;
                }
            }


            // log error
            $this->logApiCall($method, $ressource, $params, false, null, $id_error);

            throw $e;
        }
        $data   = $data->json();
        $found  = false;
        $this->already_refresh = false;

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
                elseif (isset($data['candidate']))
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
        $identity_user      = $this->sm->get("Identity")->user;
        $code               = $request->getQuery()->get( 'code', NULL );
        $error              = $request->getQuery()->get( 'error', NULL );
        $error_description  = $request->getQuery()->get( 'error_description', NULL );


        try
        {
            if ( NULL === $error )
            {
                if ( NULL !== $code )
                {
                    // https://www.workablesandbox.com/oauth/token
                    $json = $this->request('POST', 'oauth/token', [
                        'body'  => [
                            'grant_type'    => 'authorization_code',
                            'code'          => $code,
                            'client_id'     => $this->consumer_key,
                            'client_secret' => $this->consumer_secret,
                            'redirect_uri'  => $this->config['redirect_uri']
                        ]
                    ]);


                    if (isset($json['access_token']) && isset($json['refresh_token']))
                    {
                        $this->setAccessToken( $json['access_token'], $json['refresh_token'] );

                        $key        = $json['access_token'] ;
                        $me         = $this->get('accounts');
                        $ats_user   = $this->sm->get('UserTable')->getNetworkByUser( 'workable', $identity_user->id );
                        $ats        = $this->sm->get('AtsTable')->getAts( 'workable' );

                        if (null === $ats_user)
                        {
                            // no the original connector
                            $ats_user = $this->sm->get('UserTable')->getAtsByCompany( 'workable', $identity_user->getCompany()->id_company );
                        }

                        if (null !== $me)
                        {
                            if (null === $ats_user)
                            {
                                // create the user because no auth
                                $updated = $this->sm->get('UserTable')->insertNetworkByUser('workable', $identity_user->id, [
                                    'access_token'      => $key,
                                    'refresh_token'     => $json['refresh_token'],
                                    'subdomain'         => $me['accounts'][0]['subdomain']
                                ]);
                            }
                            else
                            {
                                $updated = $this->sm->get('UserTable')->updateNetworkByUser('workable', $identity_user->id, [
                                    'access_token'   => $key,
                                    'refresh_token'     => $json['refresh_token'],
                                    'subdomain'   => $me['accounts'][0]['subdomain']
                                ]);
                            }
                        }
                        $ats_user = $this->sm->get('UserTable')->getNetworkByUser('workable', $identity_user->id);

                        $this->setUser($ats_user);

                        $found  = false;
                        $page   = 1;
                        $offset = null;
                        $first  = null;

                        if (null === $this->sm->get('AtsCompanyTable')->getByIDCompany( $identity_user->getCompany()->id_company ))
                            $this->sm->get('AtsCompanyTable')->saveCompany( $identity_user->getCompany()->name, $ats['id_ats'], $identity_user->getCompany()->name, $identity_user->getCompany()->id_company );

                        while (false === $found)
                        {
                            $params = ['limit' => 0];

                            if (null !== $offset)
                                $params['offset'] = $offset;

                            $users = $this->get('members', $params);

                            if (count($users['members']) === 0)
                                break;

                            foreach ($users['members'] as $u)
                            {
                                if (!$first)
                                    $first = $u;
                                if ($u['email'] === $identity_user->email || $identity_user->first_name . ' ' . $identity_user->last_name === $u['name'])
                                {
                                    $updated += $this->sm->get('UserTable')->updateNetworkByUser('workable', $identity_user->id, [
                                        'id_workable'   => $u['id'],
                                        'name'          => $u['name'],
                                        'role'          => $u['role'],
                                        'headline'      => $u['headline'],
                                        'first_name'    => $u['name'],
                                        'last_name'     => $u['name'],
                                        'email'         => $u['email']
                                    ]);

                                    $user = $u;

                                    $found = true;
                                    break;
                                }
                            }

                            // no pagination ?
                            break;
                        }

                        if (false === $found && $first)
                        {
                            $user = $first;
                            $updated += $this->sm->get('UserTable')->updateNetworkByUser('workable', $identity_user->id, [
                                'id_workable'   => $user['id'],
                                'name'          => $user['name'],
                                'role'          => $user['role'],
                                'headline'      => $user['headline'],
                                'first_name'    => $user['name'],
                                'last_name'     => $user['name'],
                                'email'         => $user['email']
                            ]);
                        }
                    }
                    else
                    {
                        throw new WorkableException( 'Workable API error' );
                    }

                    $user = $this->formatUser( $user );

                    $this->user = $user;

                    return $user;
                }
                else
                {
                    throw new WorkableException( 'SmartRecruiters API error : no code' );
                }
            }
            else
            {
                throw new WorkableException($error_description, $code);
            }
        }
        catch( \Exception $e )
        {
            $this->sm->get('ErrorTable')->logError($e);

            return NULL;
        }

        return NULL;
    }

    private function formatUser( $users )
    {
        $user = $users[0];
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
