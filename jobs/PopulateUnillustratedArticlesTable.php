<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class PopulateUnillustratedArticlesTable extends GenericJob {

    public function run() {
        $sources = [
            [
                'langCode' => 'ar',
                'source' => __DIR__ . '/../input/arwiki_articles.tsv',
                'lineCount' => 581711
            ],
            [
                'langCode' => 'ceb',
                'source' => __DIR__ . '/../input/cebwiki_articles.tsv',
                'lineCount' => 1357406
            ],
            [
                'langCode' => 'en',
                'source' => __DIR__ . '/../input/enwiki_articles.tsv',
                'lineCount' => 2932614
            ],
            [
                'langCode' => 'vi',
                'source' => __DIR__ . '/../input/viwiki_articles.tsv',
                'lineCount' => 867673
            ],
        ];

        foreach ( $sources as $source ) {
            $randomNonDisambigPages = [];
            while ( count( $randomNonDisambigPages ) < 500 ) {
                $randomLineNumbers = $randomPages = [];
                while ( count( $randomLineNumbers ) < 500 ) {
                    $randomLineNumber = mt_rand( 1, $source['lineCount'] );
                    if ( !isset( $randomLineNumbers[$randomLineNumber] ) ) {
                        $randomLineNumbers[$randomLineNumber] = 1;
                    }
                }
                $fh = fopen( $source['source'], 'r' );
                //skip first row
                $row = fgetcsv( $fh, 1024, "\t" );
                $count = 1;
                while ( $row = fgetcsv( $fh, 1024, "\t" ) ) {
                    if ( isset( $randomLineNumbers[$count] ) ) {
                        $articleId = $row[2];
                        $pageTitle = $row[3];
                        $randomPages[$articleId] = $pageTitle;
                    }
                    $count ++;
                }
                echo "Choosing " . count( $randomPages ) . " pages from " .
                    $source['langCode'] . " wiki ...\n";
                // Loop through pages and remove disambiguation pages
                for ( $i = 0; $i < count( $randomPages ); $i += 10 ) {
                    $slice = array_slice( $randomPages, $i, 10, true );
                    $result = $this->httpGETJson(
                        'https://' . $source['langCode'] . '.wikipedia.org/w/api.php?' .
                        'action=query&prop=pageprops&' .
                        'ppprop=disambiguation&redirects&format=json&titles=%s',
                        implode( '|', $slice )
                    );
                    foreach ( $result['query']['pages'] as $articleId => $page ) {
                        if ( isset( $page['pageprops']['disambiguation'] ) ) {
                            unset( $randomPages[$articleId] );
                        }
                    }
                }
                echo "... of which " . count( $randomPages ) . " are non-disambiguation pages. \n";
                $randomNonDisambigPages = array_merge( $randomNonDisambigPages, $randomPages );
            }
            $randomNonDisambigPages = array_slice( $randomNonDisambigPages, 0, 500, true );
            echo "Inserting 500 " . $source["langCode"] . " unillustrated non-disambiguation articles into db.\n";
            foreach ( $randomNonDisambigPages as $articleId => $pageTitle ) {
                $this->db->query( 'insert into unillustratedArticles set ' .
                    'articleId = ' . intval( $articleId ) . ',' .
                    'langCode = "' . $this->db->real_escape_string( $source['langCode'] ) . '",' .
                    'pageTitle = "' . $this->db->real_escape_string( $pageTitle ) . '"' );
            }
        }
    }
}

$job = new PopulateUnillustratedArticlesTable();
$job->run();
echo "Done\n";