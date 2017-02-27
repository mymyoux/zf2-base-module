<?php

namespace Core\Service;

use Zend\Http\Request;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class GTranslate extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    private $api;
    protected $key;
    protected $active = false;
    protected $use_db = false;

    public function __construct()
    {
        $this->client   = new \GuzzleHttp\Client();
    }

    protected function init()
    {
        $config         = $this->sm->get('AppConfig')->get('google_translate');
        $this->key      = $config['key'];

        if (isset($config['is_active']))
            $this->active   = $config['is_active'];
        if (isset($config['use_db']))
            $this->use_db   = $config['use_db'];
    }

    public function isActive()
    {
        return $this->active;
    }

    public function translate( $text, $source = null)
    {
        if ($source === 'en')
            return ['en', $text];

        if (true === $this->use_db)
        {
            $hash       = $this->sm->get('GoogleTranslateTable')->getHash( $text );
            $db_hash    = $this->sm->get('GoogleTranslateTable')->findByHash( $hash );

            if (null !== $db_hash && $db_hash->text === $text)
            {
                return [$db_hash->from_lang, $db_hash->translate];
            }
        }

        $params = [
            'q'         => $text,
            'target'    => 'en',
            'key'       => $this->key
        ];

        if (null !== $source)
            $params['source'] = $source;

        // https://www.googleapis.com/language/translate/v2?q=bonjour&target=en&source=fr&key=AIzaSyB6qK07W3zR4e1b7ELTLOJR0_qkf3oHUW8
        try
        {
            $data = $this->client->get('https://www.googleapis.com/language/translate/v2', ['query' => $params]);
        }
        catch (\Exception $e)
        {
            $this->sm->get('ErrorTable')->logError( $e );

            if (null !== $source)
                return $this->translate($text, null);
        }

        $json = $data->json();

        if (isset($json['data']) && isset($json['data']['translations']))
        {
            if (isset($json['data']['translations'][0]['detectedSourceLanguage']))
                $source = $json['data']['translations'][0]['detectedSourceLanguage'];
            return [ $source, $json['data']['translations'][0]['translatedText'] ];
        }

        return $data->json();
    }
}
