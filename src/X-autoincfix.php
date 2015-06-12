<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();

$tableNames = array(
    'swan_swa_committee',
    'swan_swa_competition',
    'swan_swa_competition_type',
    'swan_swa_damages',
    'swan_swa_deposit',
    'swan_swa_event',
    'swan_swa_event_host',
    'swan_swa_event_registration',
    'swan_swa_event_ticket',
    'swan_swa_grant',
    'swan_swa_indi_result',
    'swan_swa_member',
    'swan_swa_qualification',
    'swan_swa_season',
    'swan_swa_team_result',
    'swan_swa_ticket',
    'swan_swa_university',
    'swan_swa_university_member',
);

foreach( $tableNames as $name ) {
    //Reset the AutoInc value
    $to->beginTransaction();
    $alterAutoIncSql = "DROP PROCEDURE if exists reset_autoincrement;
CREATE PROCEDURE reset_autoincrement
BEGIN

  SELECT @max := MAX(ID)+ 1 FROM ABC;

  PREPARE stmt FROM 'ALTER TABLE " . Migration::getToTable( $name ) . " AUTO_INCREMENT = ?'
  EXECUTE stmt USING @max

  DEALLOCATE PREPARE stmt;

END $$";
    $to->query( $alterAutoIncSql );
    $to->commit();
}



echo "Done!";