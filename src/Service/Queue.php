<?php

namespace Core\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Core\Queue\Job;

/**
 * Class API
 * @package Application\Service
 */
class Queue extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    public function createJob( $tube, array $job )
    {
    	$job = new Job($tube, $job, $this->sm->get("Identity")->isLoggued()?$this->sm->get("Identity")->user->id:NULL);

        $job->setServiceLocator( $this->sm );

    	return $job;
    }
}
