<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 11/10/2014
 * Time: 19:14
 */

namespace Core\Exception;

// when we need a new layout
class NewLayoutException extends \Exception
{
	public function getLayout()
	{
		return $this->getMessage();
	}
}
