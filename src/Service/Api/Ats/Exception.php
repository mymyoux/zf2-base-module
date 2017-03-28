<?php

namespace Core\Service\Api\Ats;

class Exception extends \Exception
{
	public $is_permission_error = false;

    public function setPermissionError( $boolean )
    {
        $this->is_permission_error = $boolean;

        return $this;
    }

    public function isPermissionError()
    {
        return $this->is_permission_error;
    }
}
