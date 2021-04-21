<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class PrepareDataForRating extends GenericJob {

    public function __construct( array $config = null ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['prepareData'] );
    }

    public function run() {
        $languages = [ 'ar', 'ceb', 'en', 'vi', 'bn', 'cs' ];

        foreach ( $languages as $language ) {
            $unillustratedPagesForLanguage = [];
            $this->log( "Finding suggestions for " . $language . " wiki" );
            foreach ( [ 'ms', 'ima' ] as $source ) {
                $pageCount = 0;
                while ( $pageCount < 250 ) {
                    $timeStart = microtime(true);
                    // results are returned in random order, and "limit" is an upper bound rather
                    // than an exact number, so keep requesting results until we have enough,
                    // keeping track of them in $unillustratedPagesForLanguage so we don't store
                    // duplicates
                    $result =
                        $this->httpGETJson(
                            $this->config['search']['apiEndpoint'] . '?limit=100&source='  .
                            $source,
                            $language
                        );
                    $apiResponseTime = microtime(true) - $timeStart;
                    $this->log( 'API response time (limit=100, source=' . $source. ', language='
                        . $language . ', resultCount=' . count( $result['pages'] ) . '): ' .
                        $apiResponseTime . 's' );
                    if ( count( $result['pages'] ) === 0 ) {
                        continue;
                    }
                    $this->log( 'Found ' . count( $result['pages'] ) . ' pages with suggestions.' );
                    foreach ( $result['pages'] as $page ) {
                        if ( $pageCount >= 250 ) {
                            break;
                        }
                        if ( !in_array( $page['page'], $unillustratedPagesForLanguage ) ) {
                            $pageCount++;
                            $unillustratedPagesForLanguage[] = $page['page'];
                            $this->save( $language, $page['page'], $page['suggestions'] );
                        }
                    }
                    $this->log( $pageCount . ' pages so far for ' . $language . ' for source '
                        . $source );
                }
            }
            $this->log( "Finished " . $language . " wiki." );
        }
    }

    private function save( string $langCode, string $pageTitle, array $suggestions ) {
        $this->db->query( 'insert into unillustratedArticles set ' .
            'langCode = "' . $this->db->real_escape_string( $langCode ) . '",' .
            'pageTitle = "' . $this->db->real_escape_string( $pageTitle ) . '"' );
        $articleId = $this->db->insert_id;
        foreach ( $suggestions as &$suggestion ) {
            $suggestion['filename'] = $this->fixTitle( $suggestion['filename'] );
        }
        $filePageMetaData = $this->getFileMetadata(
            array_column( $suggestions, 'filename' )
        );
        foreach ( $suggestions as $suggestion ) {
            $this->db->query(
                'insert into imageRecommendations set ' .
                'unillustratedArticleId=' . intval( $articleId ) . ',' .
                'resultFilePage="' . $this->db->real_escape_string( $suggestion['filename'] )  . '",' .
                'resultImageUrl="' . $this->db->real_escape_string(
                    $this->getImageUrl( $suggestion['filename'], $filePageMetaData )
                ) . '",' .
                'source="' . $this->db->real_escape_string( $suggestion['source']['name'] )  . '",' .
                'confidence_class="' . $this->db->real_escape_string( $suggestion['confidence_rating'] )  .
                '"'
            );
        }
        $this->log( 'Page ' . $pageTitle . ': ' .
            count( $suggestions ) . ' suggestions found' );
    }

    private function fixTitle( string $title ) : string {
        $title = str_replace( ' ', '_', $title );
        if ( strpos( $title, 'File:' ) !== 0 ) {
            $title = 'File:' . $title;
        }
        return $title;
    }

    private function getFileMetadata( array $titles ) : array {
        $imageInfoUrl = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=imageinfo' .
            '&titles=%s&iiprop=url';
        // if we can request imageinfo for all titles at once then let's do so
        if ( strlen( sprintf( $imageInfoUrl, implode( '|', $titles ) ) ) < 2000 ) {
            $titles = [ implode( '|', $titles ) ];
        }

        $titleMap = $titleToMetadata = [];
        foreach ( $titles as $title ) {
            $result = $this->httpGETJson(
                $imageInfoUrl,
                $title
            );

            if ( isset( $result['query']['normalized'] ) ) {
                foreach ( $result['query']['normalized'] as $fromTo ) {
                    $titleMap[ $fromTo['to'] ] = $fromTo['from'];
                }
            }

            foreach ( $result['query']['pages']  as $id => $page ) {
                $title = $titleMap[ $page['title'] ] ?? $page['title'];
                $titleToMetadata[ $title ] = [
                    'url' => $page['imageinfo'][0]['url'] ?? '',
                ];
            }
        }

        return $titleToMetadata;
    }

    private function getImageUrl( string $title, array $metadata ) : string {
        if ( !empty( $metadata[$title]['url'] ) ) {
            return $this->getThumbnail( $metadata[$title]['url'] );
        }
        return 'https://commons.wikimedia.org/wiki/' . $title;
    }

    private function getThumbnail( string $url ) : string {
        $src = str_replace( '/commons/', '/commons/thumb/', $url ) . '/';
        if ( substr( $url, strrpos( $url, '.' ) + 1 ) == 'tif' ) {
            $src .= 'lossy-page1-800px-thumbnail.tif.jpg';
            return $src;
        }
        $src .= '800px-' . substr( $url, strrpos( $url, '/' ) + 1 );
        return $src;
    }
}

$job = new PrepareDataForRating();
$job->run();
echo "Done\n";