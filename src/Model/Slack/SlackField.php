<?php

namespace Core\Model\Slack;

use Core\Model\CoreModel;

class SlackField extends CoreModel
{
    /**
     * Title
     * @var string
     */
    public $title;
    /**
     * Value 
     * @var string
     */
    public $value;
    /**
     * use short display
     * @var boolean
     */
    public $short;
    public function __construct()
    {
        $this->short = true;
    }
    public function exchangeArray($data)
    {
        return parent::exchangeArray($data);
    }
}
