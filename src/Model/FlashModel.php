<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 13/11/14
 * Time: 16:18
 */

namespace Core\Model;


class FlashModel extends CoreModel{

    const TYPE_MESSAGE = "message";
    const TYPE_WARNING = "warning";
    const TYPE_ERROR = "error";
    public $text;
    public $type;
    public function __construct($text = NULL, $type = FlashModel::TYPE_MESSAGE)
    {
        $this->text = $text;
        $this->type = $type;
    }
}