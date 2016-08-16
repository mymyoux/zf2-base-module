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
 * Class AskTable
 * @package Core\Table
 */
class AskTable extends CoreTable
{

    const TABLE = "ask";
    const TABLE_TYPE = "ask_type";
   
   public function ask($data)
   {    
        $keys = array("type", "value","id_external_ask");
        $value = array();
        foreach($data as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v;
            }
        }

        $result = $this->table(AskTable::TABLE_TYPE)->select(array("type"=>$value["type"]));
        $result = $result->current();
        if($result === False)
        {
            $this->table(AskTable::TABLE_TYPE)->insert(array("type"=>$value["type"]));
            $id_type = $this->table(AskTable::TABLE_TYPE)->lastInsertValue;
        }else
        {
            $id_type = $result["id_ask_type"];
        }
        $value["id_ask_type"] = $id_type;
        unset($value["type"]);
        $this->table(AskTable::TABLE)->insert($value);
   }
   /**
     * @ghost\Param(name="type", required=true)
     * @ghost\Param(name="value", required=false)
     * @ghost\Param(name="id_external_ask", required=false)
     * @return JsonModel
     */
    public function askAPI($user, $apirequest)
   {    
        $keys = array("type", "value","id_external_ask");
        $value = array();
        foreach($apirequest->params as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v->value;
            }
        }
        return $this->ask($value);
   }
   public function answer($id_ask, $answer)
   {
        $keys = array("answer", "id_external_answer", "id_user");
        $value = array();
        foreach($answer as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v;
            }
        }
        $this->table(AskTable::TABLE)->update($answer, array("id_ask"=>$id_ask));
   }
   public function answerAPI($user, $apirequest)
   {

    
        $keys = array("answer", "id_external_answer", "id_user");
        $value = array();
        foreach($apirequest->params as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v->value;
            }
        }
        $value["id_user"] = $user->getRealID();
        $this->table(AskTable::TABLE)->update($value, array("id_ask"=>$apirequest->params->id_ask->value));

        $job = $this->sm->get('QueueService')->createJob('ask', array("id_ask"=>$apirequest->params->id_ask->value));
        $job->send();
   }
   public function getAllTypes($user, $apirequest)
   {
        $request = $this->select(array("ask"=>AskTable::TABLE_TYPE))
        ->columns(array("type"))
        ->group(array("type"));
        $result = $this->execute($request);
        return array_map(function($item)
            {
                return $item["type"];
            }, $result->toArray());
   }
   public function getAskByID($id_ask)
   {
        $request =  $this->select(array("ask"=>AskTable::TABLE))
        ->join(array("type"=>AskTable::TABLE_TYPE), "type.id_ask_type = ask.id_ask_type", array("type"))
        ->where(array("id_ask"=>$id_ask));
        $result = $this->execute($request);
        $result = $result->current();
        return $result === False?NULL:$result;
   }
   public function getAll($user, $apirequest)
   {
        $request = $this->select(array("ask"=>AskTable::TABLE))
        ->join(array("type"=>AskTable::TABLE_TYPE), "type.id_ask_type = ask.id_ask_type", array("type","request","array"));
        $where = NULL;
        if($apirequest->params->non_answered->value === True)
        {
            $where = $this->select()->where->isNull("answer")->and->isNull("id_external_answer");
        }
        if(isset($apirequest->params->type->value))
        {
            if(!isset($where))
            {
                $where = $this->select()->where;      
            }else
            {
                $where = $where->and;
            }
            $where = $where->equalTo("type", $apirequest->params->type->value);
        }
        if(isset($where))
        {
            $request = $request->where($where);
        }

        $request = $apirequest->paginate->apply($request, "ask");
        $result = $this->execute($request);

        return array_map(function($item)
            {
                if(isset($item["id_external_ask"]) && isset($item["request"]))
                {
                    $result = $this->execute(str_replace(":id",$item["id_external_ask"],$item["request"]));
                    $item["customdata"] = $item["array"] == 1 ? $result->toArray():$result->current();
                    if(empty($item["customdata"]) || $item["customdata"] === False)
                    {
                        unset($item["customdata"]);
                    }
                    $item["req"] = str_replace(":id",$item["id_external_ask"],$item["request"]);
                }
                unset($item["request"]);
                unset($item["array"]);
                return $item;
            }, $result->toArray());
   }
}
