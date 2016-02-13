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
    }

    public function getMailByTypeAndUser( $type, $id_user, $date )
    {
        $where = $this->select(self::TABLE)->where
                    ->and->equalTo("id_user", (int) $id_user)
                    ->and->equalTo("type", (string) $type)
                    ->and->expression('DATE_FORMAT(tp.created_time, "%Y-%m-%d") = "' . date('Y-m-d', strtotime($date)) . '"', []);

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
