<?php

namespace Core\Model\Ats;
use Core\Model\CoreModel;
use Zend\ServiceManager\ServiceLocatorInterface;

class JobCoreModel extends CoreModel
{
	private $sm;

	public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;
    }

	public function setAtsJobId( $id )
	{
		$this->_id_ats_job = $id;
	}

	public function getAtsJobId()
	{
		return $this->_id_ats_job;
	}

	public function saveValues()
	{
		if (!$this->_id_ats_job)
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
			$this->sm->get('AtsJobTable')->saveJobValue( $this->getAtsJobId(), $key, $value );
		}
	}

}
