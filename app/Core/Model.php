<?php

namespace App\Core;

use App\Config\Database;
use PDO;

class Model
{

    protected PDO $db;

    public function __construct()
    {

        $this->db = Database::connect();

    }

}