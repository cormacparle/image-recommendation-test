<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class Deduplicate extends GenericJob {

    public function __construct( array $config = null ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['deduplicate'] );
    }

    public function run() {
        $articles = $this->db->query(
            'select unillustratedArticles.pageTitle,imageRecommendations.resultFilePage,' .
            'count(imageRecommendations.id) from ' .
            'unillustratedArticles join imageRecommendations on ' .
            'unillustratedArticles.id=imageRecommendations.unillustratedArticleId ' .
            'group by unillustratedArticles.pageTitle,imageRecommendations.resultFilePage ' .
            'having count(imageRecommendations.resultFilePage)>1'
        );
        while ( $article = $articles->fetch_assoc() ) {
            $this->log( 'Removing duplicate recommendations ' . $article['resultFilePage'] .
                ' for ' . $article['pageTitle']
            );
            $duplicates = $this->db->query(
                'select imageRecommendations.id,rating from ' .
                'unillustratedArticles join imageRecommendations on ' .
                'unillustratedArticles.id=imageRecommendations.unillustratedArticleId ' .
                'where pageTitle = \'' . $this->dbEscape( $article['pageTitle'] ). '\' ' .
                'and resultFilePage = \'' . $this->dbEscape( $article['resultFilePage'] ). '\' ' .
                'and rating is null'
            );
            // ignore the first
            $duplicate = $duplicates->fetch_assoc();
            $this->log( 'Ignoring recommendation with id ' . $duplicate['id'] );
            // delete the others
            while ( $duplicate = $duplicates->fetch_assoc() ) {
                $query = 'delete from imageRecommendations where id=' .intval( $duplicate['id'] );
                $this->log( $query );
                $this->db->query( $query );
            }
        }
    }
}

$job = new Deduplicate();
$job->run();
echo "Done\n";