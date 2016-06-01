<?php

namespace Core\Model\Ats\Api;
use Core\Model\CoreModel;

class ResultListModel extends CoreModel
{
	public $count = 0;
	public $content = [];

	public function setTotalFound( $count )
	{
		$this->count = $count;

		return $this;
	}

	public function setContent( $content )
	{
		$this->content = $content;

		return $this;
	}

	public function getTotalFound()
	{
		return $this->count;
	}

	public function getContent()
	{
		return $this->content;
	}
}
