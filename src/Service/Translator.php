<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;



class Translator extends CoreService implements ServiceLocatorAwareInterface
{
	const DEFAULT_LOCALE = "en";
	private $default_locale = NULL;
	public function t($key, $params = NULL, $locale = NULL)
	{
		list($controller, $action, $key) = explode(".",$key);
		//TODO:params ?
		return $this->translate($controller, $action, $key, $locale);
	}
	public function translate($controller, $action, $key, $locale = NULL)
	{
		if(!isset($locale))
		{
			$locale = $this->getLocale();
		}
		$translation = $this->getTranslationTable()->getTranslation($controller, $action, $key, $locale);
		if($translation === NULL)
		{
			$this->getTranslationTable()->flagMissingTranslation($controller, $action, $key, $locale);
			//flag missing traduction
			$translation = $this->getTranslationTable()->getTranslation($controller, $action, $key, Translator::DEFAULT_LOCALE);
			if($translation === NULL)
			{
				$this->getTranslationTable()->flagMissingTranslation($controller, $action, $key, Translator::DEFAULT_LOCALE);
				//flag
				$translation = $controller.".".$action.".".$key;
				return $translation;
			}
		}

		//TODO: add a complete translation with PHP when needed

		return $translation["singular"];
	}

	public function getLocale()
	{
		if(!isset($this->default_locale))
		{
			//try to get from session
			$this->default_locale = $this->sm->get("session")->translator()->locale;
		}
		if(isset($this->default_locale))
		{
			return $this->default_locale;
		}
		$request = $this->sm->get("request");
		if($request instanceof \Zend\Console\Request)
		{
			return $this->_setDefaultLocale(Translator::DEFAULT_LOCALE);
		}
		$headers = $request->getHeaders();
		if ($headers->has('Accept-Language')) {
            $locales = $headers->get('Accept-Language')->getPrioritized();
            $supported = $this->getTranslationTable()->getSupportedLocales();
         //   dd($locales);
            // Loop through all locales, highest priority first
            foreach ($locales as $locale) {
            	if(in_array(mb_strtolower($locale->subtype), $supported))
            	{
            		return $this->_setDefaultLocale(mb_strtolower($locale->subtype));
            	}
            	if(in_array(mb_strtolower($locale->typeString), $supported))
            	{
            		return $this->_setDefaultLocale(mb_strtolower($locale->typeString));
            	}
            	if(in_array(mb_strtolower($locale->type), $supported))
            	{
            		return $this->_setDefaultLocale(mb_strtolower($locale->type));
            	}

            }
          	return $this->_setDefaultLocale(Translator::DEFAULT_LOCALE);
        } else {
           return $this->_setDefaultLocale(Translator::DEFAULT_LOCALE);
        }
	}
    private function _setDefaultLocale($locale)
    {
    	//save into session no more database query
    	$this->sm->get("session")->translator()->locale = $locale;
    	return $locale;
    }


    /**
     * @return \Core\Table\TranslationTable
     */
    protected function getTranslationTable()
    {
        return $this->sm->get("TranslationTable");
    }
}
