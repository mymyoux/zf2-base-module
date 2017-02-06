<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 16:12
 */

namespace Core\Service\Cache;


use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
/**
 * Class CSV
 * @package Core\Service
 */
class Redis extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
	protected $_redis;
	protected $_data = [];
	protected function redis()
	{
		if(!isset($this->_redis))
		{
			$redis = $this->sm->get('AppConfig')->get('redis')
			;
			//TODO:if no redis => mimick redis
			$this->_redis = new \Redis();
			$this->_redis->pconnect($redis['ip'], $redis['port']);

		}
		return $this->_redis;
	}
    public function get($key)
    {
    	if(array_key_exists($key, $this->_data))
    	{
    		return $this->_data[$key];
    	}
    	$result = $this->redis()->get($key);
    	if($result)
    		$this->_data[$key] = $result;
    	return $result;
    }
    public function set($key, $value, $ttl = NULL)
    {
    	$this->_data[$key] = $value;
    	$this->redis()->set($key, $value, $ttl);
    }
    public function delete()
    {
    	$keys = [];
    	$len = func_num_args();
    	for($i=0,$sum=0;$i<$len;$i++) {
                $key = func_get_arg($i);
        		unset($this->_data[$key]);
        		$keys[] = $key;
        }
        return call_user_func_array([$this->redis(), "delete"], $keys);
    }
    public function del()
    {
    	$keys = [];
    	$len = func_num_args();
    	for($i=0,$sum=0;$i<$len;$i++) {
                $key = func_get_arg($i);
        		unset($this->_data[$key]);
        		$keys[] = $key;
        }
        return call_user_func_array([$this->redis(), "del"], $keys);
    }
    public function __call($name, $params)
    {
    	return call_user_func_array([$this->redis(), $name], $params);
    }
}
