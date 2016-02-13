<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 20:14
 */

/**
 * checks if it is an URL or not
 * @param string $text
 * @return bool
 */
function is_url( $text )
{
    return filter_var( $text, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED) !== false;
}

/**
 * Display data as var_dump and kill the application
 * @param $data
 */
function dd($data)
{
    echo '<pre>';
        $stack = debug_backtrace();
        $line = $stack[0];
        echo $line["file"].":".$line["line"]."\n";
        $line = $stack[1];
        echo (array_key_exists("class", $line)?$line["class"]."::":"").$line["function"];
    echo '</pre>';
    if(function_exists("xdebug_get_code_coverage"))
    {
        //xebug
        var_dump($data);
    }else
    {
        echo '<pre>';
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS   ,15);
        echo '</pre>';
        echo '<pre>';
        ob_start();
        var_dump($data);
        $content = ob_get_contents();
        ob_end_clean();
        echo htmlspecialchars($content,ENT_QUOTES);
        echo '</pre>';

    }

    exit();
}
/**
 * Display data as JSON and kill the application
 * @param $data
 */
function jj($data)
{
    $result = array();

    $stack = debug_backtrace();
    $line = $stack[0];
    $result["file"] = $line["file"];
    $result["line"] = $line["line"];
    $line = $stack[1];
    if(array_key_exists("class", $line))
        $result["class"] = $line["class"];
    $result["function"] = $line["function"];
    $env = array();
    if(sizeof($_POST)>0)
        $env["post"] = $_POST;
    if(sizeof($_GET)>0)
        $env["get"] = $_GET;
    if(sizeof($_FILES)>0)
        $env["files"] = $_FILES;
    if(sizeof($env)>0)
        $result["environment"] = $env;

    $result["data"] = $data;
    echo  json_encode($result);
    exit();
}

/**
 * Converts CamelCase string to underscore
 * @param $input CamelCase string
 * @return string
 */
function from_camel_case($input) {
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
        $match = $match == strtoupper($match) ? mb_strtolower($match) : lcfirst($match);
    }
    return implode('_', $ret);
}

/**
 * Generates an unique hexadecimal token
 * @param int $length Token's size
 * @return string Hexadecimal token
 */
function generate_token($length = 64)
{
    $cars = array("0","1","2","3","4","5","6","7","8","9","a","b","c","d","e","f");
    $cars_length = sizeof($cars);
    $token = str_replace('.','',microtime(True)."");
    while(mb_strlen($token)<$length)
    {
        $token.= $cars[rand(0, $cars_length-1)];
    }
   return $token;
}

/**
 * Timestamp in milliseconds (1 second interval)
 * @return int
 */
function timestamp()
{
    return time()*1000;
}

/**
 * Converts StdClass to Array recursivly
 * @param $array stdClass StdClass to converts (or partial array etc)
 * @param $underscore bool Converts keys from camelCase to underscore syntax
 * @return array
 */
function toArray($array, $underscore = False)
{
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = toArray($value);
            }
            if ($value instanceof stdClass) {
                $array[$key] = toArray((array)$value);
            }
        }
    }
    if ($array instanceof stdClass) {
        return toArray((array)$array);
    }
    if($underscore)
    {
        foreach($array as $key => $value)
        {
            $underscore_case = from_camel_case($key);
            if($underscore_case != $key)
            {
                $array[$underscore_case] = $value;
                unset($array[$key]);
            }
        }
    }

    return $array;
}
/**
 * Test if an array is associative (not only number indexed)
 * @param  [type]  $array [description]
 * @return boolean        [description]
 */
function is_assoc($array) {
    if(!is_array($array))
   {
        return false;
   }
  return @(bool)count(array_filter(array_keys($array), 'is_string'));
}
/**
 * Test if an array is fully number indexed
 * @param  [type]  $array [description]
 * @return boolean        [description]
 */
function is_numeric_array($array) {
   if(!is_array($array))
   {
        return false;
   }
  return @!(bool)count(array_filter(array_keys($array), 'is_string'));
}
/**
 * Tests if a haystack starts with a needle
 * @param $haystack
 * @param $needle
 * @return bool
 */
function starts_with($haystack, $needle)
{
    return $needle === "" || mb_strpos($haystack, $needle) === 0;
}

/**
 * Tests if a haystack ends with a needle
 * @param $haystack
 * @param $needle
 * @return bool
 */
function ends_with($haystack, $needle)
{
    return $needle === "" || mb_substr($haystack, -mb_strlen($needle)) === $needle;
}

function remove_accents($string) {
    if ( !preg_match('/[\x80-\xff]/', $string) )
        return $string;

    $chars = array(
        // Decompositions for Latin-1 Supplement
        chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
        chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
        chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
        chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
        chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
        chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
        chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
        chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
        chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
        chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
        chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
        chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
        chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
        chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
        chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
        chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
        chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
        chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
        chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
        chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
        chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
        chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
        chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
        chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
        chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
        chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
        chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
        chr(195).chr(191) => 'y',
        // Decompositions for Latin Extended-A
        chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
        chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
        chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
        chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
        chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
        chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
        chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
        chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
        chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
        chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
        chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
        chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
        chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
        chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
        chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
        chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
        chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
        chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
        chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
        chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
        chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
        chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
        chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
        chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
        chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
        chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
        chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
        chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
        chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
        chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
        chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
        chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
        chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
        chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
        chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
        chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
        chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
        chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
        chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
        chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
        chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
        chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
        chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
        chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
        chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
        chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
        chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
        chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
        chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
        chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
        chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
        chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
        chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
        chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
        chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
        chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
        chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
        chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
        chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
        chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
        chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
        chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
        chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
        chr(197).chr(190) => 'z', chr(197).chr(191) => 's'
    );

    $string = strtr($string, $chars);

    return $string;
}

function join_paths() {
    $paths = array();

    foreach (func_get_args() as $arg) {
        if ($arg !== '') { $paths[] = $arg; }
    }

    return preg_replace('#/+#','/',join('/', $paths));
}

function cleanObject($object)
{
    if(!is_array($object))
    {
        return $object;
    }
    foreach($object as $key=>$value)
    {
        if(is_numeric($key))
        {
            $object[$key] = cleanObject($object[$key]);
        }
        if($value === NULL)
        {
            unset($object[$key]);
        }else
        {
            if(is_array($value))
            {
                $object[$key] = cleanObject($object[$key]);
                if(sizeof($object[$key])==0)
                {
                    unset($object[$key]);
                }
            }
        }
    }
    return $object;
}

/**
 * @param $object
 * @param null $init
 * @return array|mixed
 */
function to_array($object, $init = NULL)
{
    if(is_array($object))
    {
        foreach($object as $key => $value)
        {
            $object[$key] = to_array($value);
        }
        $data = $object;
    }else
    if(is_object($object))
    {
        if($init === $object)
        {
            //decomposition
            $keys = get_class_vars(get_class($object));
            $data = array();
            foreach($keys as $key => $value)
            {
                if (!starts_with($key, "_")) {
                    $data[$key] = to_array($object->$key);
                }
            }
            if( method_exists($object, "getShortName"))
            {
                $short = "id_".$object->getShortName();
                if(isset($data["id"]))
                {
                    $data[$short] = $data["id"];
                }else
                if(isset($data[$short]))
                {
                    $data["id"] = $data[$short];
                }
            }
        }
        else
        {
            if(method_exists($object, "__toArray"))
            {
                $data = to_array($object->__toArray());
            }else
            if(method_exists($object, "toArray"))
            {
                $data = to_array($object->toArray());
            }else
            {
                $data = json_decode(json_encode($object), True);
            }
        }

    }else
    {
        return $object;
    }
    return $data;
}
/**
 * Copy a folder to another recursively
 * @param  [type] $src      source folder
 * @param  [type] $dst      target folder
 * @param  array  $exclude  exclude file/folder or extensions (*.ext)
 * @param  [type] $src_root [internal]
 * @return void
 */
function recurse_copy($src,$dst, $exclude = array(), $src_root = NULL) { 
        $dir = opendir($src); 
        if(!isset($src_root))
        {
            $src_root = $src;
        }
        @mkdir($dst); 
     //    $this->getLogger()->info($dst);
        while(false !== ( $file = readdir($dir)) ) { 
            if (( $file != '.' ) && ( $file != '..' )) { 
                
                if(__match($file, $exclude))
                {
                  //  $this->getLogger()->error($dst . '/' . $file);
                    continue;
                }


                if ( is_dir($src . '/' . $file) ) { 
                    recurse_copy($src . '/' . $file,$dst . '/' . $file, $exclude, $src_root); 
                } 
                else { 
                    copy($src . '/' . $file,$dst . '/' . $file); 
                    
                   // $this->getLogger()->normal($dst . '/' . $file);
                } 
            } 
        } 
        closedir($dir); 
    } 
    /**
     * Used by recursive_copy
     * @param  [type] $file    [description]
     * @param  [type] $exclude [description]
     * @return [type]          [description]
     */
function __match($file, $exclude)
{
    if(in_array($file, $exclude))
    {
        return True;
    }
    foreach($exclude as $exclusion)
    {
        if(starts_with($exclusion, "*."))
        {
            $exclusion = substr($exclusion, 1);
            if(ends_with($file, $exclusion))
            {
                return True;
            }
        }
    }

    return False;
}
function is_email($email){
    return filter_var($email, \FILTER_VALIDATE_EMAIL);
}

function slug($str, $replace=array(), $delimiter='-') {
    if( !empty($replace) ) {
        $str = str_replace((array)$replace, ' ', $str);
    }

    $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
    $clean = mb_strtolower(trim($clean, '-'));
    $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

    return $clean;
}
