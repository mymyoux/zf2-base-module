<?php

namespace Core\Model\Ats;

use Core\Model\CoreModel;
use Zend\ServiceManager\ServiceLocatorInterface;

class CandidateCoreModel extends CoreModel
{
	private $sm;

	public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;
    }

	public function setAtsCandidateId( $id )
	{
		$this->_id_ats_candidate = $id;
	}

	public function getAtsCandidateId()
	{
		return $this->_id_ats_candidate;
	}

	public function saveValues()
	{
		if (!$this->_id_ats_candidate)
			throw new \Exception("No ATS ID", 4);

		$data = $this->toArray();

		$this->_saveValue( '', $data );
	}

	private function _saveValue( $key, $value )
	{
		if (is_array($value))
		{
			foreach ($value as $_key => $_value)
			{
				$this->_saveValue((!empty($key) ? $key . '_' : '') . $_key, $_value);
			}
		}
		else
		{
			// echo 'save ' . $key . ' => ' . $value . PHP_EOL;
			$this->sm->get('AtsCandidateTable')->saveCandidateValue( $this->getAtsCandidateId(), $key, $value );
		}
	}

}
