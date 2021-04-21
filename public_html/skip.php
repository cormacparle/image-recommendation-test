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

if ( !isset( $_POST['id'] )) {
    throw new Exception( 'Missing data' );
}

echo "cannot rate image " . intval( $_POST['id'] ) . "\n";
$mysqli->query(
    'update imageRecommendations 
    set viewCount=viewCount+1
    where id=' . intval( $_POST['id'] )
);
if ( $_POST['sensitive'] === '0' || $_POST['sensitive'] === '1' ) {
    echo "marked image " . intval( $_POST['id'] ) . " as " . ( $_POST['sensitive'] === '0' ? 'not' : ''
        ) . "sensitive.\n";
    $mysqli->query(
        'update imageRecommendations 
		set `sensitive`='. intval( $_POST['sensitive'] ) .'
		where id=' . intval( $_POST['id'] )
    );
}

$mysqli->close();
