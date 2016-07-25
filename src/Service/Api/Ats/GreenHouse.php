<?php

namespace Core\Service\Api\Ats;

use Zend\Http\Request;
use Core\Service\Api\AbstractAts;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Core\Model\Ats\Api\ResultListModel;
use GuzzleHttp\Post\PostFile;
use Application\Model\Ats\Greenhouse\JobPositionModel as GreenhouseJobPositionModel;


class GreenHouse extends AbstractAts implements ServiceLocatorAwareInterface
{
    private $api;
    private $consumer_key;
    private $consumer_secret;
    private $harvest_key;

    private $access_token;
    private $refresh_token;

    private $has_refresh = false;
    private $user;

    public function __construct($consumer_key, $consumer_secret)
    {
        $this->client           = new \GuzzleHttp\Client();
        $this->consumer_key     = $consumer_key;
        $this->consumer_secret  = $consumer_secret;

        $this->models           = [
            'jobs(\/[^\/]+){0,1}$'          => '\Application\Model\Ats\Greenhouse\JobModel',
            'job_posts$'     => '\Application\Model\Ats\Greenhouse\JobModel',
            'candidates(\/[^\/]+){0,1}$'    => '\Application\Model\Ats\Greenhouse\CandidateModel',
            'applications(\/[^\/]+){0,1}$'  => '\Application\Model\Ats\Greenhouse\HistoryModel',
        ];
    }

    public function getEmailFieldReplyTo()
    {
        return null;
    }

    public function tagCandidate( $id_api )
    {
        return '';
    }

    public function setAccessToken($access_token)
    {
        $this->access_token     = $access_token;
    }

    public function setHarvestKey( $harvest_key, $id_user = false )
    {
        $this->harvest_key = $harvest_key;

        if (true === $this->sm->get('Identity')->isLoggued())
        {
            $id_user = $this->sm->get('Identity')->user->id;
        }

        $ats_user          = $this->sm->get('UserTable')->getNetworkByUser('greenhouse', $id_user);

        if (null !== $ats_user && false === empty($ats_user['id_greenhouse']))
        {
            $this->setUser($ats_user);
        }
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
        // 300 ms sleep
        usleep(300000);

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
                $e = new GreenHouseException((isset($error->message) ? $error->message : $e_message), $e_code);

                $id_error = $this->sm->get('ErrorTable')->logError($e);
                $this->sm->get('Log')->error($e->getMessage());

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

    public function sendMessage( $id_api_candidate, $content, $share_with_everyone = false)
    {
        $params = [
            'headers'       => ['On-Behalf-Of' => $this->ats_user->id_greenhouse],
            'user_id'       => $this->ats_user->id_greenhouse,
            'body'          => 'YBorder: '. $content,
            'visibility'    => 'private' // always private ($share_with_everyone ? 'public' : 'private')
        ];

        return $this->json('candidates/' . $id_api_candidate . '/activity_feed/notes', true, $params);
    }

    public function getCandidateState( $id_api_candidate )
    {
        $ats        = $this->sm->get('AtsTable')->getAts( 'greenhouse' );
        $candidate  = $this->sm->get('AtsCandidateTable')->getByAPIID( $id_api_candidate, $ats['id_ats'] );
        $state      = null;
        $job_id     = $this->sm->get('AtsCandidateTable')->getValue($candidate['id_ats_candidate'], 'application_ids_0');

        if (null !== $job_id)
        {
            $application    = $this->get('applications/' . $job_id, true);

            if (null !== $application->current_stage)
            {
                $state = $application->current_stage['name'];
            }
        }

        return $state;
    }

    public function getCandidateHistory( $candidate )
    {
        // dd($candidate->application_ids);
        foreach ($candidate->application_ids as $application_id)
        {
            $histories  = $this->get('applications/' . $application_id, true);
            break;
        }

        $result = new ResultListModel();

        $result->setContent($histories);
        $result->setTotalFound(count($histories));

        return $result;
    }

    public function askInTouch( $id_api_candidate )
    {
        $content = 'A contact request has been sent.';

        return $this->sendMessage( $id_api_candidate, $content, true );
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

    public function isJobValid( $job )
    {
        $is_valid       = true;
        $text           = $job->getDescription();

        return $is_valid;
    }

    public function getJob( $id )
    {
        $job        = $this->get('jobs/' . $id, true);

        try
        {
            $job_post   = $this->get('jobs/' . $id . '/job_post', true);

            if (isset($job_post['content']))
                $job->content = $job_post['content'];

            if (isset($job_post['location']))
                $job->location = $job_post['location']['name'];
        }
        catch (GreenHouseException $e)
        {
            // do nothing, just no job post
        }

        return $job;
    }

    public function getJobs( $offset, $limit, $result_list = null )
    {
        $api_method     = 'GET';
        $api_ressource  = 'jobs';

        $params = [
            'per_page' => (int) $limit,
            'page'     => (int) $offset / (int) $limit + 1
        ];

        $ressource = $this->getRessource($api_method, $api_ressource);

        if (null !== $ressource)
        {
            if (null === $result_list)
            {
                if (!$ressource->can_fetch)
                    return new ResultListModel();

                $params['updated_after'] = date('Y-m-d\TH:i:s.000\Z', strtotime( $ressource->last_fetch_time ));
            }
            else
            {
                if (null !== $result_list->getParam('updated_after'))
                    $params['updated_after'] = $result_list->getParam('updated_after');
            }
        }

        $result = new ResultListModel();
        $data   = $this->get($api_ressource, true, $params);

        $result->setContent($data);
        $result->setTotalFound(count($data));
        $result->setParams( $params );

        if (count($data) > 0)
            $this->logRessource( $api_method, $api_ressource, true );
        else
            $this->logRessource( $api_method, $api_ressource, false );

        return $result;
    }

    public function getJobsPost( $offset, $limit, $result_list = null )
    {
        $api_method     = 'GET';
        $api_ressource  = 'job_posts';

        $params = [
            'per_page' => (int) $limit,
            'page'     => (int) $offset / (int) $limit + 1
        ];

        $ressource = $this->getRessource($api_method, $api_ressource);

        if (null !== $ressource)
        {
            if (null === $result_list)
            {
                if (!$ressource->can_fetch)
                    return new ResultListModel();

                $params['updated_after'] = date('Y-m-d\TH:i:s.000\Z', strtotime( $ressource->last_fetch_time ));
            }
            else
            {
                if (null !== $result_list->getParam('updated_after'))
                    $params['updated_after'] = $result_list->getParam('updated_after');
            }
        }

        $result = new ResultListModel();
        $data   = $this->get($api_ressource, true, $params);

        $result->setContent($data);
        $result->setTotalFound(count($data));
        $result->setParams( $params );

        if (count($data) > 0)
            $this->logRessource( $api_method, $api_ressource, true );
        else
            $this->logRessource( $api_method, $api_ressource, false );

        return $result;
    }

    public function getJobPositions( $job )
    {
        $result = new ResultListModel();
        $data   = [];

        foreach ($job->openings as $opening)
        {
            $model = new GreenhouseJobPositionModel();

            $model->exchangeArray( $opening );

            $data[] = $model;
        }

        $result->setContent($data);
        $result->setTotalFound(count($data));

        return $result;
    }

    public function isCandidateHired( $state )
    {
        return $state === 'hired';
    }

    public function isCandidateProcessClose( $state )
    {
        return $state === 'rejected';
    }

    public function getCandidates( $offset, $limit, $result_list = null )
    {
        $api_method     = 'GET';
        $api_ressource  = 'candidates';
        $params         = [
            'per_page' => (int) $limit,
            'page'     => (int) $offset / (int) $limit + 1
        ];

        $ressource = $this->getRessource($api_method, $api_ressource);

        if (null !== $ressource)
        {
            if (null === $result_list)
            {
                if (!$ressource->can_fetch)
                    return new ResultListModel();

                $params['updated_after'] = date('Y-m-d\TH:i:s.000\Z', strtotime( $ressource->last_fetch_time ));
            }
            else
            {
                if (null !== $result_list->getParam('updated_after'))
                    $params['updated_after'] = $result_list->getParam('updated_after');
            }
        }

        $result = new ResultListModel();
        $data   = $this->get($api_ressource, true, $params);

        $result->setContent($data);
        $result->setTotalFound(count($data));
        $result->setParams($params);

        if (count($data) > 0)
            $this->logRessource( $api_method, $api_ressource, true );
        else
            $this->logRessource( $api_method, $api_ressource, false );

        return $result;
    }

    public function getCompanyInformation()
    {
        return null;
    }

    public function getUrlCandidate( $id )
    {
        return 'https://app.greenhouse.io/people/' . $id;
    }

    public function updateCandidateState($id_api, $state)
    {
        if ($state === 'LEAD') return null;

        $action         = 'advance';
        $params         = ['headers' => ['On-Behalf-Of' => $this->ats_user->id_greenhouse]];

        $candidate      = $this->get('candidates/' . $id_api, true);
        $id_application = current($candidate->application_ids);

        if ($state === 'REJECTED')
            $action = 'reject';
        else
        {
            $application    = $this->get('applications/' . $id_application, true);

            if (null !== $application->current_stage)
            {
                $from_stage_id  = $application->current_stage['id'];

                if ($application->getState() !== 'active')
                    return null;

                $params        += ['from_stage_id' => $from_stage_id];
            }
        }

        return $this->json('applications/' . $id_application . '/' . $action, true, $params);
    }

    public function uploadCandidatePicture( $id_api, $picture )
    {
        // not possible at this time
        // can't upload in attachments (no image upload)
        return true;
    }

    public function uploadCandidateResume( $id_api, $pdf_link )
    {
        // upload the RESUME
        if (true === file_exists(ROOT_PATH . $pdf_link))
            $filepath_content = ROOT_PATH . $pdf_link;
        else
        {
            $pdf_link   = str_replace('public/', '', $pdf_link);
            $filepath_url = 'https://app.yborder.com/' . $pdf_link;
        }

        if (php_sapi_name() === 'cli')
            echo 'filepath : ' . (isset($filepath_content) ? $filepath_content : $filepath_url) . PHP_EOL;

        $params = [
            'type'      => 'resume',
            'headers'   => ['On-Behalf-Of' => $this->ats_user->id_greenhouse],
            'filename'  => $id_api . '_resume_yborder.pdf'
        ];

        if (isset($filepath_content))
        {
            $params['content'] = new PostFile('file', file_get_contents($filepath_content));
        }
        else
        {
            $params['url'] = $filepath_url;
        }

        try
        {
            $this->json('candidates/' . $id_api . '/attachments', true, $params);
        }
        catch (\Exception $e)
        {
            // if error : do nothing. Reason : Same image so do not need to update.
            return false;
        }

        return true;
    }

    public function createCandidate( $model )
    {
        $return     = $this->json('candidates', false, $model->toAPI());
        $model->id  = $return->id;

        // add tags
        $this->updateCandidate($model);

        return $return;
    }

    public function updateCandidate( $model )
    {
        $data = $model->toAPI();

        $params = [
            'headers'   => ['On-Behalf-Of' => $this->ats_user->id_greenhouse],
        ] + $data;

        return $this->patch('candidates/' . $model->id, true, $params);
    }

    public function addCandidateQualification( $model )
    {
        $data = $model->getQualification();

        return $this->sendMessage($model->id, $data, true);
    }

    public function getJobId( $id_ats_candidate )
    {
        $state  = null;
        $job_id = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'application_ids_0');

        if (null !== $job_id)
        {
            $application    = $this->get('applications/' . $job_id, true);

            if (null !== $application->current_stage)
            {
                $state = $application->current_stage['name'];
            }
        }

        return [$job_id, $state];
    }
}

class GreenHouseException extends Exception
{

}
