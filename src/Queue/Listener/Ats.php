<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

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
        var_dump($data);
        $this->api = $this->sm->get('ApiManager')->get('smartrecruiters');

        $user = $this->sm->get('UserTable')->getNetworkByUser( 'smartrecruiters', $data['id_user'] );

        if (null !== $user)
        {
            $this->api->setAccessToken( $user['access_token'] );

            $jobs = $this->api->request('GET',  $data['api'], []);

            foreach ($jobs['content'] as $job)
            {
                $details = $this->api->request('GET', 'jobs/' . $job['id'], []);
                var_dump($details);
            }
            // var_dump($jobs);
        }
    }

}
