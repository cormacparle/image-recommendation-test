<?php

namespace ImageRecommendationTest\Jobs;

use mysqli;

abstract class GenericJob {

    protected $db;
    protected $config;

    public function parseConfigFromStandardLocation() {
        $config = parse_ini_file( __DIR__ . '/../config.ini', true );
        if ( file_exists( __DIR__ . '/../replica.my.cnf' ) ) {
            $config = array_merge(
                $config,
                parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
            );
        }
        return $config;
    }

    public function __construct( array $config = null ) {
        if ( is_null( $config ) )  {
            $config = $this->parseConfigFromStandardLocation();
        }
        $this->config = $config;
        $this->db = new mysqli(
            $config['db']['host'],
            $config['client']['user'],
            $config['client']['password'],
            $config['db']['dbname']
        );
        if ( $this->db->connect_error ) {
            die('DB connection Error (' . $this->db->connect_errno . ') '
                . $this->db->connect_error);
        }
    }

    abstract public function run();

    protected function httpGETJson( string $url, ...$params ) : array {
        $params = array_map( 'urlencode', $params );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        if ( count( $params ) > 0 ) {
            $url = sprintf(
                $url,
                ...$params
            );
        }

        curl_setopt( $ch, CURLOPT_URL, $url );
        $result = curl_exec( $ch );
        if ( curl_errno( $ch ) ) {
            echo "url: " . $url . "\n";
            echo curl_error( $ch ) . ': ' . curl_errno( $ch ) . "\n";
            die( "Exiting because of curl error\n" );
        }
        curl_close( $ch );
        $array = json_decode( $result, true );
        if ( is_null( $array ) ) {
            print_r( $url );
            print_r( $result );
            die( "Unexpected result format.\n" );
        }
        return $array;
    }
}