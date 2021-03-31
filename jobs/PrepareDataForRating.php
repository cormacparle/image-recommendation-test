<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class PrepareDataForRating extends GenericJob {

    public function __construct( array $config = null ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['prepareData'] );
    }

    public function run() {
        $sources = [
            [
                'langCode' => 'ar',
                'unillustratedArticleCount' => 58171 //581711
            ],
            [
                'langCode' => 'ceb',
                'unillustratedArticleCount' => 135740 //1357406
            ],
            [
                'langCode' => 'en',
                'unillustratedArticleCount' => 293261 //2932614
            ],
            [
                'langCode' => 'vi',
                'unillustratedArticleCount' => 86767 //867673
            ],
            [
                'langCode' => 'bn',
                'unillustratedArticleCount' => 3364 //33643
            ],
            [
                'langCode' => 'cs',
                'unillustratedArticleCount' => 18217 //182178
            ],
        ];

        foreach ( $sources as $source ) {
            $this->log( "Finding suggestions for " . $source['langCode'] . " wiki" );
            $failedPages = $pages = [];
            while ( count( $pages ) < 500 ) {
                $randomPageNumber = mt_rand( 1, $source['unillustratedArticleCount'] );
                if ( isset( $pages[$randomPageNumber] ) ) {
                    continue;
                }

                $result =
                    $this->httpGETJson( $this->config['search']['apiEndpoint'] . '?limit=1&offset=' .
                        $randomPageNumber, $source['langCode'] );
                if ( count($result) === 0 || count( $result[0]['suggestions'] ) === 0 ) {
                    $failedPages[$randomPageNumber] = 1;
                    $this->log( 'Page at position ' . $randomPageNumber . ': no suggestions found' );
                    continue;
                }
                $pages[$randomPageNumber] = 1;
                $this->save( $source['langCode'], $result[0]['page'],
                    $result[0]['suggestions'], $randomPageNumber );
                $this->log( 'Page at position ' . $randomPageNumber .
                    ' (' . $result[0]['page'] . '): suggestions found' );
            }
            $this->log( "Finished " . $source['langCode'] . " wiki." );
        }
    }

    private function save( string $langCode, string $pageTitle, array $suggestions,
                           $randomPageNumber ) {
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
                'source="' . $this->db->real_escape_string( $suggestion['source'] )  . '",' .
                'confidence_class="' . $this->db->real_escape_string( $suggestion['confidence_rating'] )  .
                '"'
            );
        }
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