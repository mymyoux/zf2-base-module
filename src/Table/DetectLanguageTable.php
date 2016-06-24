<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;


use Zend\Db\Sql\Expression;

/**
 * Class AskTable
 * @package Core\Table
 */
class DetectLanguageTable extends CoreTable
{

    const TABLE = "lang_detection";
   
   public function getTodayUsage($apikey)
   {
        $today = date("Y-m-d");
        $result = $this->table(DetectLanguageTable::TABLE)->select(array("date"=>$today,"apikey"=>$apikey));
        $result = $result->current();
        if($result !== False)
        {
            return $result;
        }
        $this->table(DetectLanguageTable::TABLE)->insert(array("date"=>$today,"apikey"=>$apikey));
        return $this->getTodayUsage($apikey);
   }
   public function addCall($apikey, $len)
   {
        $this->getTodayUsage($apikey);
        $today = date("Y-m-d");
        $request = $this->update(DetectLanguageTable::TABLE)->set(array("used_calls"=>new Expression("used_calls + 1"),"used_bytes"=>new Expression("used_bytes + ?", $len)))->where(array("apikey"=>$apikey,"date"=>$today));
        $this->execute($request);
   }
}
