<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 13/11/14
 * Time: 16:18
 */

namespace Core\Model\Game\Elo;

use Core\Model\CoreModel;
class Player extends CoreModel
{
    public $id;
    public $place;
    public $previousPoints;
    public $points;
    public function constructor()
    {
        super();
        $this->place = 0;
        $this->previousPoints = 0;
        $this->points = 0;
    }
    public function getDiff()
    {
        return $this->points - $this->previousPoints;
    }
}
