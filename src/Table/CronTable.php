<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 21/10/14
 * Time: 10:58
 */

namespace Core\Table;

use Core\Exception\FatalException;
use Core\Table\CoreTable;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Application\Model\CronModel;

class CronTable extends CoreTable
{
    const TABLE 	= 'cron';

    public function findByName( $name )
    {
    	$request = $this->select([ 'c' => self::TABLE ])
    				->where(['name' => (string) $name]);
        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;

        $cron = new CronModel();
        $cron->exchangeArray($data);

        return $cron;
    }

    public function findByNameAndUser( $name, $user_id )
    {
        $request = $this->select([ 'c' => self::TABLE ])
                    ->where(['name' => (string) $name, 'user_id' => (int) $user_id]);
        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;

        $cron = new CronModel();
        $cron->exchangeArray($data);

        return $cron;
    }

    public function findAll( $platform = 'cron' )
    {
        $request = $this->select([ 'c' => self::TABLE ])
                    ->where(['platform' => (string) $platform, 'user_id' => null]);

        $result = $this->execute($request);

        $data = $result->toArray();

        if (!$data) return null;

        return $data;
    }

    public function findAllUser( $platform = 'cron' )
    {
        $request = $this->select([ 'c' => self::TABLE ])
                    ->where(['platform' => (string) $platform]);

        $where = $request->where
                    ->isNotNull('user_id');

        $request->where( $where );

        $result = $this->execute($request);

        $data = $result->toArray();

        if (!$data) return [];

        return $data;
    }

    public function insertCron( $data )
    {
        if(!isset($data["directory"]))
        {
            $data["directory"] = ROOT_PATH."/";
        }
    	$insert = $this->table(self::TABLE)->insert($data);

        return $this->table(self::TABLE)->lastInsertValue;
    }

    public function updateCron( $cron_id, $data )
    {
        return $this->table(self::TABLE)->update($data, ['cron_id' => (int) $cron_id]);
    }
}
