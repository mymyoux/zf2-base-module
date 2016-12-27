<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;



/**
 * Configuration Helper
 * @package Core\Service
 */
class Console extends CoreService implements ServiceLocatorAwareInterface
{
    const COMMAND_UP = "F";
    const COMMAND_DOWN = "E";
    const COMMAND_CLEAR = "J";
    const COMMAND_MOVE =  "H";
    protected $width;
    protected $height;
    public function updateSize()
    {
        $this->width = (int)exec('tput cols');
        $this->height = (int)exec('tput lines');
    }
    public function move($x, $y)
    {
        $this->out($this->cmd(Console::COMMAND_MOVE, $x, $y));
    }
    public function clear()
    {
        $this->out($this->cmd(Console::COMMAND_CLEAR));
    }
    protected function cmd($value, $first = NULL, $second = NULL)
    {
        $text =  "\033[";
        if(isset($first))
            $text.= $first;
        if(isset($second))
            $text.=";".$second;
        return $text.$value;
    }
    public function out($value)
    {
        if(!isset($this->width))
            $this->updateSize();
        echo $value;
        //echo "\r".$value;
    }
    
}
