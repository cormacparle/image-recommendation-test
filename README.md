# image-recommendation-test

Tools for testing the mediawiki image recommendations api.

See https://phabricator.wikimedia.org/T273062 

## Steps
1. Create a db and initialise it with `sql/imageRecommendations.sql`
1. Run `jobs\PrepareDataForRating.php` to populate the db with a random 500 unillustrated articles from each relevant wiki
3. Point your browser at `public_html\index.html` to rate whether the image recommendations suit the articles 
