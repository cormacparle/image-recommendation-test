<?php

namespace ImageRecommendationTest\Jobs;

use mysqli;

abstract class GenericJob {

    protected $db;
    protected $config;
    protected $logFileHandle;

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

    protected function setLogFileHandle( $logFilePath ) {
        $this->logFileHandle = fopen(
            $logFilePath,
            'a'
        );
    }

    public function __construct( array $config = null ) {
        $this->config = array_merge( $this->parseConfigFromStandardLocation(), $config ?? []);
        $this->db = new mysqli(
            $this->config['db']['host'],
            $this->config['client']['user'],
            $this->config['client']['password'],
            $this->config['db']['dbname']
        );
        if ( $this->db->connect_error ) {
            die('DB connection Error (' . $this->db->connect_errno . ') '
                . $this->db->connect_error);
        }
    }

    public function __destruct() {
        if ( !is_null( $this->logFileHandle ) ) {
            fclose( $this->logFileHandle );
        }
    }

    abstract public function run();

    protected function httpGETJson( string $url, ...$params ) : array {
        $params = array_map( 'urlencode', $params );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_USERAGENT, 'Image recommendations test data collector' );

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

    protected function log( $msg ) {
        fwrite( $this->logFileHandle, date( 'Y-m-d H:i:s' ) . ': ' . $msg . "\n" );
    }

    protected function dbEscape( $value ) {
        return $this->db->real_escape_string( $value );
    }
}