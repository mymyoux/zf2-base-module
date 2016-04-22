<?php

namespace Core\Model\Ats;

use Core\Model\CoreModel;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class CandidateCoreModel extends CoreModel
{
	protected $sm;
	private $_id_ats_candidate = null;

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
			$this->sm->get('AtsCandidateTable')->saveCandidateValue( $this->getAtsCandidateId(), $key, $value );
		}
	}

	public function toAPI()
	{
		$data = $this->toArray();

		foreach ($data as $key => $value)
		{
			if ($value === null)
			{
				unset($data[$key]);
			}
		}

		return $data;
	}

	abstract public function importFromCV( $data, $token, $place, $anonymize = true );
}
