<?php

abstract class Db
{
    private static $conection;

    public static function getConnection()
    {
        if (!self::$conection) {
            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "leads_archive";

            // Create connection
            self::$conection = new mysqli($servername, $username, $password, $dbname);
        }
        return self::$conection;
    }
}
