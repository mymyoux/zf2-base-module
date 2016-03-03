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
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
class StatsTable extends CoreTable
{
    const TABLE_API_CALL = "stats_api_call";
   
    public function recordAPICall($call)
    {
        $this->table(StatsTable::TABLE_API_CALL)->insert($call);
    }
}
