<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 13/11/14
 * Time: 16:18
 */

namespace Core\Model;

use Core\Traits\ServiceLocator;

class ABModel extends CoreModel 
{
	use ServiceLocator;
	public $id_abtesting;
	public $id_user;
   	public $name;
   	public $test;
   	public $version;
   	public $previous;
   	public $value;
   	public $result;
   	public $state;
   	public $step;
   	public $id_external;

   	public function save()
   	{
   		$keys = array("previous", "value","result","id_external","state", "step");
   		$data = [];
   		foreach($keys as $key)
   		{
   			if(isset($this->$key))
   			{
   				$data[$key] = $this->$key;
   			}
   		}
   		if(empty($data))
   		{
   			return;
   		}
   		$data["id_abtesting"] = $this->id_abtesting;
   		$this->sm->get("API")->ab->post()->update($data);
   	}
   	public function nextStep()
   	{
   		$this->step++;
   		$this->save();
   	}
   	public function done()
   	{
   		$this->state = "end";
   		$this->save();
   	}
}
