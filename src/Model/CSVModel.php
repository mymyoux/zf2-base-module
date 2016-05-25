<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 13/11/14
 * Time: 16:18
 */

namespace Core\Model;


class CSVModel extends CoreModel implements \Iterator {

    const DELIMITER_END_LINE = "end_line";
    const DELIMITER_LINE_WRAPPER = "line_wrapper";
    const DELIMITER_END_COLUMN = "end_column";
    private $key = -1; 
    private $row;
    protected $url;
    protected $content;

    protected $delimiters;
    public function __construct($content = NULL, $url = NULL)
    {
        $this->url = $url;
        $this->content = $content;
    }
    public function current()
    {
        $this->parse();
        return $this->row;
    }
    public function key() { 
        $this->parse();
        return $this->key; 
    } 
    public function next()
    {
        $this->parse();
        $this->key++;
        if($this->key<sizeof($this->parsedContent))
        {
            $this->row = $this->parsedContent[$this->key];
        }else
        {
            $this->row = NULL;
        }
        return $this->row;
    }
    public function rewind() 
    { 
        $this->key = -1; 
        $this->next();
    } 
    protected function parse()
    {
        if(isset($this->parsedContent))
        {
            return;
        }
        if(!isset($this->content) && isset($this->url))
        {
            if(!file_exists($this->url))
            {
                throw new \Exception($this->url." doesn't exist");   
            }
            $this->content = file_get_contents($this->url);
            try
            {
                $this->content = iconv("ISO-8859-1","UTF-8//TRANSLIT//IGNORE",$this->content);
            }catch(\Exception $e)
            {

            }
        }
        if(!isset($this->content))
        {
             throw new \Exception("no content");   
        }
        if(!isset($this->delimiters))
        {
            $this->autodetectDelimiters();
        }

        $rows = explode($this->delimiters[CSVModel::DELIMITER_END_LINE], $this->content);
        if($rows[sizeof($rows)-1] == "")
        {
            unset($rows[sizeof($rows)-1]);
            $rows = array_values($rows);
        }
        if($this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER] !== False)
        {
            $len = mb_strlen($this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER]);
            $rows = array_map(function($row) use ($len)
                {

                    return mb_substr($row, $len, -$len);
                }, $rows);
        }
        $rows = array_map(function($row)
                {

                    return new RowModel($row, $this->delimiters);
                }, $rows);
        $this->parsedContent = $rows;
    }
    protected function autodetectDelimiters()
    {
        if(!isset($this->delimiters))
            $this->delimiters = array();
        if(!isset($this->delimiters[CSVModel::DELIMITER_END_LINE]))
        {
            $delimiters = ["\r\n", "\r", "\n"];
            foreach($delimiters as $delimiter)
            {
                $index = mb_strpos($this->content, $delimiter);
                if($index !== False)
                {
                    $this->delimiters[CSVModel::DELIMITER_END_LINE] = $delimiter;
                    break;
                }
            }
            if(!isset($this->delimiters[CSVModel::DELIMITER_END_LINE]))
            {
                throw new \Exception("End line delimiter not found");
            }
        }
        if(!isset($this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER]))
        {
            $delimiters = ['"'];
            foreach($delimiters as $delimiter)
            {
                $index = mb_strpos($this->content, $delimiter.$this->delimiters[CSVModel::DELIMITER_END_LINE].$delimiter);
                if($index !== False)
                {
                    $this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER] = $delimiter;
                    break;
                }
            }
            if(!isset($this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER]))
            {
                $this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER] = False;
            }
        }
        if(!isset($this->delimiters[CSVModel::DELIMITER_END_COLUMN]))
        {
            $delimiters = [", "," ;",",",";"];
            $counts = [];
            foreach($delimiters as $delimiter)
            {
                $counts[] = mb_substr_count($this->content, $delimiter);
            }
            $index = 0;
            $max = $counts[0];
            for($i=1; $i<sizeof($counts); $i++)
            {
                if($counts[$i]>$max)
                {
                    $index = $i;
                    $max = $counts[$i];
                }
            }
            $this->delimiters[CSVModel::DELIMITER_END_COLUMN] = $delimiters[$index];
        }
    }
    public function length()
    {
        $this->parse();
        return sizeof($this->parsedContent);
    }
    public function getRow($index)
    {
        $this->parse();
        if($index<sizeof($this->parsedContent))
        {
            return $this->parsedContent[$index];
        }
        return NULL;
    }
    public function removeRow($index)
    {
        if($index instanceof RowModel)
        {
            $index = array_search($index, $this->parsedContent);
            if($index === False)
            {
                return;
            }
        }
        if($index < sizeof($this->parsedContent))
        {
            array_splice($this->parsedContent, $index, 1);
            $this->parsedContent = array_values($this->parsedContent);
            if($this->key == $index)
            {
                $this->key--;
            }
        }
    }
    public function valid() { 
        $this->parse();
        return $this->key<sizeof($this->parsedContent);
    } 
    public function toString()
    {
        $rows = array_map(function($row)
            {
                return $row->toString();
            }, $this->parsedContent);
        $content = implode($this->delimiters[CSVModel::DELIMITER_END_LINE], $rows);
        return $content;
    }
}

class RowModel extends CoreModel implements \Iterator
{
    private $key = -1; 
    private $cell;
    private $delimiters;
    public $parsedContent;
    public function __construct($content = NULL, $delimiters = NULL)
    {
        $this->delimiters = $delimiters;
        $this->content = $content;
    }
    public function current()
    {
        $this->parse();
        return $this->cell;
    }
    public function key() { 
        $this->parse();
        return $this->key; 
    } 
    public function next()
    {
        if(!isset($this->parsedContent))
        {
            $this->parse();
        }
        $this->key++;
        if($this->key<sizeof($this->parsedContent))
        {
            $this->cell = $this->parsedContent[$this->key];
        }else
        {
            $this->cell = NULL;
        }
    }
    protected function parse()
    {
        if(isset($this->parsedContent))
        {
            return;
        }
        $cells = explode($this->delimiters[CSVModel::DELIMITER_END_COLUMN], $this->content);
        $this->parsedContent = $cells;
    }
    public function rewind() 
    { 
        $this->key = -1; 
        $this->next();
    }
    public function length()
    {
        $this->parse();
        return sizeof($this->parsedContent);
    }
    public function getCell($index)
    {
        $this->parse();
        if($index<sizeof($this->parsedContent))
        {
            if(mb_substr($this->parsedContent[$index], 0, 2) == '""' && mb_substr($this->parsedContent[$index], -2) == '""')
            {
                return mb_substr($this->parsedContent[$index], 2, -2);
            }
            return $this->parsedContent[$index];
        }
        return NULL;
    } 
    public function addCell($content)
    {
        $this->parse();
        $this->parsedContent[] = $content;
        $this->content = null;
    }
    public function setCell($index, $data)
    {
        if(!is_numeric($index))
        {
            return NULL;
        }
        $this->parse();
        while($index>=sizeof($this->parsedContent))
        {
            $this->addCell("");
        }
        $this->parsedContent[$index] = $data;
        $this->content = null;
    }
    public function valid() { 
        $this->parse();
        return $this->key<sizeof($this->parsedContent);
    }
    public function find($word)
    {
        $this->parse();
        foreach($this->parsedContent as $key=>$value)
        {
            if(($index = mb_stripos($value, $word)) !== False)
            {
                return $key;
            }
        }   
        return NULL;
    }
    public function toString()
    {
        if(!isset($this->content))
        {
            $content = implode($this->delimiters[CSVModel::DELIMITER_END_COLUMN], $this->parsedContent);
            if($this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER] !== False)
            {
                $content = $this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER].$content.$this->delimiters[CSVModel::DELIMITER_LINE_WRAPPER];   
            }
            $this->content = $content;
        }
        return $this->content;
    }
}
