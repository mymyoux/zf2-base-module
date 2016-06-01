<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 13/11/14
 * Time: 16:18
 */

namespace Core\Model\Game\Elo;
use Core\Model\CoreModel;

class Match extends CoreModel
{
  private $players = array();
  public function constructor()
  {
  	super();
  	$this->players = [];
  }
  public function addPlayer($id, $points, $place = NULL)
  {
    $player = new Player();
    $player->id    = $id;
    $player->place   = isset($place)?$place:sizeof($this->players);
    $player->previousPoints  = $points;
    $this->players[] = $player;
  }
  public function getPlayer($id)
  {
  	 	foreach ($this->players as $player)
	    {
	      if ($player->id == $id)
	        return $player;
	    }
	    return NULL;
  }
  public function compute()
  {
    $n = count($this->players);
    $K = 32 / ($n - 1);
    for ($i = 0; $i < $n; $i++)
    {
      $curPlace = $this->players[$i]->place;
      $curELO   = $this->players[$i]->previousPoints;
      $diff = 0;
      for ($j = 0; $j < $n; $j++)
      {
        if ($i != $j)
        {
          $opponentPlace = $this->players[$j]->place;
          $opponentELO   = $this->players[$j]->previousPoints;
          //work out S
          if ($curPlace < $opponentPlace)
            $S = 1;
          else if   ($curPlace == $opponentPlace)
            $S = 0.5;
          else
            $S = 0;
          //work out EA
          $EA = 1 / (1 + pow(10, ($opponentELO - $curELO) / 400));
          $diff += round($K * ($S - $EA));
        }
      }
      //add accumulated change to initial ELO for final ELO   
      $this->players[$i]->points = $this->players[$i]->previousPoints + $diff;
    }
  }
}
