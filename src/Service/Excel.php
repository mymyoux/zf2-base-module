<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 14/10/2014
 * Time: 13:48
 */

namespace Core\Service;
use PHPExcel;
use PHPExcel_Writer_Excel2007;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
class Excel extends CoreService //implements ServiceLocatorAwareInterface 
{
	protected static $letters = ["A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z"];
   public function createFromArray($data, $filename)
   {
   		$excel = new PHPExcel();
   		$sheet = $excel->getActiveSheet();
   		$columns = empty($data)?[]:array_keys($data[0]);
   		foreach($columns as $key=>$column)
   		{
   			$sheet->setCellValue($this->name($key, 0), $column);
   		}
   		$i = 0;
   		foreach($data as $index=>$object)
   		{
   			$i++;
   			$j = 0;
   			foreach($object as $key=>$value)
   			{
   				$sheet->setCellValue($this->name($j++, $i), (string)(is_array($value)?json_encode($value):$value));
   			}

   		}
   		$name = $filename.".xlsx";
   		if(!file_exists(dirname(join_paths(ROOT_PATH,'public',$name))))
   		{
   			mkdir(dirname(join_paths(ROOT_PATH,'public',$name)),0777,true);
   		}

   		$writer = new PHPExcel_Writer_Excel2007($excel);
   		$writer->save(join_paths(ROOT_PATH,'public',$name));
   		return $name;
   }
   protected function name($column, $row)
   {
   		$column++;
	    $name = "";
   		while($column > 0)
   		{
   			$modulo = ($column - 1)%26;
   			$name .= Excel::$letters[$modulo];
   			$column = (int)(($column-$modulo)/26);
   		}
   		$name.=($row+1);
   		return $name;
   }
}
