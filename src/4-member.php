<?php
require_once  dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once  __DIR__ . DIRECTORY_SEPARATOR . 'Migration.php';
$to = Migration::getNewDb();
$from = Migration::getOldDb();

$fromTable = 'swa_user';
$toTable = 'swa_member';

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
$anotherQueryBuilder = $to->createQueryBuilder();
$anotherQueryBuilder->insert( Migration::getToTable( 'swa_university_member' ) );
foreach( $fromResult->fetchAll() as $row ) {
    $toQueryBuilder->values( array(
        'id' => $to->quote( $row['id'], PDO::PARAM_INT ),
        'user_id' => $to->quote( $row['mambo_id'], PDO::PARAM_INT ),
        'sex' => $to->quote( $row['sex'], PDO::PARAM_STR ),
        'university_id' => $to->quote( getUniIdForUniSnip( $row['uni_id'] ), PDO::PARAM_INT ),
        'course' => $to->quote( $row['course'], PDO::PARAM_STR ),
        'level' => $to->quote( $row['level'], PDO::PARAM_STR ),
        'discipline' => $to->quote( $row['discipline'], PDO::PARAM_STR ),
        'shirt' => $to->quote( $row['shirt'], PDO::PARAM_STR ),
        'econtact' => $to->quote( $row['emergencyContact'], PDO::PARAM_STR ),
        'enumber' => $to->quote( $row['emergencyNumber'], PDO::PARAM_STR ),
        'tel' => $to->quote( $row['mobile'], PDO::PARAM_STR ),
        'dob' => $to->quote( $row['dob'], PDO::PARAM_STR ),
        'graduation' => $to->quote( $row['graduation'], PDO::PARAM_INT ),
        'swahelp' => $to->quote( $row['swa_help'], PDO::PARAM_INT ),
        'paid' => $to->quote( $row['swa_member'], PDO::PARAM_INT ),
    ) );
    $insertResult = $to->query( $toQueryBuilder->getSQL() );
    if( strval( $row['club_member'] ) == "1" ) {
        $anotherQueryBuilder->values( array(
            'member_id' => $to->quote( $row['id'], PDO::PARAM_INT ),
            'university_id' => $to->quote( getUniIdForUniSnip( $row['uni_id'] ), PDO::PARAM_INT ),
            'committee' => $to->quote( 0, PDO::PARAM_INT ),
            'graduated' => $to->quote( (int)( intval( $row['graduation'] ) < 2015 ), PDO::PARAM_INT ),
        ) );
        $anotherInsertResult = $to->query( $anotherQueryBuilder->getSQL() );
    }
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

echo "Done!\n";