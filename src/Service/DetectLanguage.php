<?php

namespace Core\Service;

use Zend\Http\Request;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use DetectLanguage\DetectLanguage as DetectLanguageLibrary;

class DetectLanguage extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    private $api;

    public function __construct()
    {

    }

    protected function init()
    {
        $config = $this->sm->get('AppConfig')->get('detectlanguage');

        DetectLanguageLibrary::setSecure( true );
        DetectLanguageLibrary::setApiKey( $config['key'] );
    }

    public function simpleDetect( $text )
    {
        if (true === empty($text)) return null;

        return DetectLanguageLibrary::simpleDetect( $text );
    }
}
