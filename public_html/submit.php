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

if ( !isset( $_POST['id'] ) || !isset( $_POST['rating'] ) || !isset( $_POST['sensitive'] )) {
    throw new Exception( 'Missing data' );
}

echo "rated image " . intval( $_POST['id'] ) . " with " . intval( $_POST['rating'] ) . "\n";
$mysqli->query(
    'update imageRecommendations 
    set rating='. intval( $_POST['rating'] ) .'
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
