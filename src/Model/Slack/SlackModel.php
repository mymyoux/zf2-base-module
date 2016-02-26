<?php

namespace Core\Model\Slack;

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
