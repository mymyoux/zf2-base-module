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
			$tabs = [];

			$tabs['Salary']   				= ': ' . $data['qualification']['salary'];
			$tabs['delay_availability']   	= ': ' . $data['qualification']['delay_availability'];
			$tabs['academic_level']   		= ': ' . $data['qualification']['academic_level'];
			$tabs['academic_reputation']   	= ': ' . $data['qualification']['academic_reputation'];
			$tabs['personal_skills']  		= ': ' . $data['qualification']['personal_skills'];
			$tabs['motivation']   			= ': ' . $data['qualification']['motivation'];
			$tabs['technical_level']   		= ': ' . $data['qualification']['technical_level'];
			$tabs['experience']   			= ': ' . $data['qualification']['experience'] . ' years';
			$tabs['accompanied']   			= ': ' . $data['qualification']['accompanied'];
			$tabs['english_level']  		= ': ' . $data['qualification']['english_level'];

			$max = 0;
			foreach ($tabs as $key => $tab)
			{
				if (mb_strlen($key) > $max) $max = mb_strlen($key);
			}

			foreach ($tabs as $key => $tab)
			{
           		$spaces     = str_repeat(' ', $max - mb_strlen($key) + 5 );
           		$tabs[$key] 		= $key . $spaces . $tab;
			}

			// 2 columns
			$max = 0;
			foreach ($tabs as $key => $tab)
			{
				if (mb_strlen($tab) > $max) $max = mb_strlen($tab);
			}

			$descriptions = [];
			reset($tabs);
		    while ($tab = current($tabs))
		    {
		    	$next = next($tabs);
           		$spaces     = str_repeat(' ', $max - mb_strlen($tab) + 10 );

		    	$descriptions[] = $tab . $spaces . $next;

		        next($tabs);
		    }

			$descriptions[] = '';
			$descriptions[] = $data['cabinet']['name'] . ': ' .$data['qualification']['analyse'];

			$description = implode(PHP_EOL, $descriptions);

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

		// echo ($description);
		// exit();
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
