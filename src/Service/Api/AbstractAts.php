<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 06/10/2014
 * Time: 23:11
 */

namespace Core\Service\Api;


abstract class AbstractAts extends AbstractAPI
{
	public function typeAuthorize()
	{
		return ['company'];
	}
	public function canLogin()
	{
		return true;
	}

	/**
     * Get exclude functions for jobs (defined in config)
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
     * Get jobs
     *
     * @param  integer $offset Offset
     * @param  integer $limit  Limit
     * @return array           Array[totalFound, content[JobModels...]]
     */
    abstract public function getJobs( $offset, $limit );

	/**
     * Check if a job can be inserted into our DB
     *
     * @param    $job class JobModel extends JobCoreModel implements AbstractJobModel
     * @return boolean      True if the job is valid
     */
	abstract public function isJobValid( $job );

    /**
     * Get candidates
     *
     * @param  integer $offset Offset
     * @param  integer $limit  Limit
     * @return array           Array[totalFound, content[CandidateModels...]]
     */
    abstract public function getCandidates( $offset, $limit );

    /**
     * Get candidate state history
     *
     * @param  string $id ID of the candidate
     * @return ResultListeModel     All history state
     */
    abstract public function getCandidateHistory( $id );

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
     * Get information of the company (of the current user)
     *
     * @return array Data of the company
     */
    abstract public function getCompanyInformation();

}
