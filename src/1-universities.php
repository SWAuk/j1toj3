<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( 'swa_unis' ) );
$fromResult = $from->query( $fromQueryBuilder->getSQL() );

$toTable = 'swa_university';
$to->beginTransaction();
$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( $toTable ) );
foreach( $fromResult->fetchAll() as $row ) {
    $toQueryBuilder->values( array(
        'id' => $to->quote( $row['id'], PDO::PARAM_INT ),
        'name' => $to->quote( $row['name'], PDO::PARAM_STR ),
        'url' => $to->quote( $row['url'], PDO::PARAM_STR ),
    ) );
    $insertResult = $to->query( $toQueryBuilder->getSQL() );
}
try{
    $to->commit();
} catch(Exception $e) {
    $to->rollback();
    throw $e;
}

//Reset the AutoInc value
$alterAutoIncSql = "DROP PROCEDURE if exists reset_autoincrement;
CREATE PROCEDURE reset_autoincrement
BEGIN

  SELECT @max := MAX(ID)+ 1 FROM ABC;

  PREPARE stmt FROM 'ALTER TABLE " . Migration::getToTable( $toTable ) . " AUTO_INCREMENT = ?'
  EXECUTE stmt USING @max

  DEALLOCATE PREPARE stmt;

END $$";
$alterResult = $to->query( $alterAutoIncSql );