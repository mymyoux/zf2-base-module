<?php

namespace Core\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\Console\ColorInterface as Color;

class Log extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
	CONST LOG_CRITICAL  = 6;
    CONST LOG_ERROR     = 5;
    CONST LOG_BG_DEBUG  = 4;
    CONST LOG_WARN      = 3;
    CONST LOG_DEBUG     = 2;
    CONST LOG_INFO      = 1;
    CONST LOG_NONE      = 0;

	private $debug 		  = false;
    private $metrics        = [];
    private $critical       = null;
    private $display_time   = true;
    private $config_query   = null;

    public function __construct()
    {
        $this->metrics = [];
    }

    public function logSqlQuery( $type, $strRequest, $start_time )
    {
        if (null === $this->config_query)
        {
            $this->config_query = $this->sm->get('AppConfig')->get('query_log');
        }

        if (false === in_array($type, $this->config_query['types_allow']))
            return false;

        $duration = (microtime( true ) - $start_time) * 1000;

        if ($duration < $this->config_query['min_duration'])
        {
            return false;
        }

        try
        {
            throw new \Exception('log_query_sql', 1);
        }
        catch(\Exception $e)
        {
            // log
            $is_cron    = $this->sm->get('AppConfig')->isCron();
            $is_front   = !$this->sm->get('AppConfig')->isCLI();
            $is_queue   = $this->sm->get('AppConfig')->isQueue();
            $stack      = $e->getTraceAsString();

            $this->sm->get('QueryLogTable')->insertLog($type, $strRequest, $duration, $is_front, $is_cron, $is_queue, $stack);
        }
    }

    public function setDisplayTime( $boolean )
    {
        $this->display_time = $boolean;
    }

    public function logApiCall( $message = null )
    {
        $this->logMetric('api_call', 1);
        if (null !== $message)
            $this->log($message, $type);
    }

    public function logApiBatch( $message = null )
    {
        $this->logMetric('api_batch', 1);
        if (null !== $message)
            $this->log($message, $type);
    }

    public function logApiCallError( $message = null )
    {
        $this->logMetric('api_call_error', 1);
        if (null !== $message)
            $this->log($message, $type);
    }

    public function logMetric( $stat_name, $value )
    {
        if (false === isset($this->metrics[ $stat_name ]))
            $this->metrics[ $stat_name ] = 0;

        $this->metrics[ $stat_name ] += $value;
    }

    public function getCriticalMessage()
    {
        return $this->critical;
    }

    public function getMetric( $metric_name )
    {
        return isset($this->metrics[ $metric_name ]) ? $this->metrics[ $metric_name ] : 0;
    }

    public function getMetrics()
    {
        return $this->metrics;
    }

    public function setDebug( /*\boolean*/ $debug )
    {
    	$this->debug = $debug;
    }

    public function warn( $message )
    {
        $this->logMetric('warn', 1);
    	$this->log($message, self::LOG_WARN);
    }

    public function error( $message )
    {
        $this->logMetric('error', 1);
    	$this->log($message, self::LOG_ERROR);
    }

    public function critical( $message )
    {
        $this->critical = $message;
        $this->logMetric('critical', 1);
        $this->log($message, self::LOG_CRITICAL);
    }
    public function fatal($message)
    {
        $this->critical($message);
        exit();
    }

    public function normal( $message )
    {
    	$this->log($message, self::LOG_NONE);
    }

    public function info( $message )
    {
    	$this->log($message, self::LOG_INFO);
    }

    public function color( $message, $color, $rc = true )
    {
        if (false === $this->debug && $type < self::LOG_ERROR) return;

        $begin = $end = '';

        if (true === $this->display_time)
            $begin = '[' . date('Y-m-d H:i:s') . '] ' . $begin;

        if (php_sapi_name() === 'cli')
        {
            if (defined('CRON') && CRON === true)
                echo $begin . $message . $end . ($rc ? PHP_EOL : '');
            else
                $this->sm->get('console')->write($begin . $message . $end . ($rc ? PHP_EOL : ''), $color);
        }
    }

    public function debug( $message, $bg = false )
    {
    	$this->log($message, (false === $bg ? self::LOG_DEBUG : self::LOG_BG_DEBUG));
    }

    public function log( $message, $type = self::LOG_NONE )
    {
        if (false === $this->debug && $type < self::LOG_ERROR) return;

        $begin = $end = '';

        switch ( $type )
        {
            case self::LOG_CRITICAL :
                $color 	= Color::RED;
                $begin 	= '/!\\ ';
            break;
            case self::LOG_BG_DEBUG :
                $color = Color::BLUE;
            break;
            case self::LOG_ERROR :
                $color = Color::LIGHT_RED;
            break;
            case self::LOG_WARN :
                $color = Color::YELLOW;
            break;
            case self::LOG_INFO :
                $color = Color::GREEN;
            break;
            case self::LOG_DEBUG :
                $color = Color::LIGHT_BLUE;
            break;
            default:
            	$color = Color::NORMAL;
            break;
        }

        if ($begin !== $end)
        {
            if (self::LOG_CRITICAL === $type)
                $end .= ' /!\\';
        }

        if (true === $this->display_time)
            $begin = '[' . date('Y-m-d H:i:s') . '] ' . $begin;

        if (php_sapi_name() === 'cli')
        {
            if (defined('CRON') && CRON === true)
                echo $begin . $message . $end . PHP_EOL;
            else
                $this->sm->get('console')->write($begin . $message . $end . PHP_EOL, $color);
        }
    }

	public function getConsole()
	{
		return $this->sm->get('console');
	}
}
