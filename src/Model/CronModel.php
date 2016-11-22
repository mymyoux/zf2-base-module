<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 15:21
 */

namespace Core\Model;

class CronModel extends CoreModel
{
	protected $cron_id;
	protected $name;
	public $status;
	public $last_execution_time;

	public function getId()
	{
		return $this->cron_id;
	}

	public function getName()
	{
		return $this->name;
	}
	public function getPath()
	{
		$path =  str_replace(' ', '\\', ucwords(str_replace(':', ' ', $this->name))) . '';
		$path = str_replace(' ', '', ucwords(str_replace('-', ' ', $path)));
		return $path;
	}
	public function getController()
	{

	}
	public function getStatus()
	{
		return $this->status;
	}

	public function setStatus( $status )
	{
		$this->status = $status;

		return $this;
	}

	public function setExecutionTime( $time )
	{
		$this->last_execution_time = $time;

		return $this;
	}
}
