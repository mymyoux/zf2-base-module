<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;


use Zend\Mail\Transport\Sendmail;

use Zend\Mime\Part;
use Zend\Mvc\Service\ConfigFactory;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

use Zend\View\HelperPluginManager;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver\TemplatePathStack;
use Jlinn\Mandrill\Mandrill;
use Core\Struct\MandrillMessage;

/**
 * Email Helper
 * Class Email
 * @package Core\Service
 */
class Email extends CoreService implements ServiceLocatorAwareInterface{
    /**
     * Debug mode
     * @var boolean
     */
    private $debug          = NULL;
    private $async          = false;
    private $merge_language = null;

    public function setMergeLanguage( $merge_language )
    {
        $this->merge_language = $merge_language;
    }

    public function setAsync( $async )
    {
        $this->async = (bool) $async;
    }

    public function sendEmailTemplate($type, $template, $email, $email_sender = NULL, $sender = NULL, $subject = NULL, $data = NULL)
    {
        $this->checkDebug();

        $user = NULL;
        if($email instanceof \Core\Model\UserModel)
        {
            if(property_exists($email, "email"))
            {
                $user = $email;
                $email = $user->email;


            }
        }
        if(($email instanceof \ArrayObject || is_array($email)) && isset($email["email"]))
        {
            $email = $email["email"];
        }
        if(is_array($email))
        {
            $emails = $email;
        }else
        {
            $emails = array($email);
        }
        if(is_array($email_sender))
        {
            $data = $email_sender;
            unset($email_sender);
        }
        if(!isset($sender))
        {
            $sender = "YBorder";
        }
        if(!isset($email_sender))
        {
            $email_sender = "notifications@yborder.com";
        }

        $mandrill       = $this->createMandrill();
        $sender_hash    = null;
        $headers        = null;

        if (is_array($data) && isset($data['sender_hash']))
            $sender_hash = $data['sender_hash'];

        if (is_array($data) && isset($data['_headers']))
            $headers = $data['_headers'];

        if(!is_numeric_array($data))
        {
            $data = array_map(function($key, $value)
            {
                return array("name"=>$key, "content"=>$value);
            }, array_keys($data), $data);

        }

        $config = $this->sm->get("AppConfig")->getConfiguration();
        $self = $this->sm->get("Identity")->user;
        if(isset($self) && $self->isImpersonated() && isset( $config["local_notifications"]["email_admin"]) &&  $config["local_notifications"]["email_admin"] == True)
        {
            $config["local_notifications"]["email"] = $self->getRealUser()->email;
        }
        $original_emails = [];

        foreach($emails as $key=>$email)
        {
            if(($email instanceof \ArrayObject || is_array($email)) && isset($email["email"]))
            {
                $email = $email["email"];
            }
            if($email instanceof \Core\Model\UserModel)
            {

                if(property_exists($email, "email"))
                {
                    $email = $email->email;
                }
            }
            $original_emails[] = $email;
            if(!$this->debug)
            {
                $index_plus = mb_strpos($email, "+");
                if($index_plus !== False)
                {
                    $index_arobase =  mb_strrpos($email, "@");
                    if($index_arobase !== False)
                    {
                        $email = mb_substr($email, 0, $index_plus).mb_substr($email,$index_arobase);
                    }
                }
                if(isset($config["local_notifications"]["email"]))
                {
                    $email = $config["local_notifications"]["email"];
                }
            }
            $emails[$key] = $email;
        }

        //local redirection

        /*if(isset($config["local_notifications"]["email"]))
        {
            if(is_array($email))
            {

                $email = $config["local_notifications"]["email"];
            }
        }*/
        $to = array(

        );
        $i = 0;
        foreach($emails as $email)
        {
           $to[] = array("email"=>$email,
               // "name"=>isset($user)?$user:$email,
                "type"=>$i==0?"to":"cc"
            );
            $i++;
        }
        $message = new MandrillMessage();

        $message->from_email    = $email_sender;
        $message->from_name     = $sender;
        $message->auto_text     = true;
        $message->important     = false;
        $message->track_opens   = true;
        $message->track_clicks  = true;

        if (null !== $sender_hash)
        {
            list($email_sender_name, $email_sender_domain) = explode('@', $email_sender);

            $email_sender = $email_sender_name . '+' . $sender_hash . '@' . $email_sender_domain;
        }

        $message->headers       = [
            'Reply-To' => $email_sender
        ];

        if (null !== $headers)
            $message->headers += $headers;

        $message->to                = $to;
        $message->global_merge_vars = $data;

        if (null !== $this->merge_language)
            $message->merge_language    = $this->merge_language;

        if (true === is_array($type))
            $message->setTags( $type );
        else
            $message->setTags( [$type] );

        if(isset($subject))
            $message->subject = $subject;

        $this->log($type, $user, $template, $original_emails, $sender, $subject);

        $result = $mandrill->messages()->sendTemplate($template, $message, [], $this->async);

        return array("data"=>$data,"result"=>$result,"message"=>$message);
    }
    /**
     * Sends an email
     * @param string $type Type of mail for database log
     * @param string $template Template email
     * @param string|\Core\Model\UserModel $email email's address
     * @param string $sender email's sender
     * @param string $subject email's subject
     * @param array|null $data Data to pass to the template
     * @throws \Exceptions
     */
    public function sendEmail($type, $template, $email, $email_sender, $sender, $subject, $data = NULL)
    {

        $this->checkDebug();

        $config = $this->sm->get("AppConfig")->getConfiguration();
        if(is_array($template))
        {
            $config['view_manager']['template_path_stack'][] = $template["path"];
            $template = $template["template"];
        }

        //template
        $messageView = new ViewModel($data);
        $messageView->setTemplate($template);
        $messageView->setTerminal(true);



        $renderer = $this->getMessageBodyRenderer();
        $renderer->setHelperPluginManager($this->sm->get("viewhelpermanager"));

        $html = $renderer
            ->setResolver(new TemplatePathStack(array(
                'script_paths' => $config['view_manager']['template_path_stack']
            )))->render($messageView);



        //$this->getMessageBodyRenderer()->setHelperPluginManager($this->sm->get("Viewhelper  "))



        $user = NULL;
        if($email instanceof \Core\Model\UserModel)
        {
            if(property_exists($email, "email"))
            {
                $user = $email;
                $email = $user->email;


            }
        }
        if(($email instanceof \ArrayObject || is_array($email)) && isset($email["email"]))
        {
            $email = $email["email"];
        }
        if(is_array($email))
        {
            $emails = $email;
        }else
        {
            $emails = array($email);
        }



        //local redirection
        $self = $this->sm->get("Identity")->user;
        if(isset($self) && $self->isImpersonated() && isset( $config["local_notifications"]["email_admin"]) &&  $config["local_notifications"]["email_admin"] == True)
        {
            $config["local_notifications"]["email"] = $self->getRealUser()->email;
        }

        $original_emails = [];
        foreach($emails as $key=>$email)
        {
            if(($email instanceof \ArrayObject || is_array($email)) && isset($email["email"]))
            {
                $email = $email["email"];
            }
            if($email instanceof \Core\Model\UserModel)
            {

                if(property_exists($email, "email"))
                {
                    $email = $email->email;
                }
            }

            $original_emails[] = $email;
            if(!$this->debug)
            {
                $index_plus = mb_strpos($email, "+");
                if($index_plus !== False)
                {
                    $index_arobase =  mb_strrpos($email, "@");
                    if($index_arobase !== False)
                    {
                        $email = mb_substr($email, 0, $index_plus).mb_substr($email,$index_arobase);
                    }
                }
                if(isset($config["local_notifications"]["email"]))
                {
                    $email = $config["local_notifications"]["email"];
                }
            }
            $emails[$key] = $email;
        }

        //local redirection

        /*if(isset($config["local_notifications"]["email"]))
        {
            if(is_array($email))
            {

                $email = $config["local_notifications"]["email"];
            }
        }*/
        if(($email instanceof \ArrayObject || is_array($email)) && isset($email["email"]))
        {
            $email = $email["email"];
        }
        $to = array(

        );
        $i = 0;
        foreach($emails as $email)
        {

            $to[] = array("email"=>$email,
                "type"=>$i==0?"to":"cc"
            );
            $i++;
        }


        if(!$this->debug && isset($config["local_notifications"]["email"]))
        {
            $email = $config["local_notifications"]["email"];
        }
        $mandrill = $this->createMandrill();
        $message = new Message();

        $message->html          = $html;
        $message->from_email    = $email_sender;
        $message->from_name     = $sender;
        $message->subject       = $subject;
        $message->auto_text     = true;
        $message->important     = false;
        $message->track_opens   = true;
        $message->track_clicks  = true;
        $message->headers       = [
            'Reply-To' => $email_sender
        ];
        $message->to            = $to;
        if (true === is_array($type))
            $message->setTags( $type );
        else
            $message->setTags( [$type] );

        $this->log($type, $user, $html, $original_emails, $sender, $subject);

        $mandrill->messages()->send($message, False);
    }
    public function isEmailSent()
    {
        $this->checkDebug();
        $configuration = $this->sm->get("AppConfig")->getConfiguration();
        return !$this->debug && !isset($configuration["local_notifications"]["email"]);
    }
    protected function checkDebug()
    {
        if(!isset($this->debug))
        {
            $this->debug  = False;
            $configuration =  $this->sm->get("AppConfig")->getConfiguration();
            //not debug - not prod - not email
            if(!$this->sm->get("AppConfig")->isProduction())
            {
                if(!isset($configuration["local_notifications"]["email"]))
                {
                    $this->debug = True;
                }
            }
        }
    }
    private function createMandrill()
    {
        $mandrill_configuration = $this->sm->get("AppConfig")->get("mandrill");
        $this->checkDebug();

        $api_key = (true === $this->debug ? $mandrill_configuration["test_api_key"] : $mandrill_configuration["api_key"]);

        return new Mandrill( $api_key );
    }

    /**
     * Logs email to the database
     * @param string $type Email's type
     * @param \Core\Model\UserModel $recipient Recipient
     * @param string $html Email's content
     * @param string $emails Email of recipient(s)
     * @param string $sender Email of sender
     * @param string $subject Email's subject
     */
    protected function log($type, $recipient, $html, array $emails, $sender, $subject = NULL)
    {
        // do not insert if it's in debug mode
        if (true === $this->debug) return false;

        if(!isset($subject))
        {
            $subject = "by_default";
        }
        if (true === is_array($type)) $type = implode('-', $type);

        foreach ($emails as $email)
            $this->getMailTable()->logEmail($type, $recipient, $html, $email, $sender, $subject);
    }

    /**
     * @return \Core\Table\MailTable
     */
    protected function getMailTable()
    {
        return $this->sm->get("MailTable");
    }
    /***
     * PHP Renderer used to build the template
     * @return PhpRenderer
     */
    public function getMessageBodyRenderer()
    {
        return new PhpRenderer();
    }

    public function setDebug( $debug )
    {
        $this->debug = (bool) $debug;
    }
}
