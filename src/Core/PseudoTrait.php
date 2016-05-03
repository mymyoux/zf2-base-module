<?php
namespace Core\Core;

use Core\Core\PseudoTrait\PseudoTrait;
trait PseudoTrait
{
   protected $traits = [];
   public function hasPseudoTrait($name)
   {
        return isset($this->traits[$name]);
   }
   public function getPseudoTrait($name)
   {
        return $this->traits[$name];
   }
   public function __call($name, $arguments)
   {
        foreach($this->traits as $key=>$pseudotrait)
        {
            if(method_exists($pseudotrait, $name))
            {
                return call_user_func_array(array($pseudotrait, $name), $arguments);
            }
        }
        throw new \Exception(get_class($this).": No trait implements the method '".$name."'");
   }
   public function addPseudoTrait(PseudoTrait $trait)
   {
        $this->traits[$trait->getName()] = $trait;
        $trait->link($this);
   }
} 
