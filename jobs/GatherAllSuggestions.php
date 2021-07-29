<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class GatherAllSuggestions extends GenericJob {

    public function __construct( array $config = null ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['prepareData'] );
    }

    public function run() {
        $languages = [
            'ar' => 603024,
            'arz' => 804095,
            'bn' => 42237,
            'ceb' => 1239278,
            'cs' => 192938,
            'de' => 127092,
            'en' => 2714211,
            'es' => 675366,
            'eu' => 117370,
            'fa' => 338713,
            'fr' => 888437,
            'he' => 75036,
            'hu' => 173909,
            'hy' => 94070,
            'it' => 674061,
            'ko' => 276904,
            'pl' => 542785,
            'pt' => 55301,
            'ru' => 467467,
            'sr' => 113770,
            'sv' => 1560690,
            'tr' => 160187,
            'uk' => 108732,
            'vi' => 954149,
        ];

        if ( !isset( $this->config['wiki'] ) || !isset( $languages[$this->config['wiki']] ) ) {
            die("You need to supply a wiki with the --wiki param\n");
        }
        $uih = fopen(__DIR__ . '/../output/' . $this->config['wiki'] . '.unillustrated.csv', 'w' );
        $ish = fopen(__DIR__ . '/../output/' . $this->config['wiki'] . '.suggested.csv', 'w' );

        $allSuggestions = [];
        for ( $offset = 0 ; $offset <= $languages[$this->config['wiki']] ; $offset += 500 ) {
            $result =
                $this->httpGETJson(
                    $this->config['endpoint']['api'] . '?seed=0&limit=500&offset=' . $offset,
                    $this->config['wiki']
                );
            foreach ( $result['pages'] as $page ) {
                fputcsv( $uih, [ $page['page_id'], $page['page'] ] );
                foreach ($page['suggestions'] as $suggestion) {
                    if (!isset($allSuggestions[$suggestion['filename']])) {
                        $allSuggestions[$suggestion['filename']] = 1;
                        fputcsv( $ish, [ $suggestion['filename'] ] );
                    }
                }
            }
        }
    }
}

$options = getopt('', [ 'wiki::' ]);
$job = new GatherAllSuggestions( $options );
$job->run();
echo "Done\n";