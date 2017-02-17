<?php

namespace Core\Table;

use Core\Table\CoreTable;

class GoogleTranslateTable extends CoreTable
{
    CONST TABLE         = 'google_translate';

    public function insertTranslate( $data )
    {
        $hash   = $this->getHash( $data['text'] );

        if (null === $this->findByHash($hash))
        {
            $data['hash'] = $hash;
            $this->table(self::TABLE)->insert($data);

            return $this->table(self::TABLE)->lastInsertValue;
        }

        return null;
    }

    public function findByHash( $hash )
    {
        $request = $this    ->select([ 'a' => self::TABLE ])
                             ->where([
                                'hash' => (string) $hash
                            ]);

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;

        return $data;
    }

    public function getHash( $text )
    {
        $text          = strip_tags($text);
        $hash          = sha1($text);

        return $hash;
    }

    public function getByExternal( $type_external, $id_external, $type = null )
    {
        $where  = $this->select([ 'gt' => self::TABLE ])
                    ->where
                    ->equalTo('gt.id_external', (int) $id_external)
                    ->and
                    ->equalTo('gt.type_external', $type_external)
                    ;

        if (null !== $type)
        {
            $where->and->equalTo('gt.type', $type);
        }

        $request = $this->select([ 'gt' => self::TABLE ])
            ->where( $where )
            ;

        $result     = $this->execute($request);
        $result     = $result->current();

        return $result;
    }
}
