<?php
require __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

class Controller
{


    public function generate_uuid()
    {
        $uuid = Uuid::uuid4()->toString();

        echo "UUID yang dihasilkan: $uuid";
    }
}
