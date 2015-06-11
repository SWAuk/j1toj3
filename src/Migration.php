<?php

use Doctrine\DBAL\DriverManager;

class Migration {

    public static function getOldDb() {
        $params = array(
            'dbname' => 'swa_old',
            'user' => 'root',
            'password' => 'toor',
            'host' => 'localhost',
            'driver' => 'pdo_mysql',
        );
        return DriverManager::getConnection($params);
    }

    public static function getNewDb() {
        $params = array(
            'dbname' => 'swa_new',
            'user' => 'root',
            'password' => 'toor',
            'host' => 'localhost',
            'driver' => 'pdo_mysql',
        );
        return DriverManager::getConnection($params);
    }

    public static function getFromTable( $tableName ) {
        return "jos_" . $tableName;
    }

    public static function getToTable( $tableName ) {
        return "swan_" . $tableName;
    }

}