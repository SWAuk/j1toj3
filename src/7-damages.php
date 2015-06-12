<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromTable = 'swa_event_damages';
$toTable = 'swa_damages';

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( $fromTable ) );
$fromResult = $from->query( $fromQueryBuilder->getSQL() );

$to->beginTransaction();
$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( $toTable ) );
foreach( $fromResult->fetchAll() as $row ) {
    $toQueryBuilder->values( array(
        'id' => $to->quote( $row['id'], PDO::PARAM_INT ),
        'date' => $to->quote( $row['time'], PDO::PARAM_STR ),
        'cost' => $to->quote( $row['cost'], PDO::PARAM_STR ),
        'event_id' => $to->quote( $row['event_id'], PDO::PARAM_INT ),
        //NO university ID in the old DB, so NULL can just mean unknown?
    ) );
    $insertResult = $to->query( $toQueryBuilder->getSQL() );
}
try{
    $to->commit();
} catch(Exception $e) {
    $to->rollback();
    throw $e;
}

echo "Done!\n";