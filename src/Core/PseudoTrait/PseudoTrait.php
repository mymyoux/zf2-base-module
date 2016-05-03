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
	
} 
