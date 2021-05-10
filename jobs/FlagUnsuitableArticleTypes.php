<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class FlagUnsuitableArticleTypes extends GenericJob {

    private static $unsuitablePageTypes = [
        "Q577", // year page
        "Q3186692", // calendar year page
        "Q4167410", // disambiguation page
        "Q13406463", // list page
        "Q101352", // family name
        "Q12308941", // male given name
        "Q11879590", // female given name
        "Q18340514", // events in a specific year or time period
        "Q3311614", // century leap year
        "Q82799", // name
        "Q14795564", // point in time with respect to current timeframe
    ];

    public function __construct( array $config = null ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['flagUnsuitableArticleTypes'] );
    }

    public function run() {
        $articles = $this->db->query('select id,pageTitle,langCode from unillustratedArticles ' .
            'order by langCode asc' );
        while ( $article = $articles->fetch_assoc() ) {
            $unsuitableArticleType = false;
            $result = $this->httpGETJson(
              $this->config['endpoint']['wbgetentities'],
                $article['langCode'],
                $article['pageTitle']
            );
            $entity = array_pop($result['entities'] );
            if ( isset( $entity['claims'] ) ) {
                foreach ( $entity['claims'] as $pid => $claimsForPid ) {
                    if ( $pid !== 'P31' ) {
                        continue;
                    }
                    foreach ( $claimsForPid as $claim ) {
                        if ( in_array( $claim['mainsnak']['datavalue']['value']['id'],
                            self::$unsuitablePageTypes ) ) {
                            $unsuitableArticleType = true;
                        }
                    }
                }
                if ( $unsuitableArticleType ) {
                    $this->db->query( 'update unillustratedArticles set unsuitableArticleType=1 ' .
                        'where id=' . intval( $article['id'] ) );
                }
            }
        }
    }
}

$job = new FlagUnsuitableArticleTypes();
$job->run();
echo "Done\n";