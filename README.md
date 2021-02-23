# image-recommendation-test

Tools for testing the mediawiki image recommendations api.

See https://phabricator.wikimedia.org/T273062 

## Steps
1. Run `jobs\PopulateUnillustratedArticlesTable.php` to populate the db with a random 500 unillustrated articles (that are not disambiguation pages)
2. Run `jobs\FetchSearchResults.php` to fetch image recommendations for each of the articles, and store them
3. Point your browser at `public_html\index.html` to rate whether the image recommendations suit the articles 