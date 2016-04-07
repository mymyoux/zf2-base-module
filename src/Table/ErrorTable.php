<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;

use Zend\Db\Sql\Select;
class ErrorTable extends CoreTable
{
    const TABLE = "error";
    const TABLE_JAVASCRIPT = "error_javascript";

    private $loggued;
    public function logError(\Exception $exception)
    {
        if(!isset($this->loggued))
        {
            $this->loggued = array();
        }
        if(in_array($exception, $this->loggued, True))
        {
           return;
        }
        $this->loggued[] = $exception;
        $info = array();
        $info["type"] = get_class($exception);
        $info["code"] = $exception->getCode();
        $info["message"] = $exception->getMessage();
        $info["file"] = $exception->getFile();
        $info["line"] = $exception->getLine();
        $info["stack"] = $exception->getTraceAsString();

        if (php_sapi_name() == "cli")
        {
            $info["url"] = "php console ".implode(" ", $this->getConsoleParams());
        }else
        {
            $info["url"] = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        }
        try
        {
            if(isset($_GET))
            {
                $info["get"] = json_encode($_GET);
            }

        }catch(\Exception $e)
        {
            $info["get"] = "error";
        }
        try
        {
            if(isset($_POST))
            {
                $info["post"] = json_encode($_POST);
            }

        }catch(\Exception $e)
        {
            $info["post"] = "error";
        }

        $info["id_user"] = 0;

        try
        {
            /**
             * @var \Core\Service\Identity $identity
             */
            $identity = $this->sm->get("Identity");
            if($identity->isLoggued())
            {
                $info["id_user"] = $identity->user->id;
                $info["id_real_user"] = $identity->user->getRealId();
            }
        }catch(\Exception $e)
        {

        }
        $this->table()->insert($info);


        try
        {
            if($this->sm->get("Identity")->isLoggued())
            {
                $info["user"] = $this->sm->get("Identity")->user;
            }
            $info["id_error"] = $this->table()->lastInsertValue;
            $this->sm->get("Notifications")->sendError($info);
        }catch(\Exception $e)
        {

        }
        return $this->table()->lastInsertValue;
    }

    public function getJSErrorNotCleaned()
    {
        $where = $this->select()->where->isNull("error_stack_clean")->and->nest->isNotNull("error_stack")->or->isNotNull("error_url")->unnest;
        $request = $this->select(array("error"=>ErrorTable::TABLE_JAVASCRIPT))
        ->join(array("user"=>UserTable::TABLE), "user.id_user = error.id_user",array("first_name","last_name","email"),Select::JOIN_LEFT)
        ->where($where);
        $result = $this->execute($request);
        return $result->toArray();
    }
    public function updateError($id_error, $data)
    {
        $this->table(ErrorTable::TABLE_JAVASCRIPT)->update($data, array("id_error"=>$id_error));
    }
    protected function getConsoleParams()
    {
        $arguments = $this->sm->get("request")->getParams()->toArray();
        $params = [];
        foreach($arguments as $key=>$value)
        {
            if(is_numeric($key))
            {
                $params[] = $value;
            }
        }
        return $params;
    }


    public function logJSError($data, $hardware = NULL, $error = NULL)
    {

        $keys = array("id_user","session","error_name","error_message","error_url","error_line","error_column","error_stack","hardware_cordovaVersion",
           "hardware_os","hardware_uuid","hardware_osVersion","hardware_android","hardware_blackberry","hardware_ios","hardware_mobile","hardware_windowsPhone",
           "hardware_screenWidth","hardware_screenHeight","hardware_landscape","hardware_portrait","hardware_browser", 'hardware_cookie' ,"url","type");

        if(!isset($hardware))
        {
            $notin = $this->select()->where->IsNotNull("hardware_os");
             $results = $this->table(ErrorTable::TABLE_JAVASCRIPT)->select(array("session"=>$data["session"],$notin));
             $result = $results->current();
             if($result !== False)
             {
                $hardware = array();
                foreach($result as $key=>$value)
                {
                    if(mb_substr($key, 0, 9) == "hardware_")
                    {
                        $hardware[mb_substr($key,9)] = $value;
                    }
                }
             }
        }
        if(isset($hardware))
        {
            foreach($hardware as $key=>$value)
            {
                $data["hardware_".$key] = $value;
            }
        }
        if(isset($error))
        {
            foreach($error as $key=>$value)
            {
                $data["error_".$key] = $value;
            }
        }

        $values = array();
        foreach($keys as $key)
        {
            if(isset($data[$key]))
            {
                $values[$key] = $data[$key];
            }
        }
        $this->table(ErrorTable::TABLE_JAVASCRIPT)->insert($values);
    }
    public function getError($id_error)
    {
        $result = $this->table(ErrorTable::TABLE)->select(array("id"=>$id_error));
        $result = $result->current();
        if($result === False)
        {
            return NULL;
        }
        return $result;
    }
}

