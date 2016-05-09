<?php

namespace Core\Console\Queue;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Pheanstalk\Job as PheanstalkJob;

class ListenController extends \Core\Console\CoreController
{
    CONST DESCRIPTION   = 'Queue system management';

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    /**
     * @return
     */
    public function startAction()
    {
        $name = $this->params()->fromRoute('name');
        if (!$name)
        {
            $this->getLogger()->error('No name param.');
            exit();
        }
        $this->listen( $name );
    }

    public function listen( $name )
    {
        $config      = $this->sm->get('AppConfig')->get('beanstalkd');
        $this->tries = $config['retry_count'];
        $ip          = $config['ip'];
        $port        = $config['port'];

        $this->queue = new Pheanstalk($ip, $port);

        $this->queueName = $this->sm->get('AppConfig')->getEnv() . '-' . $name;


        $modules = $this->sm->get("ApplicationConfig")["modules"];
        $modules = array_reverse($modules);
        $classname = ucfirst(camel($name));
        foreach($modules as $module)
        {
            $object_name = '\\'.ucfirst($module).'\Queue\Listener\\' . $classname;
            if (false === class_exists($object_name))
            {
                continue;
            }
            break;
        }
        if (false === class_exists($object_name))
        {
            throw new \Exception('Class `' . $object_name . '` not exist', 1);
        }

        $listener = new $object_name;

        $listener->setServiceLocator( $this->sm );

        $this->queue->watch($this->queueName)->ignore('default');

        $this->getLogger()->debug('Listening to `' . $this->queueName . '`');

        while ($job = $this->queue->reserve())
        {
            try
            {
                $this->getLogger()->normal($this->queueName . 'job received! ID (' . $job->getId() . ')');

                $data   = json_decode($job->getData(), True);
                $log    = $this->sm->get('BeanstalkdLogTable')->findById( $data["_id_beanstalkd"] );

                $this->getLogger()->debug('ID BeanstalkdLogTable (' . $log['id'] .')');

                if (true !== $listener->checkJob( $data ))
                {
                    $this->getLogger()->error('delete Job (not valid)');
                    $this->queue->delete($job);
                    continue;
                }

                $listener->preexecute( $data );
                $this->sm->get('BeanstalkdLogTable')->setSend($log['id'], true);

                $this->queue->delete($job);
                $this->getLogger()->info('Success (delete the job)');
            }
            catch (\Exception $e)
            {
                $this->getLogger()->error("ERROR! " . $e->getMessage());
                $this->sm->get('ErrorTable')->logError( $e );

                $jobsStats = $this->queue->statsJob($job);

                if ($jobsStats->releases > $this->tries) {
                    $this->getLogger()->error("Burrying job!");
                    $this->buryJob($job, $this->queue);
                } else {
                    $this->getLogger()->warn('retrying in 60 seconds!');
                    echo "retrying in 60 seconds!" . PHP_EOL;
                    $this->queue->release($job, PheanstalkInterface::DEFAULT_PRIORITY, 60);
                }

            }
        }
    }

    protected function buryJob(PheanstalkJob $job, Pheanstalk $queue)
    {
        $queue->bury($job);
    }

    public function work()
    {
        // Listening to the 'emails' this->queue
        $this->queue->watch($this->queueName)->ignore('default');


        echo "Working next job in the 'emails" . ZEND_ENV . "' queue" . PHP_EOL;

        $job = $this->queue->reserve();
        echo "Email job received! Job Id is " . $job->getId() . PHP_EOL;

        $email = json_decode($job->getData(), true);

        if (!isset($email['message']) || !isset($email['templateName']) || !isset($email['templateContent'])) {
            // Email Incomplete
            echo "Email incomplete!" . PHP_EOL;

            $this->queue->delete($job);
        }

        if ($email['test'] === true) {
            $mandrill = new \Mandrill(getenv('MANDRILL_TEST'));
        } else {
            $mandrill = new \Mandrill(getenv('MANDRILL'));
        }

        try {
            $mandrill->messages->sendTemplate($email['templateName'], $email['templateContent'], $email['message']);
            echo "Email sent!" . PHP_EOL;
            $this->queue->delete($job);
        } catch (\Mandrill_Error $e) {
            echo "Error!" . PHP_EOL;

            $jobsStats = $this->queue->statsJob($job);

            if ($jobsStats->releases > $this->tries) {
                echo "Burrying job!" . PHP_EOL;
                $this->buryJob($job, $this->queue);
            } else {
                echo "retrying in 60 seconds!" . PHP_EOL;
                $this->queue->release($job, PheanstalkInterface::DEFAULT_PRIORITY, 60);
            }

        }

    }
}
