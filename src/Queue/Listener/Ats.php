<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

use Application\Model\Marketplace\SearchModel;

use DetectLanguage\DetectLanguage;

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
        $this->sm->get('Email')->setAsync( true );
        $this->sm->get('Email')->setMergeLanguage( 'handlebars' );

        $YBAPI      = $this->sm->get("API");
        $api_name   = $data['ats'];
        $ats        = $this->sm->get('AtsTable')->getAts( $api_name );
        $this->api  = $this->sm->get('ApiManager')->get( $api_name );

        $user = $this->sm->get('UserTable')->getNetworkByUser( $api_name, $data['id_user'] );

        if (null === $user)
        {
            $this->sm->get('Log')->error('User who did the action not exist');
            return;
        }

        $this->api->setAccessToken( $user['access_token'], $user['refresh_token'] );

        // get the real user
        $user = $this->sm->get('UserTable')->getUser( $data['id_user'] );

        // $sm_exclude = [
        //     'accounting_auditing',
        //     'administrative',
        //     'advertising',
        //     'business_development',
        //     'consulting',
        //     'customer_service',
        //     'distribution',
        //     'education',
        //     'finance',
        //     'general_business',
        //     'health_care_provider',
        //     'human_resources',
        //     'legal',
        //     'manufacturing',
        //     'marketing',
        //     'other',
        //     'production',
        //     'public_relations',
        //     'purchasing',
        //     'sales',
        //     'strategy_planning',
        //     'supply_chain',
        //     'training',
        //     'writing_editing',
        // ];

        // $sm_association = [
        //     'analyst'  => 1, // DS
        //     'research'  => 1, // DS
        //     'art_creative'  => 2, // UX/UI
        //     'design'  => 2, // DS
        //     'product_management'  => 21, // Product manager
        //     'quality_assurance'  => 11, // QA / Test
        // ];

        // // blablacar
        // // Intuit2
        // // Ubisoft2
        // $jobs       = json_decode(file_get_contents('https://api.smartrecruiters.com/v1/companies/Ubisoft2/postings'), true);
        // $success    = 0;
        // $max        = 0;
        // $YBorder    = $this->sm->get('Api');

        // foreach ($jobs['content'] as $job)
        // {
        //     $details        = json_decode(file_get_contents($job['ref']), true);
        //     $tag_place      = $this->sm->get('PlaceTable')->getPlaceFromShortCountryName($details['location']['country']);
        //     $text           = $details['jobAd']['sections']['qualifications']['text'];
        //     $languageCode   = 'en';//$this->sm->get('DetectLanguage')->simpleDetect($text);

        //     if (true === in_array($details['function']['id'], $sm_exclude))
        //     {
        //         $this->sm->get('Log')->warn('Exclude ' . $details['name'] . ' with function ' . $details['function']['label']);
        //         continue;
        //     }

        //     if ('none' === $text || empty($text))
        //     {
        //         $this->sm->get('Log')->warn('Qualification empty');
        //         continue;
        //     }

        //     if ($details['language']['code'] !== 'en' || $languageCode !== 'en')
        //     {
        //         $this->sm->get('Log')->warn('Exclude language is : ' . $details['language']['code'] . ' ' . $languageCode);
        //         continue;
        //     }

        //     // $this->sm->get('Log')->warn($details['function']['label']);
        //     $this->sm->get('Log')->info('Place ' . $tag_place['name']);

        //     // list($tags_name, $position) = $this->convertJob( $details['name'], $details['jobAd']['sections']['qualifications']['text'], $positions );
        //     list($tags_name, $position) = $this->sm->get('AtsService')->convertJob( $details['name'], $text, $sm_association, $details['function']['id'] );
        //     $success    += (null !== $position);
        //     $max        += 1;

        //     if (null !== $position && count($tags_name) > 0)
        //     {
        //         $search_tags    = [
        //             'position'  => [ $position['id_position'] ]
        //         ];

        //         if (null !== $tag_place)
        //             $search_tags['location']  = [ $tag_place['id_place'] ];

        //         $result         = $YBorder->marketplace->module('company')->user($user)->data(NULL, "GET", [
        //             'search'    => implode(' ', $tags_name),
        //             'tags'      => $search_tags
        //         ]);

        //         $data           = $result->value;

        //         var_dump(count($data));
        //         var_dump(implode(' ', $tags_name), $search_tags);
        //     }


        //     // if (null !== $position)
        //     //     break;
        //     // if (null !== $position)
        //     // // echo 'NAME: ' . $details['name'] . PHP_EOL;
        //     // echo PHP_EOL;

        // }

        // $this->sm->get('Log')->info('Success ' . $success . '/' . $max);

        // exit();
        // $this->getJobs($user, $ats);

        switch ($data['ressource'])
        {
            case 'company':
                $company = $this->api->getCompanyInformation();

                if (null === $this->sm->get('AtsCompanyTable')->getByAPIID($company['identifier'], $ats['id_ats']))
                    $this->sm->get('AtsCompanyTable')->saveCompany( $company['identifier'], $ats['id_ats'], $company['name'], $user->getCompany()->id_company );
            break;
            case 'jobs':
                $this->getJobs($user, $ats);
            break;
            case 'candidates' :
                $this->getCandidates($user, $ats);
            break;
            case 'create_candidate_anonyme' :
                $ids_candidate  = $data['ids_candidate'];
                // $template       = $data['template'];
                // $email_param    = $data['email_param'];
                $candidates     = [];

                foreach ($ids_candidate as $id_candidate)
                {
                    $id_candidate = (int) $id_candidate;

                    $candidates[ $id_candidate ] = $this->upsertCandidate($id_candidate, $user, $ats, true);
                    $candidates[ $id_candidate ] = $candidates[ $id_candidate ]->toAPI();
                }

                // if ($data['debug'])
                //     $this->sm->get("Email")->setDebug( true );

                // foreach ($email_param['candidates'] as &$candidate)
                // {
                //     $candidate['url'] = $this->api>-getUrlCandidate( $candidates[ $item['id_user'] ]['id'] );
                // }

                // $this->sm->get("Email")->sendEmailTemplate([$template, 'search'], $template, $user, $email_param);

            break;
            case 'create_candidate_full' :
                $id_candidate = (int) $data['id_candidate'];

                $this->upsertCandidate($id_candidate, $user, $ats, false);
            break;
            case 'close_by_candidate':
                // update status to rejected
                $id_candidate   = (int) $data['id_candidate'];
                $candidate      = $this->sm->get('UserTable')->getUser($id_candidate);

                if (!isset($candidate))
                {
                    $this->sm->get('Log')->error('Candidate not exist');
                    return;
                }

                $exist = $this->sm->get('AtsCandidateTable')->getByCandidateID( $id_candidate, $ats['id_ats'] );
                $id_api = $exist['id_api'];

                $this->api->updateCandidateState( $id_api, 'REJECTED');

                // SEND A MESSAGE ?!
                // /!\
                $content    = 'Candidate close the process.';
                // send message to company
                $this->sendEmail($content, $id_candidate, $user, $ats);
            break;
            case 'close_by_company':
                // update status to rejected
                $id_candidate   = (int) $data['id_candidate'];
                $candidate      = $this->sm->get('UserTable')->getUser($id_candidate);

                if (!isset($candidate))
                {
                    $this->sm->get('Log')->error('Candidate not exist');
                    return;
                }

                $exist = $this->sm->get('AtsCandidateTable')->getByCandidateID( $id_candidate, $ats['id_ats'] );
                $id_api = $exist['id_api'];

                $this->api->updateCandidateState( $id_api, 'REJECTED');
            break;
        }
    }

    private function getJobs($user, $ats)
    {
        $positions      = $this->sm->get('MarketplaceSearchTable')->getPositionsPercent();
        $offset         = 0;
        $limit          = 100;
        $jobs           = $this->api->getJobs($offset, $limit);
        $YBorder        = $this->sm->get('Api');
        $company        = $this->sm->get('AtsCompanyTable')->getByIDCompany($user->getCompany()->id_company);

        while ($offset < $jobs->getTotalFound())
        {
            $content = $jobs->getContent();

            foreach ($content as $job)
            {
                $details    = $this->api->getJob( $job->id );
                $exist      = $this->sm->get('AtsJobTable')->getByAPIID( $details->id, $ats['id_ats'] );

                if (null === $exist)
                {
                    if (true === $this->api->isJobValid( $details ))
                    {
                        list($tags_name, $position) = $this->sm->get('AtsService')->convertJob( $details->getName(), $details->getDescription());//, $sm_association, $details['function']['id'] );

                        if (null !== $tags_name)
                        {
                            $result         = $YBorder->marketplace->module('company')->user($user)->data(NULL, "GET", [
                                'search'    => implode(' ', $tags_name),
                                'tags'      => ['position' => [$position['id_position']]]
                            ]);

                            $data           = $result->value;
                            $api_data       = $result->api_data->paginate->jsonSerialize();

                            if (!empty($api_data['token']))
                            {
                                $params = [
                                    'name'          => $details->getName(),
                                    'description'   => $details->getDescription(),
                                    'has_alert'     => $details->hasAlert(),
                                    'is_public'     => $details->isPublic(),
                                    'token'         => $api_data['token']
                                ];

                                $YBorder->job->module('company')->user($user)->save(null, 'POST', $params);
                            }
                        }
                    }

                    $id_ats_job = $this->sm->get('AtsJobTable')->saveJob($details->id, $ats['id_ats'], $company['id_ats_company'], null);
                }
                else
                {
                    $id_ats_job = $exist['id_ats_job'];
                }

                $details->setAtsJobId( $id_ats_job );
                $details->saveValues();
            }

            $offset += $limit;

            $jobs = $this->api->getJobs($offset, $limit);
        }
    }

    private function getCandidates($user, $ats)
    {
        $offset         = 0;
        $limit          = 100;
        $candidates     = $this->api->getCandidates( $offset, $limit );

        while ($offset < $candidates->getTotalFound())
        {
            $content = $candidates->getContent();

            foreach ($content as $details)
            {
                $exist      = $this->sm->get('AtsCandidateTable')->getByAPIID( $details->id, $ats['id_ats'] );
                $histories  = $this->api->getCandidateHistory( $details->id );

                $candidate  = $this->sm->get('UserTable')->getUserByEmail( $details->email );

                if (null === $exist)
                {
                    // find candidate by email
                    $id_ats_candidate = $this->sm->get('AtsCandidateTable')->saveCandidate($details->id, $ats['id_ats'], (null === $candidate ? null : $candidate->id));

                    // add to job search email in order to never add them to YB alert
                    if (null !== $candidate)
                    {
                        $query = new SearchModel();

                        $this->sm->get('MarketplaceSearchMailTable')->insertMail($user, $query, $candidate->id);
                    }
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

                $state = null;

                if ($histories->getTotalFound() > 0)
                {
                    $data_histories = $histories->getContent();
                    $history        = array_pop($data_histories);
                    $state          = $history['status'];
                }

                $details->setAtsCandidateId( $id_ats_candidate );
                $details->saveValues();

                list($job_id, $tmp_state) = $this->api->getJobId( $id_ats_candidate );

                if (null !== $tmp_state)
                    $state = $tmp_state;
                // check if add job id
                // $job_id = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'primaryAssignment_job_id');

                // if (null === $job_id)
                // {
                //     $job_id = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'secondaryAssignments_job_id');

                //     if (null !== $job_id)
                //     {
                //         $state = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'secondaryAssignments_status');
                //     }
                // }
                // else
                // {
                //     $state = $this->sm->get('AtsCandidateTable')->getValue($id_ats_candidate, 'primaryAssignment_status');
                // }

                if ($job_id !== null)
                {
                    $job    = $this->sm->get('AtsJobTable')->getByAPIID( $job_id, $ats['id_ats'] );
                    $job_id = $job['id_ats_job'];
                }

                if (null === $exist && $histories->getTotalFound() > 0)
                {
                    // historyModel ?
                    foreach ($data_histories as $history)
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

            $offset += $limit;

            $candidates = $this->api->getCandidates($offset, $limit);
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

        $place      = $this->sm->get('UserTable')->getPlaceUser($candidate['id_user']);

        $model_name = '\Core\Model\Ats\\' . ucfirst($ats['name']) . '\CandidateModel';
        $model      = new $model_name();

        // save candidate
        if (null === $exist)
        {
            // token of the smartrecruiters conversation's
            $token = generate_token(30);
            $model->importFromCV( $candidate['cv'], $token, $place, $anonymize );

            $modelCandidate     = $this->api->json('candidates', $model->toAPI());
            $id_api             = $modelCandidate->id;
            $id_ats_candidate   = $this->sm->get('AtsCandidateTable')->saveCandidate( $id_api, $ats['id_ats'], $id_candidate );

            $modelCandidate->setAtsCandidateId( $id_ats_candidate );
            $modelCandidate->saveValues();

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

            // update values & DB association
            $modelCandidate = $this->api->json('candidates', $api_data);
            $modelCandidate->setAtsCandidateId( $exist['id_ats_candidate'] );
            $modelCandidate->saveValues();

            $this->sm->get('AtsCandidateTable')->updateCandidate( $modelCandidate->id, $ats['id_ats'], $id_candidate );
        }

        $picture = null;
        if (!empty($candidate['cv']['picture']))
            $picture = $candidate['cv']['picture'];
        else if (!empty($candidate['picture']))
            $picture = $candidate['picture'];

        // upload candidate AVATAR
        if (null !== $picture)
        {
            $this->api->uploadCandidatePicture( $id_api, $picture );
        }

        if (false === $anonymize && true === $candidate['cv']['has_pdf'])
        {
            $pdf_link   = $this->sm->get('CVTable')->getCVPDF($candidate['id_user']);

            if (null !== $pdf_link)
            {
                $this->api->uploadCandidateResume( $id_api, $pdf_link );
            }
        }

        if (true === $anonymize)
        {
            $status = 'NEW';

            $content = 'Candidate #[CANDIDATE:' . $id_api . '] was added.' . PHP_EOL;

            $this->api->sendMessage( $content, true );
        }
        else
        {
            $status     = 'IN_REVIEW';
            $content    = 'Candidate ' . $candidate['cv']['firstname'] . ' ' . $candidate['cv']['lastname'] . ' accept your intouch request.' . PHP_EOL . 'Email : ' . $candidate['cv']['email'];
            // send message to company
            $this->sendEmail($content, $id_candidate, $user, $ats);

            // send message API
            $content = 'Candidate #[CANDIDATE:' . $id_api . '] ' . $candidate['cv']['firstname'] . ' ' . $candidate['cv']['lastname'] . ' is now in touch with your company.' . PHP_EOL;

            $this->api->sendMessage( $content, true );
        }

        try
        {
            // update status
            $this->api->updateCandidateState($id_api, $status);
        }
        catch (\Exception $e)
        {
            // if error : do nothing. Reason : Same image so do not need to update.
        }

        return $modelCandidate;
    }

    private function sendEmail( $content, $id_candidate, $user, $ats )
    {
        $reply_to = $this->sm->get('AtsMessageTable')->getReplyTo( $id_candidate, $user->id );

        if (null !== $reply_to)
        {
            $this->sm->get("Email")->sendRaw(['inbox', 'message', 'new'], $content, $reply_to);
        }
    }
}
