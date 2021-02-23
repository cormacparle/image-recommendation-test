<?php

namespace ImageRecommendationTest\Jobs;

include( __DIR__ . '/GenericJob.php' );

class FetchSearchResults extends GenericJob {

    private $apiEndpoint;

    public function __construct( array $config = null ) {
        parent::__construct( $config );

        $this->apiEndpoint = urldecode( $this->config['search']['apiEndpoint'] );
    }

    public function run() {
        $articles = $this->db->query( 'select * from unillustratedArticles' );
        while ( $articleRow = $articles->fetch_assoc() ) {
            $filePages = [];
            $imageResults = $this->httpGETJson(
                $this->apiEndpoint,
                $articleRow['langCode'],
                $articleRow['pageTitle']
            );
            $hits = $imageResults['__main__']['result']['hits']['hits'];
            if ( count( $hits ) > 0 ) {
                foreach ( $hits as $hit ) {
                    $filePages[] = $this->extractTitle( $hit['_source'] );
                }
                $filePageMetaData = $this->getFileMetadata( $filePages );
                foreach ( $filePages as $filePage ) {
                    $this->db->query(
                        'insert into imageRecommendations set ' .
                        'unillustratedArticleId=' . intval( $articleRow['id'] ) . ',' .
                        'resultFilePage="' . $this->db->real_escape_string( $filePage )  . '",' .
                        'resultImageUrl="' . $this->db->real_escape_string(
                            $this->getImageUrl( $filePage, $filePageMetaData )
                        ) . '"'
                    );
                }
            }
        }
    }

    private function extractTitle( array $source ) : string {
        $title = str_replace( ' ', '_', $source['title'] );
        if ( $source['namespace'] > 0 ) {
            $title = $source['namespace_text'] . ':' . $title;
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

$job = new FetchSearchResults();
$job->run();
echo "Done\n";