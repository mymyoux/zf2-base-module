<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;


use Zend\Mail\Transport\Sendmail;

use Zend\Mime\Part;
use Zend\Mvc\Service\ConfigFactory;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

use Zend\View\HelperPluginManager;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver\TemplatePathStack;
use Jlinn\Mandrill\Mandrill;
use Jlinn\Mandrill\Struct\Message;

/**
 * Configuration Helper
 * @package Core\Service
 */
class Configuration extends CoreService ////implements ServiceLocatorAwareInterface
{
    protected $_configuration;
    protected $_env;
    protected function _getInitialConfiguration()
    {
        $configuration = $this->sm->get("Configuration");
        $this->_env = isset($configuration["env"])?$configuration['env']:'local';


        $json = join_paths(ROOT_PATH,'config/autoload','global.*.json');
        foreach (glob($json, \GLOB_NOSORT) as $filename) {
            $content = file_get_contents($filename);
            try
            {   $category = substr(basename($filename,".json"), strlen($this->_env)+1);
                $content = json_decode($content, True);
                $configuration = array_merge(array($category=>$content), $configuration);
            }catch(\Exception $e)
            {
                throw $e;
            }
        }

        $json = join_paths(ROOT_PATH,'config/autoload',$this->_env.'.*.json');
        foreach (glob($json, \GLOB_NOSORT) as $filename) {
            $content = file_get_contents($filename);
            try
            {   $category = substr(basename($filename,".json"), strlen($this->_env)+1);
                $content = json_decode($content, True);
                $configuration = array_merge(array($category=>$content), $configuration);
            }catch(\Exception $e)
            {
                throw $e;
            }
        }

        if(file_exists($json))
        {
            $content = file_get_contents($json);
            try
            {
                $content = json_decode($content, True);
                $configuration = array_merge($content, $configuration);
            }catch(\Exception $e)
            {
                throw $e;
            }


        }
        return $configuration;
    }
    public function getEnv()
    {
         if(!isset($this->_configuration))
        {
            $this->_configuration = $this->_getInitialConfiguration();
        }
        return $this->_env;
    }
    public function getConfiguration()
    {
        if(!isset($this->_configuration))
        {
            $this->_configuration = $this->_getInitialConfiguration();
        }
        return $this->_configuration;
    }
    public function getConfigurationFront($categories)
    {
        $conf_front =array();
        $configuration = $this->getConfiguration();
        foreach($categories as $category)
        {
            if($this->has($category))
            {
                  $conf_front = array_merge($conf_front, $this->get($category));
            }
        }
        return $conf_front;
    }
    public function has($category)
    {
        $configuration = $this->getConfiguration();
        return isset($configuration[$category]);
    }
    public function get($category)
    {
         $configuration = $this->getConfiguration();
        return isset($configuration[$category])?$configuration[$category]:NULL;
    }
    public function isCLI()
    {
        return (php_sapi_name() === 'cli');
    }
    /**
     * Check if current env is not Prod
     * @return boolean [description]
     */
    public function isLocal()
    {
        $configuration = $this->getConfiguration();
        return $this->_env != "prod";
    }
    /**
     * Check if current env is Really prod
     * @return boolean [description]
     */
    public function isProduction()
    {
        $configuration = $this->getConfiguration();
        return $this->_env == "prod";
    }

    /**
     * Check if current env is Really alpha
     * @return boolean [description]
     */
    public function isAlpha()
    {
        $configuration = $this->getConfiguration();
        return $this->_env == "alpha";
    }
}
