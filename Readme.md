## Google Reviews Library

This library fetches Google reviews for a specific place using PHP.

### Installation

Install via Composer:

```bash
composer require ostapovich/google-reviews
```

### Usage

For this packege your needs 2 urls.
You can take them according to the following instructions, it is much easier than Google Business

![Step 1](https://imgur.com/BwetPD6.png)

![Step 2](https://i.imgur.com/e8u62KM.png)

![Step 3](https://i.imgur.com/bJBi9xA.png)

Here's a basic example:

```php
<?php

require 'vendor/autoload.php';

use Ostapovich\GoogleReview;

// Initialize the GoogleReview class
$googleReview = new GoogleReview();

// URL of the Google reviews page
$firstUrl = 'https://www.google.com/async/reviewDialog?ei=owqIZv3iLveJxc8PoPCzuAI&opi=89978449&yv=3&cs=1&async=feature_id:0x47e66e2964e34e2d%3A0x8ddca9ee380ef7e0,review_source:All%20reviews,sort_by:qualityScore,is_owner:false,filter_text:,associated_topic:,next_page_token:,async_id_prefix:,_pms:s,_fmt:pc'; // Your First URL here, i add paris
$secondUrl = 'https://www.google.com/async/reviewSort?vet=12ahUKEwj9mbuqlZCHAxWlRvEDHTCpD10Qxyx6BAgBEBU..i&ved=2ahUKEwj9mbuqlZCHAxWlRvEDHTCpD10Qjit6BQgBEKsE&bl=UndH&s=web&opi=89978449&yv=3&cs=1&async=feature_id:0x47e66e2964e34e2d%3A0x8ddca9ee380ef7e0,review_source:All%20reviews,sort_by:newestFirst,is_owner:false,filter_text:,associated_topic:,next_page_token:,_pms:s,_fmt:pc'; // Your Second URL here here, i add paris

try {
    // Fetch all reviews
    $reviews = $googleReview->getReviewsAll($secondUrl, 3); // if you need all reviews do not specify the second parameter

    // Output reviews
    foreach ($reviews as $review) {
        echo "Name: " . $review['name'] . "\n";
        echo "Rating: " . $review['rating'] . "\n";
        echo "Review: " . $review['text'] . "\n\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

// Get general location, rating, and count
$this->getRating($this->getCrawlerObj($firstUrl));
$this->getReviewsCount($this->getCrawlerObj($firstUrl));
```

### Methods

- `getReviewsAll(string $url,int $page = 0): array|\Exception`
  - Fetches all reviews from the provided URL.

- `getCrawlerObj(string $url): Crawler|\Exception`
  - Creates a Crawler object for the provided URL.

- `getNextReviewsPage(string $nextPageUrl, int $page = 0): string|\Exception`
  - Fetches the HTML content of the next page of reviews.

- `getNextPageToken(Crawler $crawler): string|null|\Exception`
  - Extracts the `data-next-page-token` from the HTML content.

- `getRating(Crawler $crawler)`
  - Extracts the overall rating from the HTML content.

- `getReviews(Crawler $crawler): array|\Exception`
  - Extracts the reviews from the HTML content.

- `getReviewsIds(Crawler $crawler): array|\Exception`
  - Extracts the review IDs from the HTML content.

- `getReviewsCount(Crawler $crawler): int|null|\Exception`
  - Extracts the total number of reviews from the HTML content.

- `getReviewsTexts(Crawler $crawler): array|\Exception`
  - Extracts the review texts from the HTML content.

- `getReviewsReply(Crawler $crawler): array|\Exception`
  - Extracts the review replies from the HTML content.

- `getReviewsNames(Crawler $crawler): array|\Exception`
  - Extracts the reviewer names from the HTML content.

- `getReviewsRatings(Crawler $crawler): array|\Exception`
  - Extracts the review ratings from the HTML content.

- `getProfilesUrls(Crawler $crawler): array`
  - Extracts the profile URLs from the HTML content.
