<?php

namespace Core\Queue;


interface ListenerInterface
{
    public function __construct($tries);

    public function checkJob( $data );
    public function executeJob( $data );
}
