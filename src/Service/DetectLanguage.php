<?php

namespace Core\Service;

use Zend\Http\Request;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use DetectLanguage\DetectLanguage as DetectLanguageLibrary;

class DetectLanguage extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    CONST SIZE = 150;

    public static $plans = [
        "free"=>["requests"=>5000,"bytes"=>1048576],
        "basic"=>["requests"=>100000,"bytes"=>20971520],
        "plus"=>["requests"=>1000000,"bytes"=>209715200],
        "premium"=>["requests"=>10000000,"bytes"=>2147483648],
        "premium+"=>["requests"=>40000000,"bytes"=>8589934592],
    ];
    private $api;
    protected $plan;
    protected $key;
    public function __construct()
    {

    }

    protected function init()
    {
        $config = $this->sm->get('AppConfig')->get('detectlanguage');
        if(!isset($config) || !isset($config["key"]))
        {
            throw new \Exception("you need to specify an api key for detectlanguage before using it");
        }
        DetectLanguageLibrary::setSecure( true );
        DetectLanguageLibrary::setApiKey( $config['key'] );
        $this->key = $config['key'];
        $plan = isset($config["plan"])?$config["plan"]:"free";
        if(!isset(DetectLanguage::$plans[$plan]))
        {
            $plan = "free";
        }
        $this->plan = DetectLanguage::$plans[$plan];
    }
    protected function __getDetection($text)
    {
        if($this->sm->get("AppConfig")->isLocal())
        {
            return [$this->_getDefaultLang($text, 'local')];
        }
        if(mb_strlen($text) == 0)
        {
            return [$this->_getDefaultLang($text)];
        }

        $hash = $this->getDetectLanguageTable()->getHash( $text );
        $db_result = $this->getDetectLanguageTable()->findByHash( $hash );

        if ($db_result)
        {
            return [$this->formatFromDB( $db_result )];
        }
        $this->getDetectLanguageTable()->addCall($this->key, mb_strlen($text), $text);
        
        try
        {
            $detections = DetectLanguageLibrary::detect( $text );
        }
        catch (\Exception $e)
        {
            $detect = $this->_getDefaultLang($text, 'exception');

            $this->sm->get('ErrorTable')->logError( $e );

            $this->getDetectLanguageTable()->insertTranslate([
                'text'          => $text,
                'lang'          => $detect->language,
                'is_reliable'   => $detect->isReliable,
                'fake'          => isset($detect->fake) ? $detect->fake : false,
                'len'           => $detect->len,
                'confidence'    => $detect->confidence
            ]);

            return [$detect];
        }

        $langs = [];

        foreach($detections as $key=>$detect)
        {
            $detections[$key]->len =  mb_strlen($text);
            $langs[] = $detect->language;

            $this->getDetectLanguageTable()->insertTranslate([
                'text'          => $text,
                'lang'          => $langs[0],
                'is_reliable'   => $detect->isReliable,
                'fake'          => isset($detect->fake) ? $detect->fake : false,
                'len'           => $detect->len,
                'confidence'    => $detect->confidence
            ]);
        }


        return $detections;
    }

    protected function formatFromDB($db)
    {
        $lang = new \StdClass();
        $lang->language = $db['lang'];
        $lang->isReliable = true;
        $lang->confidence = 10.0;
        $lang->fake = true;
        $lang->len = mb_strlen($db['text']);
        $lang->from = $db['fake'] ? 'exception' : 'default';//$db['from'];
        return $lang;
    }

    protected function _getDefaultLang($text, $from = 'default')
    {
        $this->getNotificationManager()->alert("detect_language", "used default language");
        //TODO:alert
        $lang = new \StdClass();
        $lang->language = "en";
        $lang->isReliable = true;
        $lang->confidence = 10.0;
        $lang->fake = true;
        $lang->len = mb_strlen($text);
        $lang->from = $from;
        return $lang;
    }
    protected function _detect($text, $smart)
    {
        $this->sm->get('Log')->normal($text);
        $usage = $this->getDetectLanguageTable()->getTodayUsage($this->key);
        $remaining_calls = $this->plan["requests"] - (int)$usage["used_calls"];
        $remaining_bytes = $this->plan["bytes"] - (int)$usage["used_bytes"];

        $this->sm->get('Log')->normal('remaining_calls:' . $remaining_calls);
        $this->sm->get('Log')->normal('remaining_bytes:' . $remaining_bytes);
        
        if($remaining_calls % 100 === 0)
        {
            $this->getNotificationManager()->alert("detect_language", $remaining_calls." remaining calls - ".intval($remaining_bytes/1024)." ko");
        }
        // $size_batch = $remaining_calls>0?$remaining_bytes/$remaining_calls:0;
        $size_batch = $remaining_calls>0 ? self::SIZE : 0;
        $this->sm->get('Log')->normal('size_batch:' . $size_batch);
        if($size_batch<= 0 )
        {

            return [$this->_getDefaultLang($text)];
        }


        if(!$smart)
        {
            if(strlen($text)>$remaining_bytes)
            {
                $text = substr($text, 0, $remaining_bytes);
                while(!mb_check_encoding($text) && strlen($text)>0)
                {
                    $text = substr($text, 0, -1);
                }

                $position = mb_strrpos($text, " ");
                if($position !== False)
                {
                    $text = mb_substr($text, 0, $position);
                }
            }

            return $this->__getDetection($text);
        }


        $count = 1;
        do
        {
            $max_size = self::SIZE;//intval($remaining_bytes/$remaining_calls);
            $this->sm->get('Log')->normal('max_size:' . $max_size);
            
            // $size = ceil($max_size*$count*3/4);
            $size = ceil($max_size*$count);
            $this->sm->get('Log')->normal('size:' . $size);
            if($size>= strlen($text))
            {
                $this->sm->get('Log')->normal('1/');
                $cutText = $text;
            }else
            {
                $this->sm->get('Log')->normal('2/');
                $position = mb_strrpos($text, " ", -(mb_strlen($text) - $size));
                if($position === False)
                {
                    $this->sm->get('Log')->normal('3/');
                    $position = $size;
                }
                $cutText = mb_substr($text, 0, $position);
            }
            if(strlen($cutText)>$remaining_bytes)
            {
                $this->sm->get('Log')->normal('4/');
                $cutText = substr($cutText, 0, $remaining_bytes);
                while(!mb_check_encoding($cutText) && strlen($cutText)>0)
                {
                 $this->sm->get('Log')->normal('5/');
                    $cutText = substr($cutText, 0, -1);
                }

                $position = mb_strrpos($cutText, " ");
                if($position !== False)
                {
                    $this->sm->get('Log')->normal('6/');
                    $cutText = mb_substr($cutText, 0, $position);
                }
            }


            $this->sm->get('Log')->normal($cutText);

            //TODO increase the size part by part and save the total calls
            $languages = $this->__getDetection( $cutText );
            if(!empty($languages))
            {
                $lang = $languages[0];
                var_dump($lang);
                if (isset($lang->from) && $lang->from === 'exception' && $lang->fake)
                {
                    $this->sm->get('Log')->normal('continue exception fake');
                    // do nothing here
                }   
                else
                {
                     if($lang->isReliable)
                    {
                        return $languages;
                    }
                }                 
            }
            $remaining_bytes-= strlen($cutText);
            $remaining_calls--;
        }while($remaining_calls>0 && $remaining_bytes>0 && $size<strlen($cutText));
        return [$this->_getDefaultLang($text)];
    }
    public function simpleDetect( $text, $smart = True )
    {
        if (true === empty($text)) return null;
            $detections = $this->_detect($text, $smart);

        if (count($detections) > 0)
          return $detections[0]->language;
        else
          return null;
    }
    public function detect($text, $smart = True)
    {
        if (true === empty($text)) return null;

        return $this->_detect( $text, $smart );
    }
    public function detectOne($text, $smart = True)
    {
        if (true === empty($text)) return null;

        $langs = $this->_detect( $text, $smart );
        if(!empty($langs))
        {
            return $langs[0];
        }
        return NULL;
    }
    protected function getDetectLanguageTable()
    {
        return $this->sm->get("DetectLanguageTable");
    }
    protected function getNotificationManager()
    {
        return $this->sm->get("Notifications");
    }
}
