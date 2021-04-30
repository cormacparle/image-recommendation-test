<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class RemoveDisambigPages extends GenericJob {

    public function __construct( array $config = null ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['removeDisambigPages'] );
    }

    public function run() {
        $disambigEndpoint = 'https://%s.wikipedia.org/w/api.php?action=query' .
            '&format=json&prop=pageprops&titles=%s&ppprop=disambiguation';
        $articles = $this->db->query('select * from unillustratedArticles order by langCode asc' );
        $lastLangCode = 'bn';
        $titles = $titleMapping = [];
        while ( $article = $articles->fetch_assoc() ) {
            $titles[ $article['pageTitle'] ] = $article['langCode'];
            if ( count( $titles ) == 20 || $article['langCode'] !== $lastLangCode ) {
                $result =
                    $this->httpGETJson(
                        $disambigEndpoint,
                        $article['langCode'],
                        implode( '|', array_keys( $titles ) )
                    );
                foreach ( $result['query']['normalized'] as $mapping ) {
                    $titleMapping[$mapping['to']] = $mapping['from'];
                }
                foreach ( $result['query']['pages'] as $page ) {
                    if ( isset( $page['pageprops']['disambiguation'] ) ) {
                        $title = $titleMapping[$page['title']] ?? $page['title'];
                        $query = 'delete unillustratedArticles, imageRecommendations from ' .
                            'unillustratedArticles join imageRecommendations on ' .
                            'unillustratedArticles.id=imageRecommendations.unillustratedArticleId ' .
                            'where unillustratedArticles.pageTitle = \'' .
                            $this->db->real_escape_string( $title ) . '\'';
                        $this->db->query( $query );
                    }
                }
                $titles = $titleMapping = [];
            }
            $lastLangCode = $article['langCode'];
        }
    }
}

$job = new RemoveDisambigPages();
$job->run();
echo "Done\n";