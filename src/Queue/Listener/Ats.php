<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use GuzzleHttp\Post\PostFile;

class Ats extends ListenerAbstract implements ListenerInterface
{

    protected $queueName;
    private $tries;
    private $queue;

    /**
     * @param int $tries
     */
    public function __construct($tries = 3)
    {
    }

    public function checkJob( $data )
    {
        return true;
    }

    public function executeJob( $data )
    {
        $YBAPI      = $this->sm->get("API");
        $api_name   = $data['api'];
        $ats        = $this->sm->get('AtsTable')->getAts( $api_name );
        $this->api  = $this->sm->get('ApiManager')->get( $api_name );

        $user = $this->sm->get('UserTable')->getNetworkByUser( $api_name, $data['id_user'] );

        if (null === $user)
            return;

        $this->api->setAccessToken( $user['access_token'], $user['refresh_token'] );

        // get the real user
        $user = $this->sm->get('UserTable')->getUser( $data['id_user'] );

        switch ($data['data'])
        {
            case 'jobs':
                $params_jobs = ['limit' => 100, 'offset' => 0];

                $jobs = $this->api->get('jobs', $params_jobs);

                while ($params_jobs['offset'] < $jobs['totalFound'])
                {
                    foreach ($jobs['content'] as $job)
                    {
                        $details = $this->api->get('jobs/' . $job->id);

                        $params = [
                            'name'          => $details->getName(),
                            'description'   => $details->getDescription(),
                            'has_alert'     => $details->hasAlert(),
                            'is_public'     => $details->isPublic(),
                            'token'         => $details->getToken()
                        ];

                        // @HOW TO GET TOKEN ?!
                        // @HOW TO GET TAGS  ?!

                        // HERE save YBorder job
                        // $YBorder->jobs->module('company')->user($user)->saveJob(null, 'POST', $params);

                        $exist = $this->sm->get('AtsJobTable')->getByAPIID( $details->id, $ats['id_ats'] );

                        if (null === $exist)
                        {
                            $id_ats_job = $this->sm->get('AtsJobTable')->saveJob($details->id, $ats['id_ats'], null);
                        }
                        else
                        {
                            $id_ats_job = $exist['id_ats_job'];
                        }

                        $details->setAtsJobId( $id_ats_job );
                        $details->saveValues();
                    }

                    $params_jobs['offset'] += $params_jobs['limit'];

                    $jobs = $this->api->get('jobs', $params_jobs);
                }
            break;
            case 'candidate' :

                $params = ['limit' => 100, 'offset' => 0];

                $candidates = $this->api->get('candidates', $params);

                while ($params['offset'] < $candidates['totalFound'])
                {
                    foreach ($candidates['content'] as $details)
                    {
                        $exist = $this->sm->get('AtsCandidateTable')->getByAPIID( $details->id, $ats['id_ats'] );

                        if (null === $exist)
                        {
                            $id_ats_candidate = $this->sm->get('AtsCandidateTable')->saveCandidate($details->id, $ats['id_ats'], null);
                        }
                        else
                        {
                            $id_ats_candidate = $exist['id_ats_candidate'];
                        }

                        $details->setAtsCandidateId( $id_ats_candidate );
                        $details->saveValues();

                        // check if add job id
                        $job_id = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'primaryAssignment_job_id');
                        $state  = 'YB_NO_ACTION';

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

                        if ($job_id !== null)
                        {
                            $job    = $this->sm->get('AtsJobTable')->getByAPIID( $job_id, $ats['id_ats'] );
                            $job_id = $job['id_ats_job'];
                        }

                        // insert OR update candidate company relation
                        if (null === $this->sm->get('AtsCompanyCandidateTable')->getBy($user->getCompany()->id_company, $user->id, $id_ats_candidate))
                        {
                            $this->sm->get('AtsCompanyCandidateTable')->save($user->getCompany()->id_company, $user->id, $id_ats_candidate, $state, $job_id);
                        }
                        else
                        {
                            $this->sm->get('AtsCompanyCandidateTable')->updateCandidate($user->getCompany()->id_company, $user->id, $id_ats_candidate, $state, $job_id);
                        }
                    }

                    $params['offset'] += $params['limit'];

                    $candidates = $this->api->get('candidates', $params);
                }
            break;
            case 'create_candidate_anonyme' :

                $id_candidate = (int) $data['id_candidate'];

                $this->upsertCandidate($id_candidate, $user, $ats, true);
            break;
            case 'create_candidate_full' :

                $id_candidate = (int) $data['id_candidate'];

                $this->upsertCandidate($id_candidate, $user, $ats, false);
            break;
        }
    }

    private function upsertCandidate( $id_candidate, $user, $ats, $anonymize )
    {
        $candidate = $this->sm->get('UserTable')->getUser($id_candidate);
        if(!isset($candidate))
        {
            dd('error candidate not exist');
        }
        //create or retrieve cv
        $cv = $this->sm->get('CVTable')->createForm($candidate);

        // check if the current user can see the profile
        // ie : the candidate hasn't working in the same company
        if (count($this->sm->get('MarketplaceSearchCandidateTable')->getExcludesIDUsers($user, [$id_candidate])) > 0)
        {
            dd('error candidate not exist');
        }

        if (isset($cv))
        {
            $cv->retrieveAllForCompany();
            $cv->retrieveQualificationForCompany();
            $candidate->cv = $cv;
        }

        $exist = $this->sm->get('AtsCandidateTable')->getByCandidateID( $id_candidate, $ats['id_ats'] );

        // $employees  = $this->getCompanyTable()->getGroupEmployeesIdByEmployee( $user );

        // $state = $this->getCompanyTable()->getCandidateState($this->identity->user->getCompany(), $user, $employees);
        $cv->retrieveReferences();

        if (true === $anonymize)
            $cv = $this->sm->get('CandidateService')->anonymize( $cv );

        $candidate->cv = $cv;
        $candidate = $candidate->toCompanyArray();

        if (true === $anonymize)
            $candidate = $this->sm->get('CandidateService')->anonymize( $candidate );

        // if (!(in_array($state, \Application\Table\CompanyTable::STATES_HIRING_PROCESS) || $state == CompanyTable::STATE_IN_TOUCH_ACCEPTED))
        // {
        //     if(isset($user["cv"]["references"]))
        //     {
        //         $length = sizeof( $user["cv"]["references"]);

        //         $user["cv"]["references"] = array_fill(0,$length, array());

        //     }

        //     // if candidate is hired => error
        //     if ($user['cabinet_state'] == \Application\Table\CabinetTable::STATE_HIRED)
        //     {
        //         $view->setVariable("error", "user hired");
        //         return $view;
        //     }
        // }



        $model = new \Application\Model\Ats\Smartrecruiters\CandidateModel();

        $model->importFromCV( $candidate['cv'], $anonymize );

        // dd($candidate);
        // save candidate

        if (null === $exist)
        {
            $modelCandidate = $this->api->json('candidates', $model->toAPI());

            $id_ats_candidate = $this->sm->get('AtsCandidateTable')->saveCandidate( $modelCandidate->id, $ats['id_ats'], $id_candidate );
            $modelCandidate->setAtsCandidateId( $id_ats_candidate );
            $modelCandidate->saveValues();

            $id_api = $modelCandidate->id;
        }
        else
        {
            $id_api = $exist['id_api'];

            // $modelCandidate = $this->api->put('candidates/' . $id_api . '/properties/firstName', json_encode('coucou'));
            $dd = $this->api->get('candidates/81039b47-ff2c-4814-bbfa-7b20f8bdf1a6/properties');//, ['query' => ['content' => 'PROFILE']]);

            dd($dd);

            $modelCandidate = $this->api->put('candidates', $model->toAPI());

            // $modelCandidate->setAtsCandidateId( $exist['id_ats_candidate'] );
            // $modelCandidate->saveValues();

            // $this->sm->get('AtsCandidateTable')->updateCandidate( $modelCandidate->id, $ats['id_ats'], $id_candidate );
        }

        // upload image
        if (null !== $candidate['picture'])
        {
            $params = [
                'attachmentType'    => 'AVATAR',
                'file'              => new PostFile('file', file_get_contents('https://app.yborder.com/' . $candidate['picture']))
            ];
            var_dump('https://app.yborder.com/' . $candidate['picture']);
            $this->api->post('candidates/' . $id_api . '/attachments', $params);
        }


        dd($modelCandidate->toArray());
    }

}
