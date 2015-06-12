<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromTable = 'swa_team_results';
$toTable = 'swa_team_result';

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

$allTeamResults = $fromResult->fetchAll();

//START TRANSACTION
$to->beginTransaction();

//TODO this should be set before script running! :O (should be 1 for clean migrate..)
$nextCompetitionId = 1;
$eventToCompMap = array();

//ADD ALL TEAM COMPS
$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( 'swa_competition' ) );
foreach( $allTeamResults as $row ) {
    if( !array_key_exists( $row['event_id'], $eventToCompMap ) ) {
        $toQueryBuilder->values( array(
            'id' => $to->quote( $nextCompetitionId, PDO::PARAM_INT ),
            'event_id' => $to->quote( $row['event_id'], PDO::PARAM_INT ),
            'competition_type_id' => $to->quote( 6, PDO::PARAM_INT ),//6 is a TEAM race
        ) );
        $insertResult = $to->query( $toQueryBuilder->getSQL() );
        $eventToCompMap[$row['event_id']] = $nextCompetitionId;
        $nextCompetitionId++;
    }
}

$toQueryBuilder = $to->createQueryBuilder();
$toQueryBuilder->insert( Migration::getToTable( $toTable ) );
foreach( $allTeamResults as $row ) {
    //Note: Skipping team 105 as NO uni / teamid
    if( $row['id'] == 105 ) {
        continue;
    }

    if (preg_match('/[0-9]+/', $row['team_id'], $matches))
    {
        $teamNumber = $matches[0];
        $universityId = preg_replace( '/[0-9]+/', '', $row['team_id'] );
    } else {
        $universityId = $row['team_id'];
        $teamNumber = 1;
    }

    $toQueryBuilder->values( array(
        'id' => $to->quote( $row['id'], PDO::PARAM_INT ),
        'competition_id' => $to->quote( $eventToCompMap[$row['event_id']], PDO::PARAM_INT ),
        'university_id' => $to->quote( getUniIdForUniSnip( $universityId ), PDO::PARAM_INT ),
        'team_number' => $to->quote( $teamNumber, PDO::PARAM_INT ),
        'result' => $to->quote( $row['points'], PDO::PARAM_INT ),
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

echo "Done!";