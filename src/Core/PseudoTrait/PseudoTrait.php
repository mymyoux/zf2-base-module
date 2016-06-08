<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 30/09/2014
 * Time: 21:12
 */

namespace Core\Core\PseudoTrait;


abstract class PseudoTrait
{
	protected $subject;
	abstract public function getName();
	public function link($subject)
	{
		$this->subject = $subject;
	}  
	public function toArray()
	{
        $data = to_array($this, $this);
        return $data;
        if(!isset($value))
        {
            $value = $this;
        }
        $keys = get_class_vars(get_class($value));
        $data = array();
        foreach($keys as $key => $value)
        {
            if(!starts_with($key, "_"))
            {
                $data[$key] = $this->$key;
                if(is_array($this->$key))
                {
                    foreach($this->$keys as $k=>$v)
                    {
                        if(isset($v))
                        {
                            $data[$key][$k] = $this->toArray($v);
                        }
                    }
                }else
                {
                    if(is_object($this->$key))
                    {
                        if(method_exists($this->$key,"toArray"))
                        {
                            $data[$key] = $this->$key->toArray();
                        }
                    }
                }
            }
        }
        if(property_exists($this, "id"))
        {

            $data["id_".$this->getShortName()] = $this->id;
        }
        return $data;
	}
} 
