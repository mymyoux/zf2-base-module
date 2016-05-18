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
            '\/history$'                    => '\Application\Model\Ats\Smartrecruiters\HistoryModel',
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

            $this->sm->get('Log')->normal('[' . $method . '] ' . $path . $ressource . ' ' . json_encode($params));

            $data = $this->client->{ strtolower($method) }($path . $ressource, $params);
        }
        catch (\Exception $e)
        {
            // Client error response [url] https://api.smartrecruiters.com/configuration/company [status code] 401 [reason phrase] Unauthorized
            preg_match('/Client error response \[url\] (.+) \[status code\] (\d+) \[reason phrase\] (.+)/', $e->getMessage(), $matches);
            if (count($matches) > 0)
            {
                list($original_message, $e_url, $e_code, $e_message) = $matches;
                $e = new SmartrecruitersException($e_message, $e_code);
            }
            else
            {
                // not an API Exception
                throw $e;
            }

            // dd($e->getMessage());
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

    /**
     * Send message into SM Home
     *
     * @param  string  $content             content of the message.
     * @param  boolean $share_with_everyone true if everyone can see the message.
     * @return array
     */
    public function sendMessage( $id_api_candidate, $content, $share_with_everyone = false)
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
     * Get candidate state history
     *
     * @param  AtsCandidateModel $candidate ID of the job
     * @return ResultListModel   History list
     */
    public function getCandidateHistory( $candidate )
    {
        $histories  = $this->get('candidates/' . $candidate->id . '/status/history', ['limit' => 100]);

        $result = new ResultListModel();

        $result->setContent($histories['content']);
        $result->setTotalFound($histories['totalFound']);

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
        $content = 'Intouch request sent to #[CANDIDATE:' . $id_api_candidate  . ']';

        return $this->sendMessage( $id_api_candidate, $content, true );
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
        $tag_place      = $this->sm->get('PlaceTable')->getPlaceFromShortCountryName($job->location['country']);
        $languageCode   = $this->sm->get('DetectLanguage')->simpleDetect($text);

        if (true === in_array($job->function['id'], $this->getExcludeFunctions()))
        {
            $this->sm->get('Log')->warn('Exclude ' . $job->getName() . ' with function ' . $job->function['label']);
            $is_valid = false;
        }

        if ('none' === $text || empty($text))
        {
            $this->sm->get('Log')->warn('Qualification empty');
            $is_valid = false;
        }

        if ($job->language['code'] !== 'en' || $languageCode !== 'en')
        {
            $this->sm->get('Log')->warn('Exclude language is : ' . $job->language['code'] . ' ' . $languageCode);
            $is_valid = false;
        }

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
        return $this->get('jobs/' . $id);
    }

    /**
     * Get jobs
     *
     * @param  integer $offset Offset
     * @param  integer $limit  Limit
     * @return array           Array[totalFound, content[JobModels...]]
     */
    public function getJobs( $offset, $limit )
    {
        $params = [
            'offset'    => (int) $offset,
            'limit'     => (int) $limit
        ];

        $result = new ResultListModel();
        $data   = $this->get('jobs', $params);

        $result->setContent($data['content']);
        $result->setTotalFound($data['totalFound']);

        return $result;
    }

    public function createCandidate( $model )
    {
        if (null !== $model->id_job)
        {
            // insert directly to the job
            return $this->json('jobs/' . $model->id_job . '/candidates', $model->toAPI());
        }
        else
            return $this->json('candidates', $model->toAPI());
    }

    public function updateCandidate( $model )
    {
        return $this->createCandidate( $model );
    }

    public function addCandidateQualification( $model )
    {
        // do nothing because it's done in the creation
    }

    public function getCandidates( $offset, $limit )
    {
        $params = [
            'offset'    => (int) $offset,
            'limit'     => (int) $limit
        ];

        $result = new ResultListModel();
        $data   = $this->get('candidates', $params);

        $result->setContent($data['content']);
        $result->setTotalFound($data['totalFound']);

        return $result;
    }

    /**
     * Get information of the company (of the current user)
     *
     * @return array Data of the company
     */
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

    /**
     * Get the URL of the candidate (in the ATS interface)
     *
     * @param  string $id ID of the candidate
     * @return string     URL
     */
    public function getUrlCandidate( $id )
    {
        return 'https://www.smartrecruiters.com/app/people/' . $id . '/messages';
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
        return $this->put('candidates/' . $id_api . '/status', ['status' => $state]);
    }

    public function uploadCandidatePicture( $id_api, $picture )
    {
        if (true === file_exists(ROOT_PATH . $picture))
            $filepath = ROOT_PATH . '/public/' . $picture;
        else
            $filepath = 'https://app.yborder.com' . $picture;

        if (php_sapi_name() === 'cli')
            echo 'filepath : ' . $filepath . PHP_EOL;

        $params = [
            'attachmentType'    => 'AVATAR',
            'file'              => new PostFile('file', $filepath)
        ];

        try
        {
            $this->post('candidates/' . $id_api . '/attachments', $params);
        }
        catch (\Exception $e)
        {
            // if error : do nothing. Reason : Same image so do not need to update.
            return false;
        }

        return true;
    }

    public function uploadCandidateResume( $id_api, $pdf_link )
    {
        // upload the RESUME
        if (true === file_exists(ROOT_PATH . $pdf_link))
            $filepath = ROOT_PATH . $pdf_link;
        else
        {
            $pdf_link   = str_replace('public/', '', $pdf_link);
            $filepath = 'https://app.yborder.com/' . $pdf_link;
        }

        if (php_sapi_name() === 'cli')
            echo 'filepath : ' . $filepath . PHP_EOL;

        $params     = [
            'attachmentType'    => 'RESUME',
            'file'              => new PostFile('file', file_get_contents($filepath), generate_token(30) . '.pdf')
        ];

        try
        {
            $this->post('candidates/' . $id_api . '/attachments', $params);
        }
        catch (\Exception $e)
        {
            // if error : do nothing. Reason : Same image so do not need to update.
            return false;
        }

        return true;
    }

    public function getJobId( $id_ats_candidate )
    {
        $state  = null;
        $job_id = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'primaryAssignment_job_id');

        if (null === $job_id)
        {
            $job_id = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'secondaryAssignments_job_id');

            if (null !== $job_id)
            {
                $state = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'secondaryAssignments_status');
            }
        }
        else
        {
            $state = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'primaryAssignment_status');
        }

        return [$job_id, $state];
    }
}

class SmartrecruitersException extends Exception
{

}
