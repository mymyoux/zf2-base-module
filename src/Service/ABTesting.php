<?php

namespace Core\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\Console\ColorInterface as Color;
use Core\Model\ABModel;

class ABTesting extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
	

    public function __construct()
    {
    }
    public function create($name,  $quantity, $user = NULL, $version = 1)
	{
		$total = $this->getABTable()->getTotal();
		if(isset($user))
		{
			$test = $user->id%$quantity;
		}else
		{
			$test = time()%$quantity;
		}
		$id_abtesting = $this->sm->get("API")->ab->post()->user($user)->create(array("name"=>$name, "version"=>$version,"test"=>$test))->value;
		$abtesting = new ABModel();
		$abtesting->setServiceLocator($this->sm);
		$abtesting->exchangeArray($this->sm->get("API")->ab->method("GET")->user($user)->get(array("id_abtesting"=>$id_abtesting))->value);

		return $abtesting;
	}
	public function get($name, $user = NULL, $version)
	{

		$result = $this->sm->get("API")->ab->get()->user($user)->get(array("name"=>$name, "version"=>$version))->value;
		if(!isset($result))
		{
			return NULL;
		}
		$abtesting = new ABModel();
		$abtesting->setServiceLocator($this->sm);
		$abtesting->exchangeArray($result);
		return $abtesting;
	}

 	protected function getABTable()
 	{
 		return $this->sm->get('ABTable');
 	}  
}
