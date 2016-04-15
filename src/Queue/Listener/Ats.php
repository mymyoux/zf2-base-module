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
        // text search for jobs
        // $text = '<p><b>What you will need to be successful:</b></p><ul><li>Exceptional proficiency in iOS mobile development (Objective-C and/or Swift) or outstanding ability to learn;</li><li>Believe in the power of technology as a global game changer;</li><li>Out of the box thinking and mindset of constant knowledge sharing;</li><li>Implacable willingness to build massive, solid and reliable applications that scale; </li><li>Assertive contribution to the decision making process and team roadmap;</li><li>True team spirit that embraces the power that agility assigns to developers;</li><li>Firm belief that “Done is better than perfect” and that “The member is the boss” are compelling values;</li><li>Be humble, structured, organized, motivated by innovation and a relentless doer; </li><li>Enjoy working as a team-player and learning from others;</li><li>Having some cool projects on AppStore is a plus. </li><li>A fun attitude.</li></ul>';

        // $text       = strip_tags($text);
        // $searches   = $this->sm->get('MarketplaceSearchTable')->searchToTags($text);
        // // remove <= 2 words length
        // $searches   = array_filter($searches, function($item){
        //     return mb_strlen($item) > 2;
        // });

        // $searches   = array_map(function($item){
        //     return preg_replace('/[^A-Za-z0-9 ]+/', '', $item);
        // }, $searches);

        // $tags = $this->sm->get('TagTable')->getTagByNamesForClean($searches, true);

        // dd($tags);


        $YBAPI      = $this->sm->get("API");
        $api_name   = $data['ats'];
        $ats        = $this->sm->get('AtsTable')->getAts( $api_name );
        $this->api  = $this->sm->get('ApiManager')->get( $api_name );

        $user = $this->sm->get('UserTable')->getNetworkByUser( $api_name, $data['id_user'] );

        if (null === $user)
            return;

        $this->api->setAccessToken( $user['access_token'], $user['refresh_token'] );

        // get the real user
        $user = $this->sm->get('UserTable')->getUser( $data['id_user'] );

        switch ($data['ressource'])
        {
            case 'jobs':
                $this->getJobs($user, $ats);
            break;
            case 'candidates' :
                $this->getCandidates($user, $ats);
            break;
            case 'create_candidate_anonyme' :
                $id_candidate = (int) $data['id_candidate'];

                $this->upsertCandidate($id_candidate, $user, $ats, true);
            break;
            case 'create_candidate_full' :
                $id_candidate = (int) $data['id_candidate'];

                $this->upsertCandidate($id_candidate, $user, $ats, false);
            break;
            case 'close_by_candidate':
                // update status to rejected
                $id_candidate   = (int) $data['id_candidate'];
                $candidate      = $this->sm->get('UserTable')->getUser($id_candidate);
                if(!isset($candidate))
                {
                    dd('error candidate not exist');
                }

                $exist = $this->sm->get('AtsCandidateTable')->getByCandidateID( $id_candidate, $ats['id_ats'] );
                $id_api = $exist['id_api'];

                $this->api->put('candidates/' . $id_api . '/status', ['status' => 'REJECTED']);

                // SEND A MESSAGE ?!
                // /!\
                $content    = 'Candidate close the process.';
                // send message to company
                $this->sendMessage($content, $id_candidate, $user, $ats);
            break;
            case 'close_by_company':
                // update status to rejected
                $id_candidate   = (int) $data['id_candidate'];
                $candidate      = $this->sm->get('UserTable')->getUser($id_candidate);
                if(!isset($candidate))
                {
                    dd('error candidate not exist');
                }

                $exist = $this->sm->get('AtsCandidateTable')->getByCandidateID( $id_candidate, $ats['id_ats'] );
                $id_api = $exist['id_api'];

                $this->api->put('candidates/' . $id_api . '/status', ['status' => 'REJECTED']);
            break;
        }
    }

    private function getJobs($user, $ats)
    {
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
    }

    private function getCandidates($user, $ats)
    {
        $params = ['limit' => 100, 'offset' => 0];

        $candidates = $this->api->get('candidates', $params);

        while ($params['offset'] < $candidates['totalFound'])
        {
            foreach ($candidates['content'] as $details)
            {
                $exist      = $this->sm->get('AtsCandidateTable')->getByAPIID( $details->id, $ats['id_ats'] );
                $histories  = $this->api->get('candidates/' . $details->id . '/status/history', ['limit' => 100]);

                $candidate  = $this->sm->get('UserTable')->getUserByEmail( $details->email );

                if (null === $exist)
                {
                    // find candidate by email
                    $id_ats_candidate = $this->sm->get('AtsCandidateTable')->saveCandidate($details->id, $ats['id_ats'], (null === $candidate ? null : $candidate->id));
                }
                else
                {
                    $id_ats_candidate   = $exist['id_ats_candidate'];

                    if ($candidate !== null && $exist['id_candidate'] === null)
                    {
                        // update candidate

                        $this->sm->get('AtsCandidateTable')->updateCandidate( $details->id, $ats['id_ats'], $candidate->id );
                    }
                }

                $history    = array_pop($histories['content']);
                $state      = $history['status'];

                $details->setAtsCandidateId( $id_ats_candidate );
                $details->saveValues();

                // check if add job id
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

                if ($job_id !== null)
                {
                    $job    = $this->sm->get('AtsJobTable')->getByAPIID( $job_id, $ats['id_ats'] );
                    $job_id = $job['id_ats_job'];
                }

                if (null === $exist)
                {
                    foreach ($histories['content'] as $history)
                        $this->sm->get('AtsCompanyCandidateTable')->insertHistory($user->getCompany()->id_company, $user->id, $id_ats_candidate, $history['status'], $job_id, date('Y-m-d H:i:s', strtotime($history['changedOn'])));
                }

                // insert OR update candidate company relation
                if (null === $this->sm->get('AtsCompanyCandidateTable')->getBy($user->getCompany()->id_company, $user->id, $id_ats_candidate))
                {
                    $this->sm->get('AtsCompanyCandidateTable')->save($user->getCompany()->id_company, $user->id, $id_ats_candidate, $state, generate_token(30), $job_id);
                }
                else
                {
                    $this->sm->get('AtsCompanyCandidateTable')->updateCandidate($user->getCompany()->id_company, $user->id, $id_ats_candidate, $state, $job_id);
                }
            }

            $params['offset'] += $params['limit'];

            $candidates = $this->api->get('candidates', $params);
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

        $place = $this->sm->get('UserTable')->getPlaceUser($candidate['id_user']);

        $model = new \Application\Model\Ats\Smartrecruiters\CandidateModel();

        // save candidate
        if (null === $exist)
        {
            // token of the smartrecruiters conversation's
            $token = generate_token(30);
            $model->importFromCV( $candidate['cv'], $token, $place, $anonymize );

            $modelCandidate = $this->api->json('candidates', $model->toAPI());

            $id_ats_candidate = $this->sm->get('AtsCandidateTable')->saveCandidate( $modelCandidate->id, $ats['id_ats'], $id_candidate );
            $modelCandidate->setAtsCandidateId( $id_ats_candidate );
            $modelCandidate->saveValues();

            $id_api = $modelCandidate->id;

            $this->sm->get('AtsCompanyCandidateTable')->save($user->getCompany()->id_company, $user->id, $id_ats_candidate, 'LEAD', $token);
        }
        else
        {
            $relation_exist = $this->sm->get('AtsCompanyCandidateTable')->getBy($user->getCompany()->id_company, $user->id, $exist['id_ats_candidate']);
            // token of the smartrecruiters conversation's
            $token          = $relation_exist['token'];
            // update the candidate
            $id_api         = $exist['id_api'];

            $model->importFromCV( $candidate['cv'], $token, $place, $anonymize );
            $api_data = $model->toAPI();

            // always set the name the user has in SM platform
            // because the employee can edit this (and not us :/)
            $api_data['firstName'] = $this->sm->get('AtsCandidateTable')->getValue( $exist['id_ats_candidate'], 'firstName' );
            $api_data['lastName'] = $this->sm->get('AtsCandidateTable')->getValue( $exist['id_ats_candidate'], 'lastName' );

            // update values & DB association
            $modelCandidate = $this->api->json('candidates', $api_data);
            $modelCandidate->setAtsCandidateId( $exist['id_ats_candidate'] );
            $modelCandidate->saveValues();

            $this->sm->get('AtsCandidateTable')->updateCandidate( $modelCandidate->id, $ats['id_ats'], $id_candidate );
        }
        // upload candidate AVATAR
        if (null !== $candidate['cv']['picture'])
        {
            $params = [
                'attachmentType'    => 'AVATAR',
                'file'              => new PostFile('file', file_get_contents('https://app.yborder.com/' . $candidate['cv']['picture']))
            ];

            try
            {
                $this->api->post('candidates/' . $id_api . '/attachments', $params);
            }
            catch (\Exception $e)
            {
                // if error : do nothing. Reason : Same image so do not need to update.
            }
        }

        if (false === $anonymize && true === $candidate['cv']['has_pdf'])
        {
            // upload the CV
            $pdf_link   = $this->sm->get('CVTable')->getCVPDF($candidate['id_user']);

            if (null !== $pdf_link)
            {
                $pdf_link   = str_replace('public/', '', $pdf_link);

                $params     = [
                    'attachmentType'    => 'RESUME',
                    'file'              => new PostFile('file', file_get_contents('https://app.yborder.com/' . $pdf_link), generate_token(30) . '.pdf')
                ];

                $this->api->post('candidates/' . $id_api . '/attachments', $params);
            }
        }

        if (true === $anonymize)
        {
            $status = 'NEW';
        }
        else
        {
            $status     = 'IN_REVIEW';
            $content    = 'Candidate ' . $candidate['cv']['firstname'] . ' ' . $candidate['cv']['lastname'] . ' accept your intouch request.' . PHP_EOL . 'Email : ' . $candidate['cv']['email'];
            // send message to company
            $this->sendMessage($content, $id_candidate, $user, $ats);
        }

        // update status
        $this->api->put('candidates/' . $id_api . '/status', ['status' => $status]);
    }

    private function sendMessage( $content, $id_candidate, $user, $ats )
    {
        $reply_to = $this->sm->get('AtsMessageTable')->getReplyTo( $id_candidate, $user->id );

        if (null !== $reply_to)
        {
            $this->sm->get('Email')->setDebug(false);
            $this->sm->get("Email")->sendRaw(['inbox', 'message', 'new'], $content, $reply_to);
            $this->sm->get('Email')->setDebug(true);
        }
    }

}
