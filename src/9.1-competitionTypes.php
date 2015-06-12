<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$toTable = 'swa_competition_type';
$to->beginTransaction();
$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( $toTable ) );

$types = array(
    'Beginner Race',
    'Intermediate Race',
    'Advanced Race',
    'Freestyle',
    'Wave',
    'Team',
);

foreach( $types as $type ) {
    $toQueryBuilder->values( array(
        'name' => $to->quote( $type, PDO::PARAM_STR ),
    ) );
    $insertResult = $to->query( $toQueryBuilder->getSQL() );
}
try{
    $to->commit();
} catch(Exception $e) {
    $to->rollback();
    throw $e;
}

echo "Done!";