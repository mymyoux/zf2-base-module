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
    const TABLE_CALL = "lang_detection_call";
    const TABLE_HASH = "detect_language";

    public function insertTranslate( $data )
    {
        $hash   = $this->getHash( $data['text'] );

        if (null === $this->findByHash($hash))
        {
            $data['hash'] = $hash;
            $this->table(self::TABLE_HASH)->insert($data);

            return $this->table(self::TABLE_HASH)->lastInsertValue;
        }

        return null;
    }

    public function findByHash( $hash )
    {
        $request = $this    ->select([ 'a' => self::TABLE_HASH ])
                             ->where([
                                'hash' => (string) $hash
                            ]);

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;

        return $data;
    }

    public function getHash( $text )
    {
        $text          = strip_tags($text);
        $hash          = sha1($text);

        return $hash;
    }


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
   public function addCall($apikey, $len, $text)
   {
        $this->getTodayUsage($apikey);
        $today = date("Y-m-d");
        $request = $this->update(DetectLanguageTable::TABLE)->set(array("used_calls"=>new Expression("used_calls + 1"),"used_bytes"=>new Expression("used_bytes + ?", $len)))->where(array("apikey"=>$apikey,"date"=>$today));
        $this->execute($request);

        // insert call

        try
        {
          throw new \Exception("Error Processing Request", 1);

        }
        catch (\Exception $e)
        {
          $data = [
            'text'    => $text,
            'length'  => $len,
            'apikey'  => $apikey,
            'trace'   => $e->getTraceAsString(),
          ];
          $this->table(DetectLanguageTable::TABLE_CALL)->insert($data);
        }
   }
}
