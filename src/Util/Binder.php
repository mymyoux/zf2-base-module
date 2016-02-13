<?php
namespace Core\Util;

use Core\Core\CoreObject;
use Core\Exception\FatalException;

class Binder extends CoreObject
{	
	private $toBind;
	private $prefix;
	private $suffix;
	public function __construct($toBind, $prefix = NULL, $suffix = NULL)
	{
		$this->toBind = $toBind;
		if(!isset($prefix))
		{
			$prefix = array();
		}
		if(!isset($suffix))
		{
			$suffix = array();
		}
		if(!is_array($prefix))
		{
			$prefix = array($prefix);
		}

		if(!is_array($suffix))
		{
			$suffix = array($suffix);
		}
		$this->prefix = $prefix;
		$this->suffix = $suffix;
	}

	public function __call($name, $arguments)
	{
		return call_user_func_array(array($this->toBind, $name), array_merge($this->prefix, $arguments, $this->suffix));
	}
	public function __get($name)
	{
		if($this->toBind->$name === False)
		{
			return False;
		}
		return new Binder($this->toBind->$name, $this->prefix, $this->suffix);
	}
	public function __isset($name)
	{
		return isset($this->toBind->$name);
	}
}