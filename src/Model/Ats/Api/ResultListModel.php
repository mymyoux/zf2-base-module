<?php

namespace Core\Model\Ats\Api;
use Core\Model\CoreModel;

class ResultListModel extends CoreModel
{
	public $count 	= 0;
	public $content = [];
	public $offset 	= null;

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

	public function setOffset( $offset )
	{
		$this->offset = $offset;
	}

	public function getTotalFound()
	{
		return $this->count;
	}

	public function getContent()
	{
		return $this->content;
	}

	public function getOffset()
	{
		return $this->offset;
	}
}
