<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 21/10/14
 * Time: 10:58
 */

namespace Core\Table\Cron;

use Core\Exception\FatalException;
use Core\Table\CoreTable;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Core\Model\Cron\LogModel;

/**
 * Class ShortlistTable - not used anymore
 * @package Application\Table
 * @deprecated
 */
class LogTable extends CoreTable
{
    const TABLE 	= 'cron_log';
    /**
     * @param $id_user
     * @return \Application\Model\CompanyModel
     */
    public function findByLogId( $log_id )
    {
        $request = $this->select([ 'c' => self::TABLE ])
                    ->where(['log_id' => (int) $log_id]);
        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;

        $log = new LogModel();
        $log->exchangeArray($data);

        return $log;
    }

    public function insertLog( $data )
    {
    	$insert = $this->table(self::TABLE)->insert($data);

        return $this->table(self::TABLE)->lastInsertValue;
    }

    public function updateLog( $log_id, $data )
    {
        return $this->table(self::TABLE)->update($data, ['log_id' => (int) $log_id]);
    }
}
