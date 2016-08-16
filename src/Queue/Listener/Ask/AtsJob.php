<?php
namespace Core\Queue\Listener\Ask;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class AtsJob extends ListenerAbstract implements ListenerInterface
{
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
        $ats_job = $this->getAtsJobTable()->getJobValueByIdAtsJob($data["id_external_ask"]);
        if(!$data["answer"]["valid"])
        {
            //refused

            $this->api->jobConvert->post()->insert([
                "title"=>$ats_job["title"],
                "description"=>$ats_job["content"],
                "type"=>"ask",
                "algo"=>"v1",
                "id_position"=>NULL,
                "is_valid"=>1,
                "is_title_auto_valid"=>$data["answer"]["explicit"]
            ]);
            if(isset($ats_job["id_job"]))
            {
                dd("ok");
                /*$jobs = $this->getSearchJobTable()->getByJobIDAndUser($ats_job["id_job"], NULL);
                dd($jobs);*/
                $this->api->jobController->module("company")->post()->deleteforce(["id_job"=>$ats_job["id_job"]]);
            }
            dd("refused");
        }else
        {
            $ats_company = $this->getAtsCompanyTable()->getByIDAtsCompany($ats_job["id_ats_company"]);
            $id_company = $ats_company["id_company"];


            //TODO: handle création/modification + réfléchir pour les tags
            if(!isset($ats_job["id_job"]))
            {
                $convert_job = $this->sm->get('JobService')->convert( $ats_job["title"], $ats_job["content"]);


                if (true === $convert_job['success'])
                {
                    $employees      = $this->sm->get('CompanyTable')->getEmployees( $id_company );

                    foreach ($employees as $employee)
                    {
                        $user_employee  = $this->sm->get('UserTable')->getUser( $employee['id_user'] );

                        if (null === $user_employee) continue;

                        $tags = ['position' => [$convert_job['position']['id_position']]];

                        if (null !== $location)
                            $tags['location'] = $location;

                        $this->sm->get('Log')->normal('Employee (' . $user_employee->id . ')');
                        $result         = $YBorder->marketplace->module('company')->user($user_employee)->data(NULL, "GET", [
                            'search'    => implode(' ', $convert_job['tags']),
                            'tags'      => $tags
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

                            if (null !== $id_job)
                                $params['id_job'] = $id_job;

                            $this->sm->get('Log')->warn('create job');

                            $yborder_job    = $YBorder->job->module('company')->user($user_employee)->{ $action }(null, 'POST', $params);
                            if (true === isset($yborder_job->value))
                                $id_job = $yborder_job->value->id_job;

                            if ($action === 'save')
                                $action = 'edit';

                            $text = $company->name . ' (' . $company->id_company . ') ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->id . ') -- new job *' . $details->getName() . '* created';

                            $this->sm->get('Notifications')->ats( $text, 'job' );
                        }
                    }
                }
            }else
            {
                //do nothing for now
            }
            //accepted
            dd("accepted");
        }
    }
    protected function getNotifications()
    {
        return $this->sm->get("Notifications");
    }
    protected function getAtsJobTable()
    {
        return $this->sm->get("AtsJobTable");
    }
    protected function getAtsCompanyTable()
    {
        return $this->sm->get("AtsCompanyTable");
    }
    protected function getSearchJobTable()
    {
        return $this->sm->get("MarketplaceSearchJobTable");
    }

}
