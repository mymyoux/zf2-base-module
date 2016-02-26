<?php

namespace Core\Model\Slack;

use Core\Model\CoreModel;
class SlackAttachment extends CoreModel
{
    public $fallback;
    public $color;
    public $pretext;
    public $author_name;
    public $author_link;
    public $author_icon;
    public $title;
    public $title_link;
    public $text;
    public $fields;
    public $image_url;
    public $thumb_url;
    public $mrkdwn_in;

    public function __construct()
    {
        $this->fields = [];
        $this->mrkdwn_in = ["text","pretext", "title"];
    }
    public function addField($field)
    {
        if(is_array($field))
        {
            $slackField = new SlackField();
            $slackField->exchangeArray($field);
            $field = $slackField;
        }
        $this->fields[] = $field;
    }
    public function exchangeArray($data)
    {
        if(isset($data["fields"]))
        {
            foreach($data["fields"] as $field)
            {
                $this->addField($field);
            }
            unset($data["fields"]);
        }
        return parent::exchangeArray($data);
    }
}
