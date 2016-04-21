<?php

namespace Core\Model\Ats;

interface AbstractJobModel
{
	public function getName();
	public function getDescription();
	public function isPublic();
	public function hasAlert();
	public function getToken();
}
