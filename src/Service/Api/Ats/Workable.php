<?php

namespace Core\Service\Api\Ats;

use Zend\Http\Request;
use Core\Service\Api\AbstractAts;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Core\Model\Ats\Api\ResultListModel;
use GuzzleHttp\Post\PostFile;
// use Application\Model\Ats\Lever\JobPositionModel as LeverJobPositionModel;
// use Application\Model\Ats\Lever\HistoryModel as LeverHistoryModel;


class Workable extends AbstractAts implements ServiceLocatorAwareInterface
{
    private $api;
    private $consumer_key;
    private $consumer_secret;
    private $harvest_key;

    private $access_token;
    private $refresh_token;

    private $has_refresh = false;

    private $stages;
    private $archive_reasons;

    public function __construct()
    {
        $this->client           = new \GuzzleHttp\Client();

        $this->models           = [
            'jobs(\/[^\/]+){0,1}$'      => '\Application\Model\Ats\Workable\JobModel',
            // 'candidates(\/[^\/]+){0,1}$'    => '\Application\Model\Ats\Lever\CandidateModel',
        ];
    }

    public function setRessourceJobs( $have_data )
    {
        $this->logRessource( 'GET', 'postings', $have_data );

        return true;
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
                $e = new WorkableException((isset($error->message) ? $error->message : $e_message), $e_code);

                $id_error = $this->sm->get('ErrorTable')->logError($e);
                $this->sm->get('Log')->error($e->getMessage());

                // log error
                $this->logApiCall($method, $ressource, $params, false, null, $id_error);
            }

            throw $e;
        }
        $data   = $data->json();

        var_dump($data);
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
            'access_token'      => $this->access_token,
            'refresh_token'     => $this->refresh_token
        ];

        return $data;
    }

    public function getUser()
    {
        return $this->ats_user;
    }
    protected function getDatabaseColumns()
    {
        return array("id", "email", "first_name", "last_name", "access_token", 'id_lever');
    }

    public function sendMessage( $id_api_candidate, $content, $share_with_everyone = false)
    {
        // $candidate  = $this->getCandidateAtsByAPIID($id_api_candidate);

        // if (null === $candidate) return null;
        // // get old content messages
        // $history = $this->getLogMessageHistory( $id_api_candidate );

        // $query = [
        //     'perform_as'    => $this->ats_user->id_lever,
        //     'dedupe'        => "true"
        // ];

        // $body = [
        //     'files[]'       => new PostFile('files[0]',  date('Y-m-d H:i:s') . PHP_EOL . $content . PHP_EOL . PHP_EOL . $history, 'yborder_actions.txt'),
        //     'emails'        => [
        //         self::formatCandidate($candidate['token'])
        //     ]
        // ];

        // $json = [
        // ];

        // $this->logSendMessage($id_api_candidate, $content);

        // return $this->request('POST', 'candidates', ['query' => $query, 'json' => $json, 'body' => $body]);
    }

    public function getCandidateState( $id_api_candidate )
    {
        // $candidate          = $this->sm->get('AtsCandidateTable')->getByAPIID( $id_api_candidate, $this->ats['id_ats'] );
        // $state              = null;
        // $archive_reason     = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'archived_reason');
        // $stage              = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'stage');
        // $job_id             = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'applications_0');

        // if (null !== $archive_reason)
        // {
        //     $archive_reasons    = $this->getArchiveReasons();
        //     $state              = $archive_reasons[ $archive_reason ];
        // }
        // else if (null !== $job_id)
        // {
        //     $state              = $stages[ $job_id ];
        // }

        // return $state;
    }

    public function getCandidateHistory( $candidate )
    {
        // $histories          = [];
        // $stages             = $this->getStages();
        // $archive_reasons    = $this->getArchiveReasons();

        // foreach ($candidate->stageChanges as $stage)
        // {
        //     $model = new LeverHistoryModel();

        //     $stage['status'] = $stages[ $stage['toStageId'] ];
        //     $model->exchangeArray( $stage );

        //     $histories[] = $model;
        // }

        // if (isset($candidate->archived) && isset($candidate->archived['reason']))
        // {
        //     $model = new LeverHistoryModel();

        //     $stage['status'] = $archive_reasons[ $candidate->archived['reason'] ];
        //     $model->exchangeArray( $stage );

        //     $histories[] = $model;
        // }

        // $result = new ResultListModel();

        // $result->setContent($histories);
        // $result->setTotalFound(count($histories));

        // return $result;
    }

    public function askInTouch( $id_api_candidate )
    {
        // $content = 'A contact request has been sent.';

        // return $this->sendMessage( $id_api_candidate, $content, true );
    }

    public function searchCompany( $query )
    {
        return null;
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
        return $this->get('jobs/' . $id);
    }

    public function getJobsPost( $offset, $limit, $result_list = null )
    {
        // no jobs post
        return new ResultListModel();
    }

    public function getJobs( $offset, $limit, $result_list = null )
    {
        $api_method     = 'GET';
        $api_ressource  = 'jobs';
        $params         = [
            'limit'     => (int) $limit
        ];

        $ressource = $this->getRessource($api_method, $api_ressource);

        if (null !== $result_list)
        {
            if (null !== $result_list->getOffset())
            {
                $params['since_id'] = $result_list->getOffset();
            }
            else
            {
                return new ResultListModel();
            }

            // if (null !== $result_list->getParam('updated_after'))
            //     $params['updated_after'] = $result_list->getParam('updated_after');
        }
        else
        {
            if (null !== $ressource)
            {
                if (!$ressource->can_fetch)
                    return new ResultListModel();

                // $params['updated_after'] = date('Y-m-d\TH:i:s.000\Z', strtotime( $ressource->last_fetch_time ));
            }
        }

        $result = new ResultListModel();
        $data   = $this->get($api_ressource, $params);

        $result->setContent($data['jobs']);
        $result->setTotalFound(count($data['jobs']));
        $result->setParams($params);

        if (count($data['jobs']) > 0)
            $this->logRessource( $api_method, $api_ressource, true );
        else
            $this->logRessource( $api_method, $api_ressource, false );

        if (isset($data['paging']) && isset($data['paging']['next']))
        {
            $query = parse_url($data['paging']['next'], \PHP_URL_QUERY);
            parse_str($query, $params_query);
            $result->setOffset($params_query['since_id']);
        }

        return $result;
    }

    public function getJobPositions( $job )
    {
        // $result = new ResultListModel();
        // $data   = [];

        // $model = new LeverJobPositionModel();

        // $model->exchangeArray( $job->toArray() );

        // $data[] = $model;

        // $result->setContent($data);
        // $result->setTotalFound(count($data));

        // return $result;
    }

    public function isCandidateHired( $state )
    {
        // return $state === 'Hired';
    }

    public function isCandidateProcessClose( $state )
    {
        // return true === in_array($state, ['Underqualified', 'Unresponsive', 'Timing', 'Withdrew', 'Offer declined', 'Position closed']);
    }

    public function getCandidates( $offset, $limit, $result_list = null )
    {
        $api_method     = 'GET';
        $api_ressource  = 'jobs/candidates';

        $params         = [
            'offset'    => (int) $offset,
            'limit'     => (int) $limit
        ];

        $ressource = $this->getRessource($api_method, $api_ressource);

        $jobs_shortcode = $this->getAtsJobs();

        dd($jobs_shortcode);
        // if (null !== $ressource)
        // {
        //     if (null === $result_list)
        //     {
        //         if (!$ressource->can_fetch)
        //             return new ResultListModel();

        //         $params['updated_after'] = date('Y-m-d\TH:i:s.000\Z', strtotime( $ressource->last_fetch_time ));
        //     }
        //     else
        //     {
        //         if (null !== $result_list->getParam('updated_after'))
        //             $params['updated_after'] = $result_list->getParam('updated_after');
        //     }
        // }

        $result = new ResultListModel();
        $data   = $this->get($api_ressource, $params);

        $result->setContent($data['jobs']);
        $result->setTotalFound(count($data['jobs']));
        $result->setParams($params);

        if (count($data['jobs']) > 0)
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
        return 'https://hire.sandbox.lever.co/candidates/' . $id;
    }

    public function updateCandidateState($id_api, $state)
    {
        // $query  = [
        //     'perform_as'    => $this->ats_user->id_lever
        // ];

        // if ($state === 'REJECTED')
        // {
        //     $archive_reasons    = $this->getArchiveReasons();

        //     foreach ($archive_reasons as $id_reason => $reason)
        //     {
        //         if ('Withdrew' === $reason)
        //         {
        //             $body_reason = $id_reason;
        //             break;
        //         }
        //     }

        //     if (!isset($body_reason)) return null;
        //     // PUT /candidates/:candidate/archived

        //     $body   = [
        //         'reason'        => $body_reason
        //     ];

        //     return $this->request('PUT', 'candidates/' . $id_api . '/archived', ['query' => $query, 'body' => $body]);
        // }

        // switch ($state)
        // {
        //     case 'NEW' :        $new_state = 'New lead';    break;
        //     case 'IN_REVIEW' :  $new_state = 'Reached out'; break;
        //     default:
        //         return null;
        // }

        // $stages = $this->getStages();

        // foreach ($stages as $id_sage => $stage)
        // {
        //     if ($new_state === $stage)
        //     {
        //         $body_stage = $id_sage;
        //         break;
        //     }
        // }

        // if (!isset($body_stage)) return null;

        // $body   = [
        //     'stage'        => $body_stage
        // ];

        // return $this->request('PUT', 'candidates/' . $id_api . '/stage', ['query' => $query, 'body' => $body]);
    }

    public function uploadCandidatePicture( $id_api, $picture )
    {
        // $candidate  = $this->getCandidateAtsByAPIID($id_api);

        // if (true === file_exists(ROOT_PATH . '/public/' . $picture))
        //     $filepath_content   = ROOT_PATH . '/public/' . $picture;
        // else
        //     $filepath_content       = 'https://app.yborder.com' . $picture;

        // if (php_sapi_name() === 'cli')
        //     echo 'filepath : ' . (isset($filepath_content) ? $filepath_content : $filepath_url) . PHP_EOL;

        // $query = [
        //     'perform_as'    => $this->ats_user->id_lever,
        //     'dedupe'        => "true"
        // ];

        // $body = [
        //     'files[]'       => new PostFile('files[0]', file_get_contents($filepath_content), 'picture.jpg'),
        //     'emails'        => [
        //         self::formatCandidate($candidate['token'])
        //     ]
        // ];

        // $json = [
        // ];

        // try
        // {
        //     $this->request('POST', 'candidates', ['query' => $query, 'json' => $json, 'body' => $body]);
        // }
        // catch (\Exception $e)
        // {
        //     // if error : do nothing. Reason : Same image so do not need to update.
        //     return false;
        // }

        // return true;
    }

    public function uploadCandidateResume( $id_api, $pdf_link )
    {
        // $candidate  = $this->getCandidateAtsByAPIID($id_api);
        // // upload the RESUME
        // if (true === file_exists(ROOT_PATH . $pdf_link))
        //     $filepath_content = ROOT_PATH . $pdf_link;
        // else
        // {
        //     $pdf_link           = str_replace('public/', '', $pdf_link);
        //     $filepath_content   = 'https://app.yborder.com/' . $pdf_link;
        // }

        // if (php_sapi_name() === 'cli')
        //     echo 'filepath : ' . (isset($filepath_content) ? $filepath_content : $filepath_url) . PHP_EOL;

        // $query = [
        //     'perform_as'    => $this->ats_user->id_lever,
        //     'dedupe'        => "true"
        // ];

        // $body = [
        //     'resumeFile'    => new PostFile('resumeFile', file_get_contents($filepath_content), 'resume.pdf'),
        //     'emails'        => [
        //         self::formatCandidate($candidate['token'])
        //     ]
        // ];

        // $json = [
        //     'emails'        => [
        //         self::formatCandidate($candidate['token'])
        //     ]
        // ];

        // return $this->request('POST', 'candidates', ['query' => $query, 'json' => $json, 'body' => $body]);
    }

    public function createCandidate( $model )
    {
        // $params             = $model->toAPI();
        // // update if same email address
        // $query = [
        //     'perform_as'    => $this->ats_user->id_lever,
        //     'dedupe'        => "true"
        // ];

        // return $this->request('POST', 'candidates', ['query' => $query, 'json' => $params]);
    }

    public function updateCandidate( $model )
    {
        // return $this->createCandidate($model);
    }

    public function addCandidateQualification( $model )
    {
        // $candidate  = $this->getCandidateAtsByAPIID($model->id);
        // $data       = $model->getQualification();

        // $query = [
        //     'perform_as'    => $this->ats_user->id_lever,
        //     'dedupe'        => "true"
        // ];

        // $body = [
        //     'files[]'       => new PostFile('files[0]', $data, 'qualification.txt'),
        //     'emails'        => [
        //         self::formatCandidate($candidate['token'])
        //     ]
        // ];

        // $json = [
        // ];

        // return $this->request('POST', 'candidates', ['query' => $query, 'json' => $json, 'body' => $body]);
    }

    public function getJobId( $id_ats_candidate )
    {
        // $state              = null;
        // $archive_reason     = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'archived_reason');
        // $stage              = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'stage');
        // $job_id             = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'applications_0');

        // if (null !== $archive_reason)
        // {
        //     $archive_reasons    = $this->getArchiveReasons();
        //     $state              = $archive_reasons[ $archive_reason ];
        // }
        // else if (null !== $job_id)
        // {
        //     var_dump($stage);
        //     $state              = $stage[ $job_id ];
        // }

        // return [$job_id, $state];
    }

    private function getStages()
    {
        // if (isset($this->stages))
        //     return $this->stages;

        // $stages     = $this->get('stages', ['limit' => 100]);
        // $data       = [];

        // foreach ($stages['data'] as $stage)
        // {
        //     $data[ $stage['id'] ] = $stage['text'];
        // }

        // $this->stages = $data;

        // return $data;
    }

    private function getArchiveReasons()
    {
        // if (isset($this->archive_reasons))
        //     return $this->archive_reasons;

        // $archive_reasons     = $this->get('archive_reasons', ['limit' => 100]);
        // $data       = [];

        // foreach ($archive_reasons['data'] as $archive_reason)
        // {
        //     $data[ $archive_reason['id'] ] = $archive_reason['text'];
        // }

        // $this->archive_reasons = $data;

        // return $data;
    }

    static public function formatCandidate( $data )
    {
        // return 'candidate+' . $data . '@mobiskill.fr';
    }
}

class WorkableException extends Exception
{

}
