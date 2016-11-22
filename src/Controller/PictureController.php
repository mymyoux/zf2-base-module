<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 12/01/15
 * Time: 11:10
 */

namespace Core\Controller;
use Core\Annotations as ghost;


use Zend\View\Model\JsonModel;

class PictureController extends FrontController
{
    /**
     * @return \Zend\Http\Response
     */
    public function indexAction()
    {   

        $width = $this->params()->fromRoute("width");
        $height = $this->params()->fromRoute("height");
        $dpi = (float)$this->params()->fromRoute("dpi");
        $extension = $this->params()->fromRoute("extension");
        $path = $this->params()->fromQuery("path");

        if(isset($path))
        {
            $file_extension = pathinfo($path, \PATHINFO_EXTENSION);
            if(isset($file_extension) && strlen($file_extension))
            {
                $path = mb_substr($path, 0, -(mb_strlen($file_extension)+1));
                $extension = $file_extension;
            }
        }
        if(!isset($width) || !isset($height) || !isset($extension) || !isset($path))
        {
            return $this->redirect()->toUrl("/css/img/default-avatar.svg");
        }

        if(starts_with($path, "/"))
        {
            $path = mb_substr($path, 1);
        }
        if(!starts_with($path, "pictures/") && !starts_with($path, "img/profiles/"))
        {
            return $this->redirect()->toUrl("/css/img/default-avatar.svg?error=bad_path&path=".$path);
        }
        $original = join_paths(ROOT_PATH, "public",$path.".".$extension);

        if(!file_exists($original))
        {
            return $this->redirect()->toUrl("/css/img/default-avatar.svg?error=no_file&path=".$original);
        }



        if(($width!=0 && $width<10) || ($height!=0 && $height<10) || ($height == 0 && $width==0) || $width>800 || $height>800)
        {
            return $this->redirect()->toUrl("/".$path.".".$extension);
        }


        if(!$this->getPictureTable()->isAllowed($width, $height))
        {
            if(!$this->identity->isLoggued() || !$this->identity->user->isAdmin())
            {
                return $this->redirect()->toUrl("/".$path.".".$extension."?error=not_allowed");
            }else
            {
                $this->getPictureTable()->addAllowed($width, $height,$this->identity->user->getRealID());
            }
        }
      

        if($dpi<1)
        {
            //min dpi
            $dpi = 1;
        }
        if($dpi>5)
        {
            //max dpi
            $dpi = 5;
        }
        $destination = join_paths(ROOT_PATH, "public",$path."-".$width."x".$height."x".$dpi.".".$extension);
        $original_width = $width;
        $original_height = $height;
        
        $width = ceil(($width*$dpi)*10)/10;
        $height = ceil(($height*$dpi)*10)/10;
       /* $view->setVariable("original", $original);
        $view->setVariable("destination", $destination);
        $view->setVariable("path", $path);
*/
        if(True)
        {
            try
            {
                if (class_exists('Imagick') && extension_loaded('imagick') && !class_exists('\Gmagick'))
                {
                    $picture = new \Imagick($original);

                    if($width == 0)
                    {
                        $ratio = $picture->getImageHeight()/$height;
                        $width = $picture->getImageWidth()/$ratio;
                    }
                    if($height == 0)
                    {
                        $ratio = $picture->getImageWidth()/$width;
                        $height = $picture->getImageHeight()/$ratio;
                    }

                    $picture->cropThumbnailImage($width, $height);
                    $picture->setCompressionQuality(100);

                    $picture->writeImages($destination, true);

                    $picture->destroy();
                }
                elseif (class_exists('\Gmagick'))
                {
                    $picture = new \Gmagick();

                    $picture->readImage($original);

                    if($width == 0)
                    {
                        $ratio = $picture->getImageHeight()/$height;
                        $width = $picture->getImageWidth()/$ratio;
                    }
                    if($height == 0)
                    {
                        $ratio = $picture->getImageWidth()/$width;
                        $height = $picture->getImageHeight()/$ratio;
                    }

                    $picture->cropthumbnailimage($width, $height);

                    $picture->writeImage($destination);

                    $picture->destroy();
                }
                else
                {
                    dd("no lib");
                    throw new \Exception("No Image library installed", 4);
                }

                $this->getPictureTable()->generated(array("path"=>$path,"width"=>$original_width,"dpi"=>$dpi,"height"=>$original_height,"extension"=>$extension,"id_user"=>$this->identity->isLoggued()?$this->identity->user->id:NULL));
            }catch(\Exception $e)
            {
                dd($e->getMessage());
                if(file_exists($destination))
                {
                    if(!unlink($destination))
                    {
                        //TODO:log error
                    }
                }
                $destination = $original;
            }
        }else
        {
            /*
            try
            {
                $picture = new \Imagick();
                $picture->readImage($original);


                if($width == 0)
                {
                    $ratio = $picture->getImageHeight()/$height;
                    $width = $picture->getImageWidth()/$ratio;
                }
                if($height == 0)
                {
                    $ratio = $picture->getImageWidth()/$width;
                    $height = $picture->getImageHeight()/$ratio;
                }

                $picture->cropThumbnailImage($width, $height);



                $picture->writeImage($destination);

                $picture->destroy();
                $this->getPictureTable()->generated(array("path"=>$path,"width"=>$width,"height"=>$height,"extension"=>$extension,"id_user"=>$this->identity->isLoggued()?$this->identity->user->id:NULL));
            }catch(\Exception $e)
            {
                if(file_exists($destination))
                {
                    if(!unlink($destination))
                    {
                        //TODO:log error
                    }
                }
                $destination = $original;
            }*/
        }



        //return $this->redirect()->toUrl()
        $destination = mb_substr($destination, mb_strlen(join_paths(ROOT_PATH,"public")));
       // $view->setVariable("url_destination", $destination);
        return $this->redirect()->toUrl($destination);
    }


    /**
     * @return \Application\Table\PictureTable
     */
    public function getPictureTable()
    {
        return $this->sm->get("PictureTable");
    }

}
