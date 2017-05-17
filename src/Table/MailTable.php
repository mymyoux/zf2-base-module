<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;


use Core\Model\UserModel;
use Zend\Db\Sql\Expression;

/**
 * Class MailTable
 * @package Core\Table
 */
class MailTable extends CoreTable
{

    const TABLE = "mail";
    const TABLE_WEBHOOK = "mail_webhook";
    const TABLE_WEBHOOK_GLOBAL = "mail_webhook_global";
    /**
     * Logs email to the database
     * @param string $type Email's type
     * @param \Core\Model\UserModel $recipient Recipient
     * @param string $html Email's content
     * @param string $recipient_email Email of recipient(s)
     * @param string $sender Email of sender
     * @param string $subject Email's subject
     */
    public function logEmail($type, $recipient, $html, $recipient_email, $sender, $subject)
    {
        $data = array(
            "type" => $type,
            "id_user"=> isset($recipient)?$recipient->id: 0,
            "subject" => $subject,
            "recipient" => $recipient_email,
            "sender" => $sender,
            "message" => $html,
            "from" => $this->sm->get("Identity")->isLoggued()?$this->sm->get("Identity")->user->id:0
        );
        $this->table()->insert($data);
        return $this->table()->lastInsertValue;
    }
    public function addWebhook($data)
    {
        if(isset($data["id_mandrill"]))
        {
            $this->table(MailTable::TABLE_WEBHOOK)->insert($data);
            if(isset($data["id_mandrill"]) && isset($data["type"]) && in_array($data["type"], ["unsub","spam","soft_bounce","hard_bounce","reject"]))
            {
                $id_user = $this->getIDUserFromIDMandrill($data["id_mandrill"]);
                if(!isset($id_user))
                {
                    return;
                }
                $user = $this->getUserTable()->getUser($id_user);
                if(!isset($user))
                {
                    return;
                }
                $this->getNotifications()->alert('unsubscribe_email', $data["type"], $user);

                $this->getUserTable()->addRole($user, "no_email");
            }
        }else
        {
            $this->table(MailTable::TABLE_WEBHOOK_GLOBAL)->insert($data);
        }
    }
    public function getIDUserFromIDMandrill($id_mandrill)
    {
        $result = $this->table()->select(array("id_mandrill"=>$id_mandrill));
        $result = $result->current();
        if($result === False)
        {
            return NULL;
        }
        if($result["id_user"]!=0)
        {
            return $result["id_user"];
        }
        if(!isset($result["recipient"]))
        {
            return NULL;
        }
        $users = $this->getUserTable()->getUsersFromEmail($result["recipient"]);
        if(!empty($users))
        {
            return $users[0]["id_user"];
        }
        return NULL;
    }
    public function getUserTable()
    {
        return $this->sm->get("UserTable");
    }
    public function getNotifications()
    {
        return $this->sm->get("Notifications");
    }
   public function getMails($user, $apirequest)
    {
      $request = $this->select(array("mail"=>MailTable::TABLE));
      if(isset($apirequest->params->since->value))
       {
        $request = $request->where($request->where->greaterThan("mail.created_time", new Expression("FROM_UNIXTIME(?)", $apirequest->params->since->value)));
       }
       if(isset($apirequest->params->until->value))
       {
        $request = $request->where($request->where->lessThan("mail.created_time", new Expression("FROM_UNIXTIME(?)", $apirequest->params->until->value)));
       }
       if(isset($apirequest->params->type->value))
       {
        $request = $request->where(array("mail.type"=>$apirequest->params->type->value));
       }
       $result = $this->execute($request);
       return $result->toArray();
    }
    public function updateMail($id, $data)
    {
        $this->table()->update($data, array("id"=>$id));
    }

    public function getMailByTypeAndUserAndGreaterThan( $type, $id_user, $date )
    {
        $where = $this->select(self::TABLE)->where
                    ->and->equalTo("id_user", (int) $id_user)
                    ->and->equalTo("type", (string) $type)
                    ->and->greaterThan('created_time', date('Y-m-d H:i:s', $date))
                    ;

        $request = $this->select([ 'tp' => self::TABLE ])
                    ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;
        return $data;
    }

    public function getByMandrill( $id_mandrill )
    {
        $where = $this->select(self::TABLE)->where
                    ->and->equalTo("id_mandrill", $id_mandrill)
                    ;

        $request = $this->select([ 'tp' => self::TABLE ])
                    ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;
        return $data;
    }

    public function getMailByTypeAndRecipient( $type, $email, $date = null )
    {
        $where = $this->select(self::TABLE)->where
                    ->and->equalTo("recipient", (string) $email)
                    ->and->equalTo("type", (string) $type)
                    ;
        if (null !== $date)
            $where->and->expression('DATE_FORMAT(tp.created_time, "%Y-%m-%d") = "' . date('Y-m-d', strtotime($date)) . '"', []);

        $request = $this->select([ 'tp' => self::TABLE ])
                    ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;
        return $data;
    }

    public function getMailByTypeAndUser( $type, $id_user, $date = null )
    {
        $where = $this->select(self::TABLE)->where
                    ->and->equalTo("id_user", (int) $id_user)
                    ->and->equalTo("type", (string) $type)
                    ;
        if (null !== $date)
            $where->and->expression('DATE_FORMAT(tp.created_time, "%Y-%m-%d") = "' . date('Y-m-d', strtotime($date)) . '"', []);

        $request = $this->select([ 'tp' => self::TABLE ])
                    ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;
        return $data;
    }

    public function mailIs( $id_mandrill, $action, $id_user )
    {
        $where = $this->select(self::TABLE_WEBHOOK)->where
                    ->and->equalTo("id_user", (int) $id_user)
                    ->and->equalTo("type", (string) $action)
                    ->and->equalTo("id_mandrill", $id_mandrill);

        $request = $this->select([ 'tp' => self::TABLE ])
                    ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return false;
        return true;
    }

    public function getMailByTypeAndEmail( $type, $email )
    {
        $where = $this->select(self::TABLE)->where
                    ->and->equalTo("recipient", (string) $email)
                    ->and->equalTo("type", (string) $type);

        $request = $this->select([ 'tp' => self::TABLE ])
                    ->where( $where );

        $result = $this->execute($request);
        $data = $result->current();
        if (!$data) return null;
        return $data;
    }

     public function hasAlreadySend( $type, array $emails )
    {
        $where = $this->select(self::TABLE)->where
                    ->and->in("recipient", $emails)
                    ->and->equalTo("type", (string) $type);

        $request = $this->select([ 'tp' => self::TABLE ])
                    ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return false;
        return true;
    }
}
