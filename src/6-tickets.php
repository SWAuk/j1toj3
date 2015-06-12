<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromTable = 'swa_events_users';
$toTable = 'swa_ticket';

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( $fromTable ) );
$fromResult = $from->query( $fromQueryBuilder->getSQL() );

$eventTicketsQueryBuilder = $to->createQueryBuilder();
$eventTicketsQueryBuilder->select( '*' )->from( Migration::getToTable( "swa_event_ticket" ) );
$eventTicketsResult = $to->query( $eventTicketsQueryBuilder->getSQL() );
$allEventTickets = $eventTicketsResult->fetchAll();

$memberQueryBuilder = $to->createQueryBuilder();
$memberQueryBuilder->select( '*' )->from( Migration::getToTable( "swa_member" ) );
$memberResult = $to->query( $memberQueryBuilder->getSQL() );
$allMembers = $memberResult->fetchAll();

/**
 * @param int $userId
 *
 * @return int mixed
 * @throws Exception
 */
function getMemberIdFromUserId( $userId ) {
    global $allMembers;
    foreach( $allMembers as $memberData ) {
        if( $memberData['user_id'] == $userId ) {
            return $memberData['id'];
        }
    }
    throw new Exception("Failed to get member id for user: $userId");
}

/**
 * @param int $eventId
 *
 * @return int
 * @throws Exception
 */
function getEventTicketIdForEvent( $eventId ){
    global $allEventTickets;
    foreach( $allEventTickets as $eventTicketData ) {
        if( $eventTicketData['event_id'] == $eventId ) {
            return $eventTicketData['id'];
        }
    }
    throw new Exception("Failed to get event ticket id for event: $eventId");
}

$to->beginTransaction();
$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( $toTable ) );
foreach( $fromResult->fetchAll() as $row ) {
    try {
        $memberId = getMemberIdFromUserId( $row['user_id'] );
    }
    catch( Exception $e ) {
        echo "Did not add ticket for User: " . $row['user_id'] . " as no memberId could be found\n";
        continue;
    }
    try {
        $eventTicketId = getEventTicketIdForEvent( $row['event_id'] );
    }
    catch( Exception $e ) {
        echo "Did not add ticket for Event: " . $row['event_id'] . " as no eventTicketId could be found\n";
        continue;
    }

    $toQueryBuilder->values( array(
        'id' => $to->quote( $row['euid'], PDO::PARAM_INT ),
        'member_id' => $to->quote( $memberId, PDO::PARAM_INT ),
        'event_ticket_id' => $to->quote( $eventTicketId, PDO::PARAM_INT ),
        'paid' => $to->quote( $row['paid'], PDO::PARAM_STR ),
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