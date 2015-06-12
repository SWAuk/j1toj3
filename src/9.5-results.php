<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromTable = 'swa_results';
$toTable = 'swa_indi_result';

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( array( 'a.id as result_id', 'a.*') );
$fromQueryBuilder->from( Migration::getFromTable( $fromTable ), 'a' );
$fromResult = $from->query( $fromQueryBuilder->getSQL() );

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( "swa_unis" ) );
$unisResult = $from->query( $fromQueryBuilder->getSQL() );
$allUnis = $unisResult->fetchAll();

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( "swa_user" ) );
$oldUsersResult = $from->query( $fromQueryBuilder->getSQL() );
$allOldUsers = $oldUsersResult->fetchAll();

$fromQueryBuilder = $from->createQueryBuilder();
$fromQueryBuilder->select( '*' )->from( Migration::getFromTable( "swa_events_users" ) );
$oldEventTicketsResult = $from->query( $fromQueryBuilder->getSQL() );
$allOldEventUsers = $oldEventTicketsResult->fetchAll();

$memberQueryBuilder = $to->createQueryBuilder();
$memberQueryBuilder->select( '*' )->from( Migration::getToTable( "swa_member" ) );
$memberResult = $to->query( $memberQueryBuilder->getSQL() );
$allMembers = $memberResult->fetchAll();

$allResults = $fromResult->fetchAll();

//lol could have used a join....
foreach( $allResults as $key => $result ) {
    $result['user_level'] = '';
    $result['event_level'] = '';
    foreach( $allOldUsers as $oldUserData ) {
        if( $result['user_id'] == $oldUserData['mambo_id'] ) {
            $result['user_level'] = $oldUserData['level'];
        }
    }
    foreach( $allOldEventUsers as $oldEventUserData ) {
        if( $result['user_id'] == $oldEventUserData['user_id'] ) {
            $result['event_level'] = $oldEventUserData['event_level'];
        }
    }
    $allResults[$key] = $result;
}

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
 * @param string $string
 *
 * @return array 0=>string 1=>int
 */
function getUniAndTeamFromString( $string ) {
    if ( preg_match( '/[0-9]+/', $string, $matches ) ) {
        $teamNumber = $matches[0];
        $universityId = preg_replace( '/[0-9]+/', '', $string );
    } else {
        $universityId = $string;
        $teamNumber = 1;
    }
    return array( $universityId, $teamNumber );
}

/**
 * @param $string
 *
 * @return int
 * @throws Exception
 */
function getCompTypeIdFromString( $string ) {
    $string = strtolower( $string );
    switch ($string) {
        case "beginner":
            return 1;
        case "intermediate":
            return 2;
        case "advanced":
            return 3;
        case "freestyle":
            return 4;
        case "wave":
            return 5;
    }
    throw new Exception( "Failed to get a comp type id for level: " . $string );
}

//START TRANSACTION
$to->beginTransaction();

//Start at the beginning of the comps
$nextCompetitionId = 1;
$compMap = array();

$skipResultIds = array();

//ADD ALL THE COMPS!
$toIndiQueryBuilder = $to->createQueryBuilder();
$toIndiQueryBuilder->insert( Migration::getToTable( 'swa_competition' ) );
foreach( $allResults as $row ) {
    if( !array_key_exists( $row['event_id'], $compMap ) ) {
        $compMap[$row['event_id']] = array();
    }

    if( $row['discipline'] == "team" ) {
        if( !array_key_exists( 'team', $compMap[$row['event_id']] ) ) {
            $toIndiQueryBuilder->values( array(
                'id' => $to->quote( $nextCompetitionId, PDO::PARAM_INT ),
                'event_id' => $to->quote( $row['event_id'], PDO::PARAM_INT ),
                'competition_type_id' => $to->quote( 6, PDO::PARAM_INT ),//6 is a TEAM race
            ) );
            $insertResult = $to->query( $toIndiQueryBuilder->getSQL() );
            $compMap[$row['event_id']]['team'] = $nextCompetitionId;
            $nextCompetitionId++;
        }
    } elseif( $row['discipline'] == "wave" ) {
        if( !array_key_exists( 'wave', $compMap[$row['event_id']] ) ) {
            $toIndiQueryBuilder->values( array(
                'id' => $to->quote( $nextCompetitionId, PDO::PARAM_INT ),
                'event_id' => $to->quote( $row['event_id'], PDO::PARAM_INT ),
                'competition_type_id' => $to->quote( 5, PDO::PARAM_INT ),//5 is a TEAM wave
            ) );
            $insertResult = $to->query( $toIndiQueryBuilder->getSQL() );
            $compMap[$row['event_id']]['wave'] = $nextCompetitionId;
            $nextCompetitionId++;
        }
    } elseif( $row['discipline'] == "freestyle" ) {
        if( !array_key_exists( 'freestyle', $compMap[$row['event_id']] ) ) {
            $toIndiQueryBuilder->values( array(
                'id' => $to->quote( $nextCompetitionId, PDO::PARAM_INT ),
                'event_id' => $to->quote( $row['event_id'], PDO::PARAM_INT ),
                'competition_type_id' => $to->quote( 4, PDO::PARAM_INT ),//4 is a TEAM freestyle
            ) );
            $insertResult = $to->query( $toIndiQueryBuilder->getSQL() );
            $compMap[$row['event_id']]['freestyle'] = $nextCompetitionId;
            $nextCompetitionId++;
        }
    }elseif( $row['discipline'] == "race" ) {
        //Add the comp for the level!

        //If they bought a 'special ticket' then we must fall back to their user level...
        if( $row['event_level'] != "XSWA bed" && $row['event_level'] != "Instructor" && $row['event_level'] != "SWA Committee" && $row['event_level'] != "" ) {
            $level = $row['event_level'];
        } else {
            $level = $row['user_level'];
        }

        if( $level == "" ) {
            echo "No level detected for result id: " . $row['result_id'] . " so not migrating ";
            echo "(Event:" . $row['event_level'] . ", User:" . $row['user_level'] . ")\n";
            $skipResultIds[] = $row['result_id'];
            continue;
        }

        $level = strtolower( $level );

        if( !array_key_exists( $level, $compMap[$row['event_id']] ) ) {
            $toIndiQueryBuilder->values( array(
                'id' => $to->quote( $nextCompetitionId, PDO::PARAM_INT ),
                'event_id' => $to->quote( $row['event_id'], PDO::PARAM_INT ),
                'competition_type_id' => $to->quote( getCompTypeIdFromString( $level ), PDO::PARAM_INT ),//6 is a TEAM race
            ) );
            $insertResult = $to->query( $toIndiQueryBuilder->getSQL() );
            $compMap[$row['event_id']][$level] = $nextCompetitionId;
            $nextCompetitionId++;
        }

    } else {
        throw new Exception("Not read to migrate dis :" . $row['discipline'] );
    }
}

$toIndiQueryBuilder = $to->createQueryBuilder();
$toIndiQueryBuilder->insert( Migration::getToTable( $toTable ) );
$toTeamQueryBuilder = $to->createQueryBuilder();
$toTeamQueryBuilder->insert( Migration::getToTable( 'swa_team_result' ) );
foreach( $allResults as $row ) {
    //Skip the things we have already echoed about...
    if( in_array( $row['result_id'], $skipResultIds ) ){
        continue;
    }

    if( $row['discipline'] == "team" ) {
        list( $universityId, $teamNumber ) = getUniAndTeamFromString( $row['user_id'] );
        try{
            $uniIntId = getUniIdForUniSnip( $universityId );
        }
        catch( Exception $e ) {
            if( $universityId == '' ) {
                echo "No uniID detected for team result id: " . $row['result_id'] . " so not migrating, points: " . $row['points'] . " event: " . $row['event_id'] . "\n";
            } else {
                throw $e;
            }

        }
        $toTeamQueryBuilder->values( array(
            'id' => $to->quote( $row['result_id'], PDO::PARAM_INT ),
            'competition_id' => $to->quote( $compMap[$row['event_id']]['team'], PDO::PARAM_INT ),
            'university_id' => $to->quote( $uniIntId, PDO::PARAM_INT ),
            'team_number' => $to->quote( $teamNumber, PDO::PARAM_INT ),
            'result' => $to->quote( $row['points'], PDO::PARAM_INT ),
        ) );
        $insertResult = $to->query( $toTeamQueryBuilder->getSQL() );
    } else {

        $level = strtolower( $row['discipline'] );
        if( $level == 'race' ) {
            if( $row['event_level'] != "XSWA bed" && $row['event_level'] != "Instructor" && $row['event_level'] != "SWA Committee" && $row['event_level'] != "" ) {
                $level = $row['event_level'];
            } else {
                $level = $row['user_level'];
            }
        } else {
            $level = $row['discipline'];
        }
        $level = strtolower( $level );

        $toIndiQueryBuilder->values( array(
            'id' => $to->quote( $row['result_id'], PDO::PARAM_INT ),
            'competition_id' => $to->quote( $compMap[$row['event_id']][$level], PDO::PARAM_INT ),
            'member_id' => $to->quote( getMemberIdFromUserId( $row['user_id'] ), PDO::PARAM_INT ),
            'result' => $to->quote( $row['points'], PDO::PARAM_INT ),
        ) );
        try{
            $insertResult = $to->query( $toIndiQueryBuilder->getSQL() );
        }
        catch( Exception $e ) {
            echo $e->getMessage()."\n";
        }

    }
}

try{
    $to->commit();
} catch(Exception $e) {
    $to->rollback();
    throw $e;
}

echo "Done!";