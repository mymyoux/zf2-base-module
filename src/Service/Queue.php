<?php

namespace Core\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Core\Queue\Job;
use Core\Model\UserModel;

/**
 * Class API
 * @package Application\Service
 */
class Queue extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    public function createJob( $tube, array $job, $user  = NULL)
    {

    	$id_user = $user;
    	if(isset($user))
    	{
    		if(!is_numeric($user))
    		{
    			if($user instanceof UserModel)
    			{
    				$id_user = $user->id;
    			}else
    			{
    				if(is_array($user) && isset($user["id_user"]))
    				{
    					$id_user = $user["id_user"];
    				}
    			}
    		}
    	}else
    	{
    		$id_user = $this->sm->get("Identity")->isLoggued()?$this->sm->get("Identity")->user->id:NULL;
    	}
    	if(isset($id_user) && !is_numeric($id_user))
    	{
    		throw new \Exception('id_user is not correct: '.((string)$id_user));
    	}
    	$job = new Job($tube, $job, $id_user);

        $job->setServiceLocator( $this->sm );

    	return $job;
    }
}
