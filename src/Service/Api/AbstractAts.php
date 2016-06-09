<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 06/10/2014
 * Time: 23:11
 */

namespace Core\Service\Api;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractAts extends AbstractAPI
{
    protected $user = null;

	public function typeAuthorize()
	{
		return [];
	}

    /**
     * Set the ats user from table (user_network_ATSNAME)
     * @param array $ats_user user
     */
    public function setUser( $ats_user )
    {
        if (!$this->user)
        {
            $this->user         = new \stdClass();
            $this->user->id     = null;
        }
        $this->user = (object) $ats_user;
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
        $class          = get_class($this);
        $name           = strtolower(substr($class, strrpos($class, '\\') + 1));
        $apis           = $this->sm->get('AppConfig')->get('apis');
        $this->config   = $apis[ $name ];
        $this->ats_name = $name;
        $this->ats      = $this->sm->get('AtsTable')->getAts( $name );
    }

    /**
     * Log ressource usage (ie: /candidates, /jobs ...)
     *
     * @param  string $method    HTTP method (ie: POST, GET, PUT ...)
     * @param  string $ressource Ressource name (ie: /candidates, /jobs ...)
     * @return void
     */
    protected function logRessource($method, $ressource)
    {
        $this->sm->get('AtsApiRessourceTable')->upsertRessource( $this->user->id_user, $this->ats['id_ats'], $method, $ressource, date('Y-m-d H:i:s'));
    }

    /**
     * Log API call for ATS
     *
     * @param  string   $method       HTTP method (ie: POST, GET, PUT ...)
     * @param  string   $ressource    Ressource name (ie: /candidates, /jobs ...)
     * @param  array    $params       Params used for the call
     * @param  boolean  $success      True if the call succeded
     * @param  array    $result       Raw result
     * @param  integer  $id_error     ID of the error
     * @return void
     */
    protected function logApiCall($method, $ressource, $params, $success = false, $result = null, $id_error = null)
    {
        $this->sm->get('AtsApiCallTable')->insertCall([
            'id_user'   => (int) $this->user->id_user,
            'id_ats'    => (int) $this->ats['id_ats'],
            'ressource' => (string) $ressource,
            'method'    => (string) $method,
            'params'    => json_encode($params),
            'success'   => (int) $success,
            'id_error'  => $id_error,
            'value'     => json_encode($result)
        ]);
    }

    /**
     * Get ressource data
     *
     * @param  string $method    HTTP method (ie: POST, GET, PUT ...)
     * @param  string $ressource Ressource name (ie: /candidates, /jobs ...)
     * @return array  Ressource data
     */
    public function getRessource($method, $ressource)
    {
        return $this->sm->get('AtsApiRessourceTable')->get( $this->user->id_user, $this->ats['id_ats'], $method, $ressource );
    }

    /**
     * Log message send through the ATS API by YBorder
     *
     * @param  integer $id_api_candidate ATS ID of the candidate
     * @param  string $content           Text
     * @return integer                   Return id of the message
     */
    protected function logSendMessage( $id_api_candidate, $content )
    {
        $candidate = $this->getCandidateAtsByAPIID( $id_api_candidate );

        if (null === $candidate) return null;

        return $this->sm->get('AtsMessageSendTable')->insertMessage( $candidate['id_ats_candidate'], $content );
    }

    /**
     * Get the last message send to a candidate (through the ATS)
     *
     * @param  integer $id_api_candidate ATS ID of the candidate
     * @return string                    Return history formatted for ATS
     */
    protected function getLogMessageHistory( $id_api_candidate )
    {
        $candidate = $this->getCandidateAtsByAPIID( $id_api_candidate );

        return $this->sm->get('AtsMessageSendTable')->getHistoryContent( $candidate['id_ats_candidate'] );
    }

    /**
     * Get candidate information
     *
     * @param  integer $id_api_candidate    ATS ID of the candidate
     * @return array                        Data of the candidate
     */
    protected function getCandidateAtsByAPIID( $id_api_candidate )
    {
        return $this->sm->get('AtsCandidateTable')->getByAPIID( $id_api_candidate, $this->ats['id_ats'] );
    }

    /**
     * Get the reply-to header from an ATS email
     *
     * @return [type] [description]
     */
    abstract public function getEmailFieldReplyTo();

    /**
     * Tag a candidate into the platform
     *
     * @param  [type] $id_api [description]
     * @return [type]         [description]
     */
    abstract public function tagCandidate( $id_api );

	/**
     * Get exclude functions ID for jobs (defined in config)
     *
     * @return array String of function ID
     */
	abstract public function getExcludeFunctions();

	/**
     * Get job details by ID
     *
     * @param  string $id ID of the job
     * @return AtsJobModel     Model of the job
     */
    abstract public function getJob( $id );

    /**
     * Get job positions by job model
     *
     * @param  string $job Model of the job
     * @return array           Array[totalFound, content[JobPositionModels...]]
     */
    abstract public function getJobPositions( $job );

    /**
     * Get jobs
     *
     * @param  integer $offset Offset
     * @param  integer $limit  Limit
     * @return array           Array[totalFound, content[JobModels...]]
     */
    abstract public function getJobs( $offset, $limit, $result_list = null );

	/**
     * Check if a job can be inserted into our DB
     *
     * @param    $job class JobModel extends JobCoreModel implements AbstractJobModel
     * @return boolean      True if the job is valid
     */
	abstract public function isJobValid( $job );

    /**
     * Get the Job ID of a candidate (if he has one) from the DB.
     *
     * @param  string $id_api_candidate DB ID of the ATS candidate
     * @return string                   ID of the job or NULL
     */
    abstract public function getJobId( $id_api_candidate );

    /**
     * Get candidates
     *
     * @param  integer $offset Offset
     * @param  integer $limit  Limit
     * @return array           Array[totalFound, content[CandidateModels...]]
     */
    abstract public function getCandidates( $offset, $limit, $result = null );

    /**
     * Return true if the candidate has been hired
     *
     * @param  string  $state state of the candidate
     * @return boolean        True if hired
     */
    abstract public function isCandidateHired( $state );

    /**
     * Return true if the candidate has been rejected or closed
     *
     * @param  string  $state state of the candidate
     * @return boolean        True if close
     */
    abstract public function isCandidateProcessClose( $state );

    /**
     * Create a new candidate
     *
     * @param  AtsCoreModel $model Candidate model
     * @return AtsCoreModel        Candidate model
     */
    abstract public function createCandidate( $model );

    /**
     * Update new candidate
     *
     * @param  AtsCoreModel $model Candidate model
     * @return AtsCoreModel        Candidate model
     */
    abstract public function updateCandidate( $model );

    /**
     * Add qualification
     *
     * @param  AtsCoreModel $model Candidate model
     * @return void
     */
    abstract public function addCandidateQualification( $model );

    /**
     * Get candidate state history
     *
     * @param  AtsCandidateModel $candidate ID of the job
     * @return ResultListModel   History list
     */
    abstract public function getCandidateHistory( $candidate );

    /**
     * Get the URL of the candidate (in the ATS interface)
     *
     * @param  string $id ID of the candidate
     * @return string     URL
     */
    abstract public function getUrlCandidate( $id );

    /**
     * Update the state of a candidate
     *
     * @param  string $id_api ID of the candidate
     * @param  string $state  State
     * @return Array          API result
     */
    abstract public function updateCandidateState( $id_api, $state );

    /**
     * Ask in touch candidate by company
     *
     * @param  string $id_api_candidate [description]
     * @return array                    [description]
     */
	abstract public function askInTouch( $id_api_candidate );

	/**
     * Get candidate current state
     *
     * @param  string $id_api_candidate [description]
     * @return array                    [description]
     */
	abstract public function getCandidateState( $id_api_candidate );

    /**
     * Upload the candidate profile picture
     *
     * @param  string $id_api   ID of the candidate
     * @param  string $picture  URL of the image
     * @return boolean          True if upload is a success
     */
    abstract public function uploadCandidatePicture( $id_api, $picture );

    /**
     * Upload the candidate resume
     *
     * @param  string $id_api   ID of the candidate
     * @param  string $pdf_link PDF link of the resume
     * @return boolean          True if upload is a success
     */
    abstract public function uploadCandidateResume( $id_api, $pdf_link );

    /**
     * Get information of the company (of the current user)
     *
     * @return array Data of the company
     */
    abstract public function getCompanyInformation();

    /**
     * Send a message to the ATS platform (use a LOG from YBorder action)
     *
     * @param  string  $id_api              ID of the candidate
     * @param  string  $content             Message content text
     * @param  boolean $share_with_everyone If you want to share this message with everyone in the ATS
     * @return MessageModel                 Message model
     */
    abstract public function sendMessage($id_api, $content, $share_with_everyone = false);
}

