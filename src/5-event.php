<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromTable = 'swa_events';
$toTable = 'swa_event';

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( $fromTable ) );
$fromResult = $from->query( $fromQueryBuilder->getSQL() );
$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( "swa_unis" ) );
$unisResult = $from->query( $fromQueryBuilder->getSQL() );
$allUnis = $unisResult->fetchAll();

/**
 * @param string $snip
 *
 * @return int
 * @throws Exception
 */
function getUniIdForUniSnip( $snip ) {
    $snip = strtolower( $snip );
    switch ($snip) {
        case "drhm":
            $snip = "durh";
            break;
        case "slnt":
            $snip = "soin";
            break;
    }
    global $allUnis;
    foreach( $allUnis as $uniData ) {
        if( $uniData['uni_id'] == $snip ) {
            return $uniData['id'];
        }
    }
    throw new Exception("Failed to find actual uni code for : '$snip'");
}

$to->beginTransaction();
$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( $toTable ) );
$eventHostQueryBuilder = $to->createQueryBuilder();
$eventHostQueryBuilder->insert( Migration::getToTable( 'swa_event_host' ) );
$eventTicketQueryBuilder = $to->createQueryBuilder();
$eventTicketQueryBuilder->insert( Migration::getToTable( 'swa_event_ticket' ) );
foreach( $fromResult->fetchAll() as $row ) {
    //SKIP THIS SILLY EVENT
    if ( $row['name'] == "Deposit Pay In" ) {
        continue;
    }

    if( strval( $row['eventmax_total'] ) != '0' ) {
        $eventMax = intval( $row['eventmax_total'] );
    } else {
        $eventMax = intval( $row['eventmax_beg'] ) + intval( $row['eventmax_int'] ) + intval( $row['eventmax_adv'] ) + intval( $row['eventmax_xswa'] );
    }

    $toQueryBuilder->values( array(
        'id' => $to->quote( $row['id'], PDO::PARAM_INT ),
        'name' => $to->quote( $row['name'], PDO::PARAM_STR ),
        'season_id' => $to->quote( $row['season'], PDO::PARAM_INT ),
        'capacity' => $to->quote( $eventMax, PDO::PARAM_INT ),
        'date_open' => $to->quote( $row['opendate'], PDO::PARAM_STR ),
        'date_close' => $to->quote( $row['deadline'], PDO::PARAM_STR ),
        'date' => $to->quote( $row['startdate'], PDO::PARAM_STR ),
    ) );
    $insertResult = $to->query( $toQueryBuilder->getSQL() );

    //EVENT HOST
    if ( $row['uni_id'] != "swa_" ) {
        //add to event host table
        $eventHostQueryBuilder->values( array(
            'event_id' => $to->quote( $row['id'], PDO::PARAM_INT ),
            'university_id' => $to->quote( getUniIdForUniSnip( $row['uni_id'] ), PDO::PARAM_INT ),
        ) );
        $eventHostInsertResult = $to->query( $eventHostQueryBuilder->getSQL() );
        //Force add both UWE and BRIS for BRUWE events
        if ( $row['uni_id'] == "bris" ) {
            $eventHostQueryBuilder->values( array(
                'event_id' => $to->quote( $row['id'], PDO::PARAM_INT ),
                'university_id' => $to->quote( getUniIdForUniSnip( 'uwe_' ), PDO::PARAM_INT ),
            ) );
        }
        if ( $row['uni_id'] == "uwe_" ) {
            $eventHostQueryBuilder->values( array(
                'event_id' => $to->quote( $row['id'], PDO::PARAM_INT ),
                'university_id' => $to->quote( getUniIdForUniSnip( 'bris' ), PDO::PARAM_INT ),
            ) );
        }
        $eventHostInsertResult = $to->query( $eventHostQueryBuilder->getSQL() );
    }

    //EVENT TICKET TABLE
    //Note: Just add 1 ticket, legacy ticket!!! mwaahahahahhaa (we could keep all the old data somewhere?)
    $eventTicketQueryBuilder->values(array(
        'event_id' => $to->quote( $row['id'], PDO::PARAM_INT ),
        'name' => $to->quote( "Legacy Ticket", PDO::PARAM_STR ),
        'quantity' => $to->quote( 999, PDO::PARAM_INT ),
        'price' => $to->quote( 0, PDO::PARAM_INT ),
    ));
    $eventTicketResult = $to->query( $eventTicketQueryBuilder->getSQL() );
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

echo "Done!";