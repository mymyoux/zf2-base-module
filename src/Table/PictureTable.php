<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 21/10/14
 * Time: 10:58
 */

namespace Core\Table;


use Core\Exception\FatalException;
use Core\Table\CoreTable;
use Zend\Db\Sql\Expression;

class PictureTable extends CoreTable
{
    const TABLE = "pictures";
    const TABLE_SIZE = "pictures_size";

    public function generated($picture)
    {
        $this->table()->insert($picture);
        $this->table(PictureTable::TABLE_SIZE)->update(array("count"=>new Expression("count + 1")), array("width"=>$picture["width"],"height"=>$picture["height"]));
    }
    public function isAllowed($width, $height)
    {
        $result = $this->table(PictureTable::TABLE_SIZE)->select(array("width"=>$width,"height"=>$height));
        $result = $result->current();
        if($result === False || $result["verified"] == 0)
        {
            if($result === False)
            {
                $id_user = $this->sm->get("Identity")->isLoggued()?$this->sm->get("Identity")->user->getRealID():NULL;
                $this->table(PictureTable::TABLE_SIZE)->insert(array("width"=>$width,"height"=>$height,"id_user"=>$id_user,"count"=>1));
            }else
            {
                $this->table(PictureTable::TABLE_SIZE)->update(array("count"=>new Expression("count + 1")), array("width"=>$width,"height"=>$height));
            }
            return False;
        }

        return True;
    }
    public function addAllowed($width, $height, $id_user)
    {
        $result = $this->table(PictureTable::TABLE_SIZE)->select(array("width"=>$width,"height"=>$height));
        $result = $result->current();
        if($result === False)
        {
            $this->table(PictureTable::TABLE_SIZE)->insert(array("width"=>$width,"height"=>$height, "id_user"=>$id_user,"verified"=>1));
        }else
        {
            $this->table(PictureTable::TABLE_SIZE)->update(array("verified"=>1,"id_user"=>$id_user),array("id_picture_size"=>$result["id_picture_size"]));
        }
    }
}
