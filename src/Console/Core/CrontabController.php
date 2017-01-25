<?php
/**
 * @author Andreosso Benjamin <benjamin.andreosso@gmail.com>
 * @version 1.0
 */

namespace Core\Console\Core;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\Console\Adapter\AdapterInterface as Console;

class CrontabController extends \Core\Console\CoreController
{
    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    private function storeUsage()
    {
        $this->memory_usage   = memory_get_peak_usage(true);
        $this->cpu_usage      = sys_getloadavg()[0];
        $this->execution_time = microtime(true);
    }

    private function initCron( $name, $user_id )
    {
        $this->modelCron    = $this->sm->get('CronTable');
        $this->modelCronLog = $this->sm->get('CronLogTable');

        if (null === $user_id)
            $cron           = $this->modelCron->findByName( $name );
        else
            $cron           = $this->modelCron->findByNameAndUser( $name, $user_id );
        //if not found check if there is a shortcut
        if(null === $cron)
        {
            $classArr = $this->getClassArray();
            $search = array_filter($classArr, function($class) use ($name)
            {
                return mb_substr($class['name'], mb_strlen($class["parent"])+1)===$name;
            });
            if (count($search) === 1)
            {
                //need to reindex
                $search = array_values($search);
                $name = $search[0]['name'];
                return $this->initCron($name, $user_id);
            }elseif(count($search) > 1)
            {
                $this->getLogger()->error('Cron `' . $name . '` is ambiguous you must prefix it');
                $this->displayUsage();
                exit();
            }

            $search = array_filter($classArr, function($class) use ($name)
            {
                return $class['name'] === $name;
            });
            if (count($search) === 1)
            {
                $this->modelCron->insertCron([
                    'name'              => $name,
                    'user_id'           => $user_id,
                    'status'            => 'processing',
                    'last_launch_date'  => date('Y-m-d H:i:s'),
                    'directory'=>ROOT_PATH."/"
                ]);

                $this->getLogger()->info('Cron `' . $name . '` inserted');

                $cron = $this->modelCron->findByName( $name );
            }
            else
            {

                $this->getLogger()->error('Cron `' . $name . '` not exist');
                $this->displayUsage();
                exit();
            }
        }
        else
        {
            $this->modelCron->updateCron($cron->getId(), [
                'status'            => 'processing',
                'last_launch_date'  => date('Y-m-d H:i:s')
            ]);
        }

        $log_id = $this->modelCronLog->insertLog([
            'cron_id'       => $cron->getId(),
            'status'        => 'processing'
        ]);

        $log    = $this->modelCronLog->findByLogId( (int) $log_id );

        $this->entity       = $cron;
        $this->log_entity   = $log;
        return $cron;
    }

    public function updateLog( array $options, $save = false )
    {
        foreach ($options as $key => $value)
        {
            $method = 'set' . ucfirst($key);
            $this->log_entity->{ $method }( $value );

            if (method_exists($this->entity, $method))
                $this->entity->{ $method }( $value );
        }

        if (true === $save)
        {
            $this->modelCron->updateCron($this->entity->getId(), $this->entity->toArray());
            $this->modelCronLog->updateLog($this->log_entity->getId(), $this->log_entity->toArray());
        }
    }

    private function getMemoryUsage( $raw = false )
    {
        $unit       = ['b','kb','mb','gb','tb','pb'];

        if (null !== $this->memory_usage)
            $data   = memory_get_peak_usage(true) - $this->memory_usage;
        else
            $data   = 0;

        if (true === $raw) return $data;

        return @round($data/pow(1024,($i=floor(log($data,1024)))),2).' '.$unit[$i];
    }

    private function getCpuUsage( $raw = false )
    {
        if (null !== $this->cpu_usage)
            $data   = sys_getloadavg()[0] - $this->cpu_usage;
        else
            $data   = 0;

        if (true === $raw) return $data;

        return $data;
    }

    private function getExecutionTime( $raw = false )
    {
        if (null !== $this->cpu_usage)
            $data   = microtime(true) - $this->execution_time;
        else
            $data   = 0;

        if (true === $raw) return round($data);

        return round($data) . ' sec';
    }

    public function terminated()
    {
        echo PHP_EOL;

        $this->getLogger()->setDebug( true );

        $this->getLogger()->info( '[RAM] ' . $this->getMemoryUsage() );
        $this->getLogger()->info( '[LOAD] ' . $this->getCpuUsage() );
        $this->getLogger()->info( '[TIME] ' . $this->getExecutionTime() );

        $options = [
            'ram'               => $this->getMemoryUsage( true ),
            'load'              => $this->getCpuUsage( true ),
            'executionTime'     => $this->getExecutionTime( true ),
            'errors'            => $this->getLogger()->getMetric('error'),
            'warnings'          => $this->getLogger()->getMetric('warn'),
            'criticals'         => $this->getLogger()->getMetric('critical'),
            'insert'            => $this->getLogger()->getMetric('insert'),
            'update'            => $this->getLogger()->getMetric('update'),
            'delete'            => $this->getLogger()->getMetric('delete'),
            'select'            => $this->getLogger()->getMetric('select'),
        ];

        $status = 'ok';

        if ($options['errors'] > 0 || $options['criticals'] > 0)
            $status = 'ko';

        if ($options['criticals'] > 0)
            $options['criticalMessage'] = $this->getLogger()->getCriticalMessage();

        $options['status'] = $status;

        $this->updateLog($options, true);
    }

    public function getLogger()
    {
        return $this->sm->get('Log');
    }

    protected function forward()
    {
        if(!isset($this->_forward))
        {
            $this->_forward = $this->sm->get("controllerpluginmanager")->get("forward");
        }
        return $this->_forward;
    }

    private function uncamel($string)
    {
       $string = preg_replace('/(?<=\\w)(?=[A-Z])/',"-$1", $string);
       $string = mb_strtolower($string);

       return $string;
    }

    private function checkDir($path, $original_path, $original_ns)
    {
        $classArr = array();
        if (is_dir($path)) { // Does path exist?
            $dir = dir($path); // Dir handle
            while (false !== ($item = $dir->read())) {  // Read next item in dir
                if ($item !== '.' && $item !== '..')
                {
                    if (is_dir($path . $item))
                    {
                        $classArr = array_merge($classArr, $this->checkDir($path . $item, $original_path, $original_ns));
                        continue;
                    }
                    if (preg_match('/^([A-Za-z0-9]+)\.php$/', $item, $matches)) {
                        $namespace = str_replace($original_path, '', $path);
                        if (!empty($namespace))
                        $classArr[] = [
                            'path'      => $original_ns . $namespace . '\\' . $matches[1],
                            'name'      => mb_strtolower($namespace) . ':' . $this->uncamel(str_replace('Controller', '', $matches[1])),
                            'parent'    => mb_strtolower($namespace)
                        ];
                    }
                }
            }
            $dir->close();
        }


        return $classArr;
    }
    private function getClassArray()
    {
        // Iterate include paths
        $classArr = array();
        foreach ($this->includePathArr as $includePath) {
            $path = $includePath;

            $classArr = array_merge($classArr, $this->checkDir($includePath['path'], $includePath['path'], $includePath['ns']));
        }
        $classArr = array_values(array_filter($classArr, function($item)
        {
           /* if(property_exists($item["path"], "visible"))
            {
                if(!${$item["path"]}::visible)
                {
                    return False;
                }
            }*/
            if($item["path"] == "\Core\Console\Cli\CliTemplate")
            {
                return False;
            }
//            return $item["path"] !=
            return True;
        }));
        return $classArr;
    }

    private function displayUsage( $name = null )
    {
        $this->getLogger()->setDisplayTime( false );

        if (null === $name)
        {
            $classArr = $this->getClassArray();

            $max        = 0;
            $max_pos    = 0;
            $i          = 0;

            foreach ($classArr as &$aclass)
            {
                $i++;
                $aclass['position'] = '[' . $i . '] ';
                $lenght = mb_strlen($aclass['name'])+mb_strlen($aclass["position"]);
                if ($lenght > $max)
                    $max = $lenght;
                $aclass['length'] = $lenght;
                $lenght = mb_strlen($aclass["position"]);
                if ($lenght > $max_pos)
                    $max_pos = $lenght;
                $aclass['length_pos'] = $lenght;
            }

            $max += 5;

            $parent = null;

            // Debug output
            foreach ($classArr as $class)
            {

                if ($parent !== $class['parent'])
                {
                    $parent = $class['parent'];

                    $title  = '-- ' . $parent . ' --';
                    $this->getLogger()->color(str_repeat(' ', $max) . '|', \Zend\Console\ColorInterface::CYAN);
                    $this->getLogger()->color($title . str_repeat(' ', $max - mb_strlen($title)) . '|', \Zend\Console\ColorInterface::CYAN);
                }

                $path       = $class['path'];
                $spaces     = str_repeat(' ', $max - $class['length'] - ($max_pos - $class['length_pos']));
                $spaces_pos = str_repeat(' ', $max_pos - $class['length_pos']);

                $this->getLogger()->color($class["position"] . $spaces_pos, \Zend\Console\ColorInterface::CYAN, false);
                $this->getLogger()->color($class['name'], \Zend\Console\ColorInterface::WHITE, false);
                $this->getLogger()->color($spaces . '| ', \Zend\Console\ColorInterface::CYAN, false);
                if (defined($path . '::DESCRIPTION'))
                {
                    $this->getLogger()->normal(constant($path . '::DESCRIPTION'));
                }
                else
                {
                    echo PHP_EOL;
                }
            }
        }
        else
        {
            // @todo
        }

        $this->getLogger()->setDisplayTime( true );
    }
    protected function getParams()
    {
        $arguments = $this->getRequest()->getParams()->toArray();
        $params = [];
        foreach($arguments as $key=>$value)
        {
            if(is_numeric($key))
            {
                $params[] = $value;
            }
        }
        if(count($params))
        {
            if(strpos($params[0], ".") !== False)
            {
                unset($params[0]);
                $params = array_values($params);
            }
        }
        return $params;
    }
    public function generikAction()
    {
        echo PHP_EOL;
        $console = $this->getServiceLocator()->get('console');
        if (!$console instanceof Console) {
            throw new \RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }

        $this->includePathArr = [
            [
                'path' => './module/Admin/src/Console/',
                'ns'   => '\Admin\Console\\'
            ],
            [
                'path' => './vendor/Core/src/Console/',
                'ns'   => '\Core\Console\\'
            ]
        ];


        $this->includePathArr = array_map(function($item)
            {
                $item["path"] .="/".$item["name"]."/src/Console/";
                $item["ns"] = '\\'.$item["name"].'\Console\\';
                return $item;
            },$this->sm->get("Module")->getAvailableModules());
        $this->storeUsage();
        $this->getLogger()->setDebug(true);

        $parameters = $this->getParams();
        $arguments  = [];

        $name 		= isset($parameters[0]) ? $parameters[0] : null;

        if (null === $name)
        {
            $this->displayUsage();
            return;
        }
        //number => cron's name
        if(is_numeric($name))
        {

            $list = $this->getClassArray();
            $index = (int)$name;
            $index--;
            if(sizeof($list)>$index)
            {
                $name = $list[$index]["name"];
            }
        }
        $action = "start";
        if (isset($parameters[1]) && mb_strpos($parameters[1], '-') === false && mb_strpos($parameters[1], '=') === false)
            $action = $parameters[1];
        for ($i = 1; $i < count($parameters); ++$i)
        {
        	if (isset($parameters[$i]))
        	{
                if (mb_strpos($parameters[$i], '--') === 0)
                    $arguments[str_replace('--', '', $parameters[$i])] = true;
                else if (mb_strpos($parameters[$i], '-') === 0)
                {
                    $arguments[str_replace('--', '', $parameters[$i])] = $parameters[$i + 1];
                    ++$i;
                    continue;
                }
                else if (mb_strpos($parameters[$i], '=') !== false)
                {
                    list($key, $value) = explode('=', $parameters[$i]);
                    $arguments[ $key ] = (is_numeric($value) ? (int) $value : $value);
                }
                else
                    $arguments[] = $parameters[$i];
        	}
        	else
        		break;
        }
        $cron = $this->initCron( $name, isset($arguments['user_id']) ? $arguments['user_id'] : null );

        $name = $cron->getName();

        foreach ($this->includePathArr as $path)
        {
            $namespace = $path['ns'] . $cron->getPath();

            if(!$this->sm->get("Module")->loaded($path["name"]))
            {
                echo "full load:".$path["name"].PHP_EOL;
                 $this->sm->get("Module")->lightLoad($path["name"]);
            }
            if (class_exists($namespace . 'Controller'))
            {
                if(!$this->sm->get("Module")->loaded($path["name"]))
                {
                    $this->sm->get("Module")->fullLoad($path["name"]);
                    $this->sm->get("ControllerManager")->setInvokableClass($namespace,  $namespace . 'Controller', True);
                }
                break;
            }
        }

        $request = array_merge([
            'action'    => $action
        ], $arguments);

        try
        {
            $this->forward()->dispatch($namespace, $request);
        }
        catch (\Exception $e)
        {
        //    dd($this->sm->get("configuration"));
            $file = $e->getFile();
            if(!isset($file))
            {
                $file = "";
            }
            $file = substr($file, strlen(ROOT_PATH)+1);
            $this->getLogger()->critical($file.":".$e->getLine() );
            $this->getLogger()->critical($e->getMessage() );
            $this->getErrorTable()->logError($e);

            $this->getLogger()->warn($e->getTraceAsString());
        }

        $this->terminated();
    }

    public function startAction()
    {
        $platform   = (null !== $this->params()->fromRoute('platform')) ? (bool)$this->params()->fromRoute('platform') : 'cron';
        $prod       = (null !== $this->params()->fromRoute('prod')) ? (bool)$this->params()->fromRoute('prod') : false;
        $crons      = $this->modelCron->findAll( $platform );
        $lines      = [];

        foreach ($crons as $cron)
        {
            if (null === $cron['crontab_config']) continue;

            $configs = explode(';', $cron['crontab_config']);
            $options = explode(';', $cron['options']);

            foreach ($configs as $key => $config)
            {
                if (true === isset($options[$key]) && !empty($options[$key]) && false !== mb_strpos($options[$key], '|timezones|'))
                {
                    //
                    for ($i = -12; $i <= 12; ++$i)
                    {
                        $config_timezone = explode(' ', preg_replace('/[ ]+/', ' ', $config));
                        $config_timezone[1] = (24 - $i + (int)$config_timezone[1]) % 24;

                        $line = implode("\t", $config_timezone) . "\t";
                        $line .= $cron['user'] . "\t";

                        if (null === $cron['cmd'])
                            $line .= '/usr/bin/php ' . $cron['directory'] . 'console ' . preg_replace('/ /', ' ', $cron['name'], 1);
                        else
                            $line .= $cron['cmd'];

                        if (true === isset($options[$key]) && false === empty($options[$key]))
                            $line .= ' ' . str_replace('|timezones|', 'time=' . $i, $options[ $key ]);

                        if ($cron['server_log'] == 1)
                            $line .= ' >> ' . $cron['directory'] . 'logs/' . preg_replace('/[^A-Za-z0-9]/', '_',  $cron['name']) . '.log';

                        $lines[] = $line;
                    }
                    continue;
                }
                $line = preg_replace('/[ ]+/', "\t", $config) . "\t";
                $line .= $cron['user'] . "\t";

                if (null === $cron['cmd'])
                    $line .= '/usr/bin/php ' . $cron['directory'] . 'console ' . preg_replace('/ /', ' ', $cron['name'], 1);
                else
                    $line .= $cron['cmd'];

                if (true === isset($options[$key]) && false === empty($options[$key]))
                    $line .= ' ' . $options[ $key ];

                if ($cron['server_log'] == 1)
                    $line .= ' >> ' . $cron['directory'] . 'logs/' . preg_replace('/[^A-Za-z0-9]/', '_',  $cron['name']) . '.log';

                $lines[] = $line;
            }
        }

        $data = implode(PHP_EOL, $lines) . PHP_EOL;
        echo $data;

        if (true === $prod)
        {
            $name       = 'yborder_' . str_replace('-', '_', $platform);
            $filename   = '/etc/cron.d/' . $name;

            $this->getLogger()->info('>> generate in ' . $filename);

            if (true === file_exists($filename))
            {
                $old_content = file_get_contents($filename);

                if ($old_content !== $data)
                {
                    $this->getLogger()->info('>> UPDATE CONTENT');
                    file_put_contents($filename, $data);
                }
                else
                {
                    $this->getLogger()->normal('== no update');
                }

            }
            else
                $this->getLogger()->error('<< file not exist ' . $filename);
        }
    }

    public function launchAction()
    {
        $platform   = (null !== $this->params()->fromRoute('platform')) ? (bool)$this->params()->fromRoute('platform') : 'cron';
        $crons      = $this->modelCron->findAllUser( $platform );

        foreach ($crons as $cron)
        {
            if (    $cron['name'] === 'twitter:live'
                ||  $cron['name'] === 'twitter-live')
            {
                $this->startCron( $cron );
            }
        }

        // update if closed

    }

    private function startCron( $cron )
    {
        if ($cron['user_id'] === null) return;

        if ($cron['status'] === 'waiting')
        {
            $command = 'nohup /usr/bin/php ' . ROOT_PATH . '/console ' . str_replace('-', ' ', $cron['name']) . ' user_id=' . $cron['user_id'] . ' >> ' . ROOT_PATH . '/logs/' . $cron['cron_id'] .'-' . $cron['user_id'] . '.log &';

            $this->getLogger()->info('>> Run ' . $cron['name'] . ' with ' . $cron['user_id']);
            $this->getLogger()->debug($command);
            shell_exec($command);
            sleep(2);
        }

        if (!$cron['to_kill'])
        {
            if ($cron['status'] === 'processing')
            {
                $grep       = 'console ' . str_replace('-', ' ', $cron['name']) . ' user_id=' . $cron['user_id'];
                $command    = 'ps -ax | grep "' . $grep . '"';
                $output     = shell_exec($command);

                if (mb_strpos($output, '/' . $grep) !== false)
                {
                    // RUNNING do nothing
                }
                else
                {
                    // NOT RUNNING state KO
                    $this->modelCron->updateCron($cron['cron_id'], ['status' => 'ko', 'to_kill' => 0]);
                    $this->getLogger()->error('>> CRON [' . $cron['cron_id'] . '] is KO');
                }
            }
        }
        else
        {
            $grep       = 'console ' . str_replace('-', ' ', $cron['name']) . ' user_id=' . $cron['user_id'];
            $command    = 'ps -ax | grep "' . $grep . '"';
            $output     = shell_exec($command);

            $output     = mb_substr($output, 0, mb_strpos($output, PHP_EOL));

            if (mb_strpos($output, '/' . $grep) !== false)
            {
                // KILL THE SCRIPT
                preg_match('/[0-9]+/', $output, $matches);
                $pid = $matches[0];
                shell_exec('kill ' . $pid);
                $this->getLogger()->debug('>> KILL pid:' . $pid . ' cron [' . $cron['cron_id'] . ']');
                sleep(2);
                $this->modelCron->updateCron($cron['cron_id'], ['status' => 'ko', 'to_kill' => 0]);
            }

            if ($cron['to_kill'] == 2)
            {
                $command = 'nohup /usr/bin/php ' . ROOT_PATH . '/console ' . str_replace('-', ' ', $cron['name']) . ' user_id=' . $cron['user_id'] . ' >> ' . ROOT_PATH . '/logs/' . $cron['cron_id'] .'-' . $cron['user_id'] . '.log &';

                $this->getLogger()->info('>> Run ' . $cron['name'] . ' with ' . $cron['user_id']);
                $this->getLogger()->debug($command);
                shell_exec($command);
                sleep(2);
                $this->modelCron->updateCron($cron['cron_id'], ['status' => 'processing', 'to_kill' => 0]);
            }
        }
    }
    public function getErrorTable()
    {
        return $this->sm->get("ErrorTable");
    }
}
