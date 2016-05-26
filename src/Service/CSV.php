<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 16:12
 */

namespace Core\Service;


use Core\Service\Api\Request;
use Core\Annotations\Paginate;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Core\Model\CSVModel;

/**
 * Class CSV
 * @package Core\Service
 */
class CSV extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    public function load($url)
    {
        $csv = new CSVModel(NULL, $url);
        return $csv;
    }
}
