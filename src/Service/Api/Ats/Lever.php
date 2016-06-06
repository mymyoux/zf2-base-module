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
use Core\Model\Ats\Api\ResultListModel;
use GuzzleHttp\Post\PostFile;
use Application\Model\Ats\Lever\JobPositionModel as LeverJobPositionModel;
use Application\Model\Ats\Lever\HistoryModel as LeverHistoryModel;


class Lever extends AbstractAts implements ServiceLocatorAwareInterface
{
    /**
     * @var \Twitter\Twitter
     */
    private $api;
    private $consumer_key;
    private $consumer_secret;
    private $harvest_key;

    private $access_token;
    private $refresh_token;

    private $has_refresh = false;

    public function __construct()
    {
        $this->client           = new \GuzzleHttp\Client();

        $this->models           = [
            'postings(\/[^\/]+){0,1}$'          => '\Application\Model\Ats\Lever\JobModel',
            'candidates(\/[^\/]+){0,1}$'    => '\Application\Model\Ats\Lever\CandidateModel',
            // 'applications(\/[^\/]+){0,1}$'    => '\Application\Model\Ats\Lever\HistoryModel',
        ];

        $this->user         = new \stdClass();
        $this->user->id     = null;
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
        $this->config   = $apis['lever'];
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
    /**
     * @inheritDoc
     */
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
        // The username is your Lever API token and the password should be blank
        $path   = 'https://api.sandbox.lever.co/v1/';
        $auth   = 'Basic ' . base64_encode($this->access_token . ':');

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
        }
        catch (\Exception $e)
        {
            preg_match('/Client error response \[url\] (.+) \[status code\] (\d+) \[reason phrase\] (.+)/', $e->getMessage(), $matches);
            if (count($matches) > 0)
            {
                list($original_message, $e_url, $e_code, $e_message) = $matches;
                $e = new LeverException($e_message, $e_code);
            }

            throw $e;
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
            $is_content = false;

            if (is_array($data['data']))
            {
                $is_content = true;
                foreach ($data['data'] as $key => $d)
                    if (!is_numeric($key))
                        $is_content = false;
            }

            if (true === $is_content)
            {
                $data['data'] = array_map(function($item) use ($modelClass, $sm){
                    $model = new $modelClass();

                    $model->setServiceLocator( $sm );
                    $model->exchangeArray($item);

                    return $model;
                }, $data['data']);
            }
            else
            {
                $model = new $modelClass();

                $model->setServiceLocator( $sm );
                if (isset($data['data']))
                    $model->exchangeArray($data['data']);
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
        return $this->user;
    }
    protected function getDatabaseColumns()
    {
        return array("id", "email", "first_name", "last_name", "access_token", 'id_lever');
    }

    public function sendMessage( $id_api_candidate, $content, $share_with_everyone = false)
    {
        // $params = [
        //     'headers'       => ['On-Behalf-Of' => $this->user->id],
        //     'user_id'       => $this->user->id,
        //     'body'          => 'YBorder: '. $content,
        //     'visibility'    => ($share_with_everyone ? 'public' : 'private')
        // ];

        // return $this->json('candidates/' . $id_api_candidate . '/activity_feed/notes', true, $params);
    }
    /**
     * Get candidate current state
     *
     * @param  string $id_api_candidate [description]
     * @return array                    [description]
     */
    public function getCandidateState( $id_api_candidate )
    {
        // $ats        = $this->sm->get('AtsTable')->getAts( 'greenhouse' );
        // $candidate  = $this->sm->get('AtsCandidateTable')->getByAPIID( $id_api_candidate, $ats['id_ats'] );
        // $state      = null;
        // $job_id     = $this->sm->get('AtsCandidateTable')->getValue($candidate['id_ats_candidate'], 'application_ids_0');

        // if (null !== $job_id)
        // {
        //     $application    = $this->get('applications/' . $job_id, true);

        //     if (null !== $application->current_stage)
        //     {
        //         $state = $application->current_stage['name'];
        //     }
        // }

        // return $state;
    }

    /**
     * Get candidate state history
     *
     * @param  AtsCandidateModel $candidate ID of the job
     * @return ResultListModel   History list
     */
    public function getCandidateHistory( $candidate )
    {
        $stages     = $this->get('stages', ['limit' => 100]);
        $data       = [];
        $histories  = [];

        foreach ($stages['data'] as $stage)
        {
            $data[ $stage['id'] ] = $stage['text'];
        }

        foreach ($candidate->stageChanges as $stage)
        {
            $model = new LeverHistoryModel();

            $stage['status'] = $data[ $stage['toStageId'] ];
            $model->exchangeArray( $stage );

            $histories[] = $model;
        }

        $result = new ResultListModel();

        $result->setContent($histories);
        $result->setTotalFound(count($histories));

        return $result;
    }

    /**
     * Ask in touch candidate by company
     *
     * @param  string $id_api_candidate [description]
     * @return array                    [description]
     */
    public function askInTouch( $id_api_candidate )
    {
        // $content = 'A contact request has been sent.';

        // return $this->sendMessage( $id_api_candidate, $content, true );
    }

    /**
     * API Public : search companies by name
     *
     * @param  [type] $query [description]
     * @return [type]        [description]
     */
    public function searchCompany( $query )
    {
        // if (empty($query)) return null;

        // try
        // {
        //     $data = $this->client->get('https://api.greenhouse.io/v1/boards/' . $query . '/embed/jobs');

        //     return $data->json();
        // }
        // catch(\Exception $e)
        // {
        //     return null;
        // }
    }

    /**
     * Get exclude functions for jobs (defined in config)
     *
     * @return array String of function ID
     */
    public function getExcludeFunctions()
    {
        return (isset($this->config['exclude_function']) ? $this->config['exclude_function'] : []);
    }

    /**
     * Check if a job can be inserted into our DB
     *
     * @param    $job class JobModel extends JobCoreModel implements AbstractJobModel
     * @return boolean      True if the job is valid
     */
    public function isJobValid( $job )
    {
        $is_valid       = true;
        $text           = $job->getDescription();
        // $tag_place      = $this->sm->get('PlaceTable')->getPlaceFromShortCountryName($job->location['country']);
        // $languageCode   = $this->sm->get('DetectLanguage')->simpleDetect($text);

        // if (true === in_array($job->function['id'], $this->getExcludeFunctions()))
        // {
        //     $this->sm->get('Log')->warn('Exclude ' . $job->getName() . ' with function ' . $job->function['label']);
        //     $is_valid = false;
        // }

        // if ('none' === $text || empty($text))
        // {
        //     $this->sm->get('Log')->warn('Qualification empty');
        //     $is_valid = false;
        // }

        // if ($job->language['code'] !== 'en' || $languageCode !== 'en')
        // {
        //     $this->sm->get('Log')->warn('Exclude language is : ' . $job->language['code'] . ' ' . $languageCode);
        //     $is_valid = false;
        // }

        return $is_valid;
    }

    /**
     * Get job details by ID
     *
     * @param  string $id ID of the job
     * @return AtsJobModel     Model of the job
     */
    public function getJob( $id )
    {
        return $this->get('postings/' . $id);
    }

    /**
     * Get jobs
     *
     * @param  integer $offset Offset
     * @param  integer $limit  Limit
     * @return array           Array[totalFound, content[JobModels...]]
     */
    public function getJobs( $offset, $limit, $result_list = null )
    {
        $params = ['limit' => (int) 1];//$limit];

        if (null !== $result_list)
        {
            $params['offset'] = $result_list->getOffset();
        }

        $result = new ResultListModel();
        $data   = $this->get('postings', $params);

        $result->setContent($data['data']);
        $result->setTotalFound(count($data['data']));
        if ($data['hasNext'])
            $result->setOffset($data['next']);

        return $result;
    }

    public function getJobPositions( $job )
    {
        $result = new ResultListModel();
        $data   = [];

        $model = new LeverJobPositionModel();

        $model->exchangeArray( $job->toArray() );

        $data[] = $model;

        $result->setContent($data);
        $result->setTotalFound(count($data));

        return $result;
    }

    public function isCandidateHired( $state )
    {
        // return $state === 'hired';
    }

    public function isCandidateProcessClose( $state )
    {
        // return $state === 'rejected';
    }

    public function getCandidates( $offset, $limit, $result_list = null )
    {
        $params = ['limit' => (int) $limit];

        if (null !== $result_list)
        {
            $params['offset'] = $result_list->getOffset();
        }

        $result = new ResultListModel();
        $data   = $this->get('candidates', $params);

        $result->setContent($data['data']);
        $result->setTotalFound(count($data['data']));
        if ($data['hasNext'])
            $result->setOffset($data['next']);

        return $result;
    }

    /**
     * Get information of the company (of the current user)
     *
     * @return array Data of the company
     */
    public function getCompanyInformation()
    {
        // try
        // {
        //     return $this->get('configuration/company', []);
        // }
        // catch (GreenHouseException $e)
        // {
            // return null;
        // }
    }

    /**
     * Get the URL of the candidate (in the ATS interface)
     *
     * @param  string $id ID of the candidate
     * @return string     URL
     */
    public function getUrlCandidate( $id )
    {
        return 'https://hire.sandbox.lever.co/candidates/' . $id;
    }

    /**
     * Update the state of a candidate
     *
     * @param  string $id_api ID of the candidate
     * @param  string $state  State
     * @return Array          API result
     */
    public function updateCandidateState($id_api, $state)
    {
        // $action         = 'advance';
        // $params         = ['headers' => ['On-Behalf-Of' => $this->user->id]]; // @todo over with $this->user->id

        // $candidate      = $this->get('candidates/' . $id_api, true);
        // $id_application = current($candidate->application_ids);

        // if ($state === 'REJECTED')
        //     $action = 'reject';
        // else
        // {
        //     $application    = $this->get('applications/' . $id_application, true);

        //     if (null !== $application->current_stage)
        //     {
        //         $from_stage_id  = $application->current_stage['id'];

        //         if ($application->getState() !== 'active')
        //             return null;

        //         $params        += ['from_stage_id' => $from_stage_id];
        //     }
        // }

        // return $this->json('applications/' . $id_application . '/' . $action, true, $params);
    }

    public function uploadCandidatePicture( $id_api, $picture )
    {
        // if (true === file_exists(ROOT_PATH . '/public/' . $picture))
        //     $filepath_content   = ROOT_PATH . '/public/' . $picture;
        // else
        //     $filepath_url       = 'https://app.yborder.com' . $picture;

        // if (php_sapi_name() === 'cli')
        //     echo 'filepath : ' . (isset($filepath_content) ? $filepath_content : $filepath_url) . PHP_EOL;

        // $params = [
        //     'type'      => 'photo_url',
        //     'headers'   => ['On-Behalf-Of' => $this->user->id],
        //     'filename'  => $id_api . '_picture_yborder.jpg'
        // ];

        // if (isset($filepath_content))
        // {
        //     $params['content'] = new PostFile('file', file_get_contents($filepath_content));
        // }
        // else
        // {
        //     $params['url'] = $filepath_url;
        // }

        // // $params = ['photo_url' => $filepath_url];

        // try
        // {
        //     $this->json('candidates/' . $id_api . '/attachments', true, $params);
        // }
        // catch (\Exception $e)
        // {
        //     // dd($e->getMessage());
        //     // if error : do nothing. Reason : Same image so do not need to update.
        //     return false;
        // }

        // return true;
    }

    public function uploadCandidateResume( $id_api, $pdf_link )
    {
        // // upload the RESUME
        // if (true === file_exists(ROOT_PATH . $pdf_link))
        //     $filepath_content = ROOT_PATH . $pdf_link;
        // else
        // {
        //     $pdf_link   = str_replace('public/', '', $pdf_link);
        //     $filepath_url = 'https://app.yborder.com/' . $pdf_link;
        // }

        // if (php_sapi_name() === 'cli')
        //     echo 'filepath : ' . (isset($filepath_content) ? $filepath_content : $filepath_url) . PHP_EOL;

        // $params = [
        //     'type'      => 'resume',
        //     'headers'   => ['On-Behalf-Of' => $this->user->id],
        //     'filename'  => $id_api . '_resume_yborder.pdf'
        // ];

        // if (isset($filepath_content))
        // {
        //     $params['content'] = new PostFile('file', file_get_contents($filepath_content));
        // }
        // else
        // {
        //     $params['url'] = $filepath_url;
        // }

        // try
        // {
        //     $this->json('candidates/' . $id_api . '/attachments', true, $params);
        // }
        // catch (\Exception $e)
        // {
        //     // if error : do nothing. Reason : Same image so do not need to update.
        //     return false;
        // }

        // return true;
    }

    public function createCandidate( $model )
    {
        $params          = $model->toAPI();

        // $params['perform_as'] = $this->user->id_lever;
        // $params['query'] = [
        //     'perform_as'    => $this->user->id_lever
        // ];
        return $this->json('candidates?perform_as=' . $this->user->id_lever, $params);
    }

    public function updateCandidate( $model )
    {
        return null;
        return $this->createCandidate($model);
        $data = $model->toAPI();

        $params = [
            'headers'   => ['On-Behalf-Of' => $this->user->id],
        ] + $data;

        return $this->patch('candidates/' . $model->id, $params);
    }

    public function addCandidateQualification( $model )
    {
        // $data = $model->getQualification();

        // return $this->sendMessage($model->id, $data, true);
    }

    public function getJobId( $id_ats_candidate )
    {
        // $state  = null;
        // $job_id = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'application_ids_0');

        // if (null !== $job_id)
        // {
        //     $application    = $this->get('applications/' . $job_id, true);

        //     if (null !== $application->current_stage)
        //     {
        //         $state = $application->current_stage['name'];
        //         var_dump($application->current_stage);
        //         // $state = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'secondaryAssignments_status');
        //     }
        // }

        // return [$job_id, $state];
    }
}



class LeverException extends Exception
{

}
