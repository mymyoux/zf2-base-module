<?php
namespace Core\Queue\Listener\Ask;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

use Application\Service\JobConfig;

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
        $admin      = $this->sm->get('UserTable')->getConsoleUser( 'admin' );
        $ats_job    = $this->getAtsJobTable()->getJobValueByIdAtsJob($data["id_external_ask"]);

        if(!$data["answer"]["valid"])
        {
            //refused

            $this->api->jobConvert->user($admin)->post()->insert([
                "title"                 => $ats_job["title"],
                "description"           => $ats_job["content"],
                "type"                  => "ask",
                "algo"                  => "v1",
                "id_position"           => null,
                "is_valid"              => 1,
                "is_title_auto_valid"   => $data["answer"]["explicit"]
            ]);

            if(isset($ats_job["id_job"]))
            {
                $this->api->job->module("company")->user($admin)->post()->deleteforce(["id_job"=>$ats_job["id_job"]]);
            }
        }else
        {
            $ats            = $this->sm->get('AtsTable')->getById( $ats_job['id_ats'] );
            $ats_company    = $this->getAtsCompanyTable()->getByIDAtsCompany($ats_job["id_ats_company"]);
            $id_company     = $ats_company["id_company"];
            $company        = $this->sm->get('CompanyTable')->getCompanyByID( $id_company );

            $this->sm->get('CompanyTable')->getInformations( $company );

            // set new position
            $config                 = new JobConfig();
            $config->id_position    = $data->id_external_answer;

            $this->sm->get('AtsService')->upsertAlertJob( $ats_job['id_ats_job'], $ats, $company, $config );

            $this->api->jobConvert->user($admin)->post()->insert([
                "title"                 => $ats_job["title"],
                "description"           => $ats_job["content"],
                "type"                  => "ask",
                "algo"                  => "v1",
                "id_position"           => $data->id_external_answer,
                "is_valid"              => 1,
                "is_title_auto_valid"   => $data["answer"]["explicit"]
            ]);
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
