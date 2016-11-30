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

/**
 * Environment Helper
 * Class Env
 * @package Core\Service
 */
class Env extends CoreService /*implements ServiceLocatorAwareInterface*/{

    public function isLocal()
    {
        if(!array_key_exists("HTTP_HOST", $_SERVER))
        {
            return False;
        }
        return mb_strpos($_SERVER['HTTP_HOST'], ".local")!==FALSE;
    }
}
