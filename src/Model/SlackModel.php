<?php

namespace Core\Model;

use Core\Model\CoreModel;
class SlackModel
{
    public $name;
    public $channel;
    public $icon;
    public $attachments;
    public $text;


    public function __construct()
    {
        $this->attachments = [];
        $this->fields = [];
    }
    public function addAttachment($attachment)
    {
        if(is_array($attachment))
        {
            $slackAttachment = new SlackAttachment();
            $slackAttachment->exchangeArray($attachment);
            $attachment = $slackAttachment;
        }
        $this->attachments[] = $attachment;
    }
  
    public function getChannel()
    {
        return (mb_strpos($this->channel, '#') === false ? '#' : '') . $this->channel;
    }
    public function toSlackArray()
    {
        $data = array(
            'channel'     => $this->getChannel(),
            'username'    => $this->name,
            'text'        => $this->text,
            'icon_emoji'  => $this->icon,
            'attachments' => $this->attachments
        );
        return $data;
    }
    public function toSlackJSON()
    {
        return json_encode($this->toSlackArray());
    }
    public function isValid()
    {
        return isset($this->channel);
    }

}
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
    public function exchangeArray($data)
    {
        return parent::exchangeArray($data);
    }
}
