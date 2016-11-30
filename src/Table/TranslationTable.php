<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;

use Zend\Db\Sql\Expression;

class TranslationTable extends CoreTable
{
	public function getSupportedLocales()
	{
		$request = $this->select("translate_locales")->columns(array("locale"));
		$result = $this->execute($request);
		$locales = array_map(function($item){return $item["locale"];}, $result->toArray());
		return $locales;
		/*
		$request = $this->select()->columns(array(new Expression('DISTINCT(locale) as locale')));
		$result = $this->execute($request);
		$locales = array_map(function($item){return $item["locale"];}, $result->toArray());
		return $locales;*/
	}
	public function isSupportedLocale($locale)
	{
		 $request = $this->select("translate_locales")->where(array("locale"=>$locale))->limit(1);
		 $result = $this->execute($request);
		 $current = $result->current();
		 return $current === NULL?False:True;
	}
	public function getDistinctKeys()
	{
		$request = $this->select()->columns(array(new Expression('DISTINCT CONCAT(controller,".",action,".",`key`,IF(type IS NOT NULL,CONCAT("-",type),"")) as `key`')))->order(array("controller","action","key","type"));
		$result = $this->execute($request);
		$keys = array_map(function($item){return $item["key"];}, $result->toArray());
		return $keys;
	}

	public function getDistinct()
	{
		$request = $this->select()->columns(array(new Expression('DISTINCT CONCAT(controller,".",action,".",`key`,IF(type IS NOT NULL,CONCAT("-",type),"")) as `name`, controller, action, `key`,type ')))->order(array("controller","action","key","type"));
		$result = $this->execute($request);
		return $result->toArray();
	}
	public function getAll()
	{
		$request = $this->select()->order(array("controller","action","key","type"));
		$result = $this->execute($request);
		return $result->toArray();
	}
	public function getTranslation($controller, $action, $key, $locale)
	{
		$where = array(
				"controller" => $controller,
				"action" => $action,
				"key" => $key,
				"locale" => $locale
			);

		$request = $this->select()->where($where)->columns(array("singular","plurial","missing"))->limit(1);

		$result = $this->execute($request);
		if(($result = $result->current()) !== False)
		{
			return $result;
		}else
		{
			return NULL;
		}
	}
	private function getTranslationRow($controller, $action, $key, $locale, $type = NULL)
	{
		$where = array(
				"controller" => $controller,
				"action" => $action,
				"key" => $key,
				"locale" => $locale
			);
		if(isset($type))
		{
			$where["type"] = $type;
		}
		$request = $this->select()->where($where)->columns(array("singular","plurial","controller","action","key","type","locale","missing"))->limit(1);

		$result = $this->execute($request);
		if(($result = $result->current()) !== False)
		{
			return $result;
		}else
		{
			return NULL;
		}
	}
	public function removeKey($controller, $action, $key, $type)
	{
		if(trim($type) == "")
		{
			$type = NULL;
		}
		$data = array("controller"=>$controller, "action"=>$action,"key"=>$key, "type"=>$type);
		$this->table()->delete($data);
	}
	protected function getPartsFromKey($key)
	{
		$key = trim(mb_strtolower($key));
		$parts = explode(".", $key);
		$len = sizeof($parts);
		$last = $parts[$len-1];
		$index = mb_strpos($last, "-");
		$type = NULL;
		if($index !== False)
		{
			$type = mb_substr($last, $index+1);
			$parts[$len-1] = mb_substr($last, 0, $index);
		}
		return array("controller"=>$parts[0], "action"=>$parts[1], "key"=>implode(".",array_slice($parts, 2)), "type"=>$type);
	}
	public function renameKey($controller, $action, $key, $original, $type)
	{
		$original = $this->getPartsFromKey($original);
		if(trim($type) == "")
		{
			$type = NULL;
		}

		//dd($original);
		$data = array("controller"=>$controller, "action"=>$action,"key"=>$key, "type"=>$type, "id_user"=>$this->sm->get("identity")->user->id);

		$this->table()->update($data, $original);
	}
	public function updateTranslation($controller, $action, $key, $type, $locale, $singular, $plurial)
	{
		if(trim($plurial) == "")
		{
			$plurial = NULL;
		}
		if(trim($singular) == "")
		{
			$singular = NULL;
		}
		if(trim($type) == "")
		{
			$type = NULL;
		}
		$translation = $this->getTranslationRow($controller, $action, $key, $locale, $type);
		//dd($translation);
		$data = array("controller"=>$controller, "action"=>$action,"key"=>$key, "type"=>$type,"locale"=>$locale, "singular"=>$singular,"plurial"=>$plurial, "id_user"=>$this->sm->get("identity")->user->id);
		//jj($data);
		if(!isset($singular) && !isset($plurial))
		{
			//delete
			$this->table()->delete(array("controller"=>$controller, "action"=>$action,"key"=>$key, "type"=>$type,"locale"=>$locale));
			return;
		}

		if(isset($translation))
		{
			$data["missing"] = $translation["missing"];
			if(isset($singular) && $data["missing"] & 1)
			{
				$data["missing"] ^= 1;
			}
			if(isset($plurial) && $data["missing"] & 2)
			{
				$data["missing"] ^= 2;
			}
			//update
			$this->table()->update($data, array("controller"=>$controller, "action"=>$action,"key"=>$key, "type"=>$type,"locale"=>$locale));

		}else
		{
			//create
			$this->table()->insert($data);
		}
	}
	public function flagMissingTranslation($controller, $action, $key, $locale)
	{
		$data = array("controller"=>$controller, "action"=>$action, "key"=>$key,"locale"=>$locale);

		if($this->sm->get("identity")->isLoggued())
			$data["id_user"] = $this->sm->get("identity")->user->id;

		$type = null;
		$translation = $this->getTranslation($controller, $action, $key, $locale);

		if(isset($translation))
		{
			$data["missing"] = $translation["missing"]|1;
			$this->table()->update($data, array("controller"=>$controller, "action"=>$action,"key"=>$key, "type"=>$type,"locale"=>$locale));
		}else
		{
			$data["missing"] = 1;
			$this->table()->insert($data);
		}

	}
}
