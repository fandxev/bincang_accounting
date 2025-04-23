<?php
require __DIR__ . '/../vendor/autoload.php';

use Ramsey\Uuid\Uuid;

function generate_uuid()
{
    return Uuid::uuid4()->toString();
}
