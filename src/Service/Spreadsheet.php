<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;


use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
use Google\Spreadsheet\SpreadsheetService;



/**
 * Email Helper
 * Class Email
 * @package Core\Service
 */
class Spreadsheet extends CoreService //implements ServiceLocatorAwareInterface
{
    private $_instance;
    private $sheets;
    protected function instance()
    {
        if(!isset($_instance))
        {
            $configuration =  $this->sm->get("AppConfig")->getConfiguration();
            //avoir token google=> galere

            /*$serviceRequest = new DefaultServiceRequest($accessToken);
            ServiceRequestFactory::setInstance($serviceRequest);*/

            $this->_instance = new SpreadsheetService();
            $this->sheets = $this->_instance->getSpreadsheets();
        }
        return $this->$_instance;
    }

    public function getSheets()
    {
        return $this->sheets;
    }
}
