<?php

namespace Core\Model\Ats\Smartrecruiters;

use Core\Model\Ats\AbstractJobModel;
use Core\Model\Ats\JobCoreModel;
use Core\Model\CoreModel;

class JobModel extends JobCoreModel implements AbstractJobModel
{
	public $id;
	public $title;
	public $refNumber;
	public $createdOn;
	public $updatedOn;
	public $location;
	public $industry;
	public $function;
	public $typeOfEmployment;
	public $experienceLevel;
	public $eeocategory;
	public $template;
	public $jobAd;
	public $status;
	public $actions;
	public $language;

	public function getName()
	{
		return $this->title;
	}

	public function getDescription()
	{
		$description = '';

		foreach ($this->jobAd['sections'] as $section)
		{
			if (!isset($section['text'])) continue;

			$description .= $section['title'] . PHP_EOL . PHP_EOL . $section['text'] . PHP_EOL . PHP_EOL;
		}

		return $description;
	}

	public function isPublic()
	{
		return true;
	}

	public function hasAlert()
	{
		return true;
	}

	public function getToken()
	{
		return null;
	}
}
