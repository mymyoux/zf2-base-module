<?php

namespace Core\Queue;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Queue;
use Notification;
use DB;
use Core\Model\Beanstalkd;
use App\User;
use Auth;
use Logger;
use  Illuminate\Queue\Jobs\JobName;
use Illuminate\Contracts\Bus\Dispatcher;
use App;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\ManuallyFailedException;
use Core\Queue\Jobs\FakeBeanstalkdJob;

use Exception;
use Throwable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Core\Traits\ServiceLocator;
class LJob
{
    use ServiceLocator;
     const STATE_CREATED = "created";
    const STATE_EXECUTED = "executed";
    const STATE_EXECUTED_FRONT = "executed_front";
    const STATE_EXECUTED_NOW = "executed_now";
    const STATE_FAILED = "failed";
    const STATE_FAILED_PENDING_RETRY = "failed_pending_retry";
    const STATE_PENDING = "pending";
    const STATE_RETRYING = "retrying";
    const STATE_CANCELLED = "cancelled";
    const STATE_REPLAYING = "replaying";
    const STATE_REPLAYING_EXECUTED = "replayed";
    const STATE_REPLAYING_FAILED = "replay_failed";
    const STATE_EXECUTING = "executing";
    /**
     * DELETED by error
     */
    const STATE_DELETED = "deleted";
    const DEFAULT_TTR = 429496729;
    private $data;
    private $class;
    private $identifier = null;
    public $id_user = null;
    public $prefix;
    private $beanstalkd;


    public function __construct( $class, $data = NULL)
    {
        
        $this->class        = $class;
        $this->data         = $data;
    }
    public function init()
    {
        $this->tube   = $this->buildTubeName($this->class);
    }
       protected function getBeanStalkd()
    {
        if (!$this->beanstalkd)
        {
            $config             = $this->sm->get('AppConfig')->get('beanstalkd');

            $this->ip           = $config['ip'];
            $this->port         = $config['port'];

            $this->beanstalkd   = new Pheanstalk($this->ip, $this->port);
        }
        return $this->beanstalkd;
    }
    public function getTube()
    {
        return $this->tube;
    }
    public function getUnprefixedTube()
    {
        if(isset($this->prefix))
        {
            return substr($this->tube, strlen($this->prefix));
        }
        return $this->tube;
    }
    public function identifier($identifier)
    {
        $this->identifier = $identifier;
        return $this;
    }
    public function user($user)
    {
        if(is_numeric($user))
        {
            $this->id_user = $user;
            return $this;
        }
        $this->id_user = $user->id_user;
        return $this;
    }
    public function data($data)
    {
        $this->data = $data;
        return $this;
    }
    public function set($name, $value)
    {
        if(!isset($this->_data))
        {
            $this->_data[] = [];
        }
        $this->_data[$name] = $value;
        return $this;
    }
    /**
     * Build tube name
     * @param  string $class Tube's name
     * @return string Tube's name  with prefix
     */
    protected function buildTubeName($class)
    {
        if (defined("$class::name"))
        {
            $tube = $class::name;
            $prefix = $this->sm->get('AppConfig')->getEnv()."_";
            if(isset($this->prefix))
            {
                $prefix.=$this->prefix;
            }
            $tube = $prefix.$tube;
            return $tube;
        }

        $tube   = null;

        $index = strpos($class, 'Queue\\');
        if($index !== False)
        {
            $index+=6;
        }
        $index2 = strpos($class, 'Jobs\\');
        if($index2 !== False)
        {
            $index2 += 5;
        }
        if($index === False || ($index2 !== False && $index>$index2))
        {
            $index = $index2;
        }
        if($index === False)
        {
            throw new \Exception('Queue must be inside Queue or Jobs folder');
        }

        $path = substr($class, $index);
        $paths = explode('\\', strtolower($path));
        $last = array_pop($paths);
        if(!isset($tube))
        {
            $tube = $last;
        }
        if(!empty($paths))
        {
            $prefix = join("/", $paths);
            $tube = $prefix."/".$tube;
        }

        $prefix = $this->sm->get('AppConfig')->getEnv()."_";
        if(isset($this->prefix))
        {
            $prefix.=$this->prefix;
        }
        $tube = $prefix.$tube;

        return $tube;
    }
    public function cancelAllPrevious()
    {
        $pheanstalk = Queue::getPheanstalk();
        $request = \Core\Model\Beanstalkd::where('queue', '=', $this->tube)
            ->whereIn("state", [Beanstalkd::STATE_CREATED, Beanstalkd::STATE_RETRYING, Beanstalkd::STATE_PENDING, Beanstalkd::STATE_FAILED_PENDING_RETRY ]);

        if (isset($this->id_user))
            $request->where('id_user', '=', $this->id_user);

        if(isset($this->identifier))
            $request->where('identifier', '=', $this->identifier);

        $previous = $request->get();

        if (!empty($previous))
        {
            foreach($previous as $log)
            {
                if(isset($log["id_beanstalkd"]))
                {
                    try
                    {
                        $job = $pheanstalk->peek( $log["id_beanstalkd"] );

                        $pheanstalk->delete($job);
                    }
                    catch(\Exception $e)
                    {
                        Logger::error('Error delete previous' . $e->getMessage());
                    }

                    $log->state = Beanstalkd::STATE_CANCELLED;
                    $log->save();
                }
            }
        }

        return $this;
    }

    public function throttle( $delay = PheanstalkInterface::DEFAULT_DELAY, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $now = false )
    {
        return $this->cancelAllPrevious()->send($delay, $priority, $now);
    }

    private function sendAlert($now = false)
    {
        $request = \Core\Model\Beanstalkd::where('queue', '=', $this->tube)
            ->where('state', '=', Beanstalkd::STATE_EXECUTED_FRONT)
            ->where('created_time', '>=', DB::raw('NOW() - INTERVAL 1 HOUR'))
            ;

        $count = $request->count();

        if (0 === $count && $now === false)
        {
            // error recursive alert beanstlakd
            // Notification::alert('beanstalkd');
        }
    }

    public function sendNow()
    {
        return $this->send(PheanstalkInterface::DEFAULT_DELAY, PheanstalkInterface::DEFAULT_PRIORITY, true);
    }

    public function send($delay = PheanstalkInterface::DEFAULT_DELAY, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $now = false, $ttr = LJob::DEFAULT_TTR)
    {
        $id = $this->sm->get('BeanstalkdLogTable')->insertLog( json_encode($this->data), $this->tube, $delay, $this->id_user, $priority, $this->identifier, $this->class);

        
        try
        {
            $class  = $this->class;

            $job = new $class();
            $job->id = (int)$id;
           
            $data = new \stdClass;
            $data->displayName = $this->class;
            $data->job = "Illuminate\\Queue\\CallQueuedHandler@call";
            $data->maxTries = isset($this->class::$maxTries)?$this->class::$maxTries:3;
            $data->timeout = isset($this->class::$timeout)?$this->class::$timeout:0;
            $data->data = new \stdClass;
            $data->data->commandName = $this->class;
            $data->data->command = serialize($job);

        }
        catch (\Exception $e)
        {
           
        }


        
        $id_beanstalkd = $this->getBeanStalkd()->useTube($this->getTube())->put(json_encode($data), $priority, $delay, $ttr);
        $this->sm->get('BeanstalkdLogTable')->setBeanstalkdID($id, $id_beanstalkd);

        return $id_beanstalkd;
    }
}
