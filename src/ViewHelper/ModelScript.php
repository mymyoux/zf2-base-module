<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 20/10/14
 * Time: 12:30
 */

namespace Core\ViewHelper;

use Zend\View\Helper\AbstractHelper;

/**
 * Class TypeToRouteHelper
 * Helps to find route from current type
 * @package Application\ViewHelper
 */
class ModelScript extends AbstractHelper{

    public function __invoke($model_name, $data)
    {
        $tag = '<script class="model" type="application/json" data-model="'.$model_name.'">';
        $tag.= json_encode($data);
        $tag .= '</script>';
        return $tag;
    }
}