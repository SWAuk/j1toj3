<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromTable = 'swa_event_grants';
$toTable = 'swa_grant';

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( $fromTable ) );
$fromResult = $from->query( $fromQueryBuilder->getSQL() );

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

$to->beginTransaction();
$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( $toTable ) );
foreach( $fromResult->fetchAll() as $row ) {
    $toQueryBuilder->values( array(
        'id' => $to->quote( $row['id'], PDO::PARAM_INT ),
        'event_id' => $to->quote( $row['event_id'], PDO::PARAM_INT ),
        'application_date' => $to->quote( $row['application_date'], PDO::PARAM_STR ),
        'amount' => $to->quote( $row['amount'], PDO::PARAM_STR ),
        'fund_use' => $to->quote( $row['fund_use'], PDO::PARAM_STR ),
        'instructions' => $to->quote( $row['instructions'], PDO::PARAM_STR ),
        'ac_sortcode' => $to->quote( $row['ac_sortcode'], PDO::PARAM_STR ),
        'ac_number' => $to->quote( $row['ac_number'], PDO::PARAM_STR ),
        'ac_name' => $to->quote( $row['ac_name'], PDO::PARAM_STR ),
        'finances_date' => $to->quote( $row['finances_date'], PDO::PARAM_STR ),
        'finances_id' => $to->quote( $row['finances_id'], PDO::PARAM_INT ),
        'auth_date' => $to->quote( $row['authorisation_date'], PDO::PARAM_STR ),
        'auth_id' => $to->quote( $row['authorisation_id'], PDO::PARAM_INT ),
        'payment_date' => $to->quote( $row['payment_date'], PDO::PARAM_STR ),
        'payment_id' => $to->quote( $row['payment_id'], PDO::PARAM_INT ),
        'created_by' => $to->quote( getMemberIdFromUserId( $row['applicant_id'] ), PDO::PARAM_INT ),
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