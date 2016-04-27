<?php

namespace Core\Model\Ats\Smartrecruiters;

use Core\Model\Ats\CandidateCoreModel;
use Core\Model\CoreModel;

class CandidateModel extends CandidateCoreModel
{
	public $id;
	public $firstName;
	public $lastName;
	public $internal;
	public $email;
	public $primaryAssignment;
	public $secondaryAssignments;
	public $actions;
	public $web;
	public $location;
	public $phoneNumber;
	public $tags;
	public $education;
	public $experience;

	public $createdOn;
	public $updatedOn;

	public $source;

	public function importFromCV( $data, $token, $place, $anonymize = true )
	{
		$this->firstName 	= $data['firstname'];
		$this->lastName 	= $data['lastname'];
		$this->email 		= 'candidate+' . $token .'@yborder.com';
		// $this->email 		= (true === $anonymize ? 'candidate+' . $token .'@yborder.com' : $data['email']);
		$this->phoneNumber 	= (false === $anonymize && !empty($data['phone']) ? $data['phone'] : null);

		// location
		if (null !== $place)
		{
			$this->location = [
				'city'		=> $data['currentplace'],
				'country'	=> $place->country
			];
		}

		$this->tags 		= array_map(function($item){
			return $item['name'];
		}, $data['tags']);

		$this->education	= array_map(function($item){
			if (true === empty($item['name'])) return null;

			return [
				'institution'	=> $item['name'],
				'degree'		=> $item['degree'],
				'major'			=> $item['degree'],
				'current'		=> false,
				'startDate'		=> $item['start_duration_display'],
				'endDate'		=> $item['end_duration_display'],
			];
		}, $data['educations']);

		$this->education = array_filter($this->education, function($item){
			return $item !== null;
		});
		/*
		"title": "Technical Product Manager, Platform and Integrations",
      "company": "SmartRecruiters",
      "current": true,
      "startDate": "2014-01",
      "endDate": "2013-12"
		 */

		$this->experience	= array_map(function($item){
			$current = (bool) $item['current_job'];
			if (!isset($item['company'])) return null;

			return [
				'title'			=> $item['name'],
				'company'		=> $item['company']['name'],
				'description'	=> $item['description'],
				'current'		=> $current,
				'startDate'		=> $item['start_year'] . '-' . $item['start_month'],
				'endDate'		=> ($current ? date('Y-m') : $item['end_year'] . '-' . $item['end_month']),
			];
		}, $data['xp']);

		// add qualification
		if (true === isset($data['qualification']))
		{
			$description 	= 'Salary: ' . $data['qualification']['salary'] . PHP_EOL;
			$description   .= 'delay_availability: ' . $data['qualification']['delay_availability'] . PHP_EOL;
			$description   .= 'academic_level: ' . $data['qualification']['academic_level'] . PHP_EOL;
			$description   .= 'academic_reputation: ' . $data['qualification']['academic_reputation'] . PHP_EOL;
			$description   .= 'personal_skills: ' . $data['qualification']['personal_skills'] . PHP_EOL;
			$description   .= 'motivation: ' . $data['qualification']['motivation'] . PHP_EOL;
			$description   .= 'technical_level: ' . $data['qualification']['technical_level'] . PHP_EOL;
			$description   .= 'experience: ' . $data['qualification']['experience'] . ' years' . PHP_EOL;
			$description   .= 'accompanied: ' . $data['qualification']['accompanied'] . PHP_EOL;
			$description   .= 'english_level: ' . $data['qualification']['english_level'] . PHP_EOL;

			$description   .= 'analyse: ' . $data['qualification']['analyse'] . PHP_EOL;

			$qualification = [
				'title'			=> 'YBorder qualification',
				'company'		=> $data['cabinet']['name'] . ', ' . $data['cabinet']['place_name'],
				'description'	=> $description,
				'current'		=> true,
				'startDate'		=> date('Y-m'),
				'endDate'		=> date('Y-m', strtotime('+1 year')),
			];

			array_unshift($this->experience, $qualification);
		}

		$this->experience = array_filter($this->experience, function($item){
			return $item !== null;
		});

		if (false === $anonymize)
		{
			$this->web = [];

			if (true === isset($data['skype']))
				$this->web['skype'] = $data['skype'];
		}

		// $this->source = 'YBorder.com';
		/*
		"web": {
    "skype": "j.kowalski",
    "linkedin": "https://www.linkedin.com/in/jkowalski",
    "facebook": "https://www.facebook.com/jkowalski",
    "twitter": "jkowalski",
    "website": "http://jkowalski.com"
  },
		 */
	}

	public function toAPI()
	{
		$data = parent::toAPI();

	    // always set the name the user has in SM platform
	    // because the employee can edit this (and not us :/)
	    if (null !== $this->getAtsCandidateId())
	    {
		    $data['firstName'] 	= $this->sm->get('AtsCandidateTable')->getValue( $this->getAtsCandidateId(), 'firstName' );
		    $data['lastName'] 	= $this->sm->get('AtsCandidateTable')->getValue( $this->getAtsCandidateId(), 'lastName' );
		}

		return $data;
	}
}
