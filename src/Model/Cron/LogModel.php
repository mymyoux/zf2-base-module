<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 15:21
 */

namespace Core\Model\Cron;
use Core\Model\CoreModel;

class LogModel extends CoreModel
{
	protected $log_id;
	public $status;
	public $ram;
	public $load;
	public $execution_time;
	public $errors;
	public $critical;
	public $warnings;
	public $insert;
	public $update;
	public $select;
	public $delete;

	public function getId()
	{
		return $this->log_id;
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

	public function setRam( $ram )
	{
		$this->ram = $ram;

		return $this;
	}

	public function setLoad( $load )
	{
		$this->load = $load;

		return $this;
	}

	public function setExecutionTime( $execution_time )
	{
		$this->execution_time = $execution_time;

		return $this;
	}

	public function setErrors( $errors )
	{
		$this->errors = $errors;

		return $this;
	}

	public function setWarnings( $warnings )
	{
		$this->warnings = $warnings;

		return $this;
	}

	public function setInsert( $insert )
	{
		$this->insert = $insert;

		return $this;
	}

	public function setSelect( $select )
	{
		$this->select = $select;

		return $this;
	}

	public function setUpdate( $update )
	{
		$this->update = $update;

		return $this;
	}

	public function setDelete( $delete )
	{
		$this->delete = $delete;

		return $this;
	}

	public function setApiCall( $api_call )
	{
		$this->api_call = $api_call;

		return $this;
	}

	public function setApiCallError( $api_call_error )
	{
		$this->api_call_error = $api_call_error;

		return $this;
	}

	public function setApiBatch( $api_batch )
	{
		$this->api_batch = $api_batch;

		return $this;
	}

	public function setCriticals( $critical )
	{
		$this->critical = $critical;

		return $this;
	}

	public function setCriticalMessage( $critical_message )
	{
		$this->critical_message = $critical_message;

		return $this;
	}
}
