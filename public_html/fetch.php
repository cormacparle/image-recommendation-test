<?php

$config = parse_ini_file( __DIR__ . '/../config.ini', true );
if ( file_exists( __DIR__ . '/../replica.my.cnf' ) ) {
    $config = array_merge(
        $config,
        parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
    );
}
$mysqli = new mysqli( $config['db']['host'], $config['client']['user'],
    $config['client']['password'], $config['db']['dbname'] );
if ( $mysqli->connect_error ) {
    die('Connect Error (' . $mysqli->connect_errno . ') '
        . $mysqli->connect_error);
}

$langCode = $_GET[ 'langCode' ] ?? 'en';
$result = $mysqli->query(
    'select imageRecommendations.id as id,langCode,pageTitle,resultFilePage,resultImageUrl
	from imageRecommendations join unillustratedArticles 
	on imageRecommendations.unillustratedArticleId=unillustratedArticles.id
	where rating is null 
	and langCode = "' . $mysqli->real_escape_string( $langCode ) . '"
	and unsuitableArticleType=0
	order by rand() limit 1'
);
header('Content-Type: application/json');
if ( $result->num_rows > 0 ) {
    $row = $result->fetch_assoc();
    echo json_encode( $row );
} else {
    echo json_encode( new stdClass() );
}
$mysqli->close();
