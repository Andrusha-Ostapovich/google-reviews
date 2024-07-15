<?php

namespace Ostapovich;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class GoogleReview
{
    protected $client;
    public function __construct()
    {
        $this->client = new Client();
    }
    /**
     * Fetches all reviews from the provided URL.
     *
     * @param string $url The URL to fetch.
     * @param int $page The page number. Defaults to 0.
     * @return array|\Exception The array of reviews or an Exception if an error occurs.
     */
    public function getReviewsAll(string $url, int $page = 0): array|\Exception
    {
        $crawler = $this->getCrawlerObj($url);
        $reviews = $this->getReviews($crawler);

        $nextPageToken = $this->getNextPageToken($crawler);
        $i = 1;

        while ($nextPageToken !== null) {
            // Замінюємо або додаємо next_page_token в середину URL
            $nextPageUrl = preg_replace('/(next_page_token:)[^,]*/', '${1}' . $nextPageToken, $url);

            $nxtPageCrawler = new Crawler($this->getNextReviewsPage($nextPageUrl, $i));
            $reviews = array_merge($reviews, $this->getReviews($nxtPageCrawler));
            $crawler = $nxtPageCrawler;
            $nextPageToken = $this->getNextPageToken($crawler);
            $i++;
            if ($page === 0) {
                $page = $i;
            }
            if ($i > $page) {
                break;
            }
        }

        return $reviews;
    }
    /**
     * Returns a Crawler object for the given URL.
     *
     * @param string $url The URL to fetch.
     * @return Crawler|\Exception The Crawler object or an Exception if an error occurs.
     */
    public function getCrawlerObj(string $url): Crawler|\Exception
    {
        return new Crawler($this->getNextReviewsPage($url));
    }
    /**
     * Fetches the HTML content of the next page of reviews from the given URL.
     *
     * @param string $nextPageUrl The URL of the next page of reviews.
     * @param int $page The current page number. Defaults to 0.
     * @return string|\Exception The HTML content of the next page of reviews, or an exception if an error occurs.
     */
    public function getNextReviewsPage(string $nextPageUrl, int $page = 0): string|\Exception
    {
        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

        try {
            $response = $this->client->request('GET', $nextPageUrl, [
                'headers' => [
                    'User-Agent' => $userAgent,
                ],
            ]);

            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            // Обробка помилок
            return $e->getMessage();
        }
    }
    /**
     * Retrieves the value of the 'data-next-page-token' attribute from the first
     * 'div.gws-localreviews__general-reviews-block' element in the given Crawler
     * object. If the attribute is not found, returns null. If an error occurs,
     * returns an Exception object.
     *
     * @param Crawler $crawler The Crawler object to search for the element.
     * @return string|null|\Exception The value of the 'data-next-page-token'
     * attribute, null if not found, or an Exception object if an error occurs.
     */
    public function getNextPageToken(Crawler $crawler): string|null|\Exception
    {
        return $crawler->filter('div.gws-localreviews__general-reviews-block')->attr('data-next-page-token') ?: null;
    }
    /**
     * Retrieves the rating from the given Crawler object by extracting the text from the first 'span.Aq14fc' element.
     *
     * @param Crawler $crawler The Crawler object containing the HTML content.
     * @return float The rating value as a float, with any commas replaced by dots.
     */
    public function getRating(Crawler $crawler): float|null|\Exception
    {
        return floatval(str_replace(',', '.', $crawler->filter('span.Aq14fc')->first()->text()));
    }
    /**
     * Retrieves all the reviews information from the given Crawler object.
     *
     * @param Crawler $crawler The Crawler object containing the reviews data.
     * @throws \Exception If an error occurs during the retrieval process.
     * @return array An array of reviews with details like id, name, profile URL, rating, text, translation text, reply, translation reply, and profile image.
     */
    public function getReviews(Crawler $crawler): array|\Exception
    {
        $reviews = [];
        $names = $this->getReviewsNames($crawler);
        $texts = $this->getReviewsTexts($crawler);
        $replys = $this->getReviewsReply($crawler);
        $ratings = $this->getReviewsRatings($crawler);
        $profileUrls = $this->getProfilesUrls($crawler);
        $ids = $this->getReviewsIds($crawler);
        $profileImgs = $this->getProfileImg($crawler);
        for ($i = 0; $i < count($names); $i++) {

            $reviews[] = [
                'id' => $ids[$i] ?? null,
                'name' => $names[$i] ?? null,
                'profile_url' => $profileUrls[$i] ?? null,
                'rating' => $ratings[$i] ?? null,
                'text' => $texts[$i * 2 + 1] ?? null,
                'translation_text' => $texts[$i * 2] ?? null,
                'reply' => $replys[$i * 2 + 1] ?? null,
                'translation_reply' => $replys[$i * 2] ?? null,
                'profile_img' => $profileImgs[$i] ?? null
            ];
        }

        return $reviews;
    }
    /**
     * Retrieves the review IDs from the given Crawler object by extracting them from profile URLs.
     *
     * @param Crawler $crawler The Crawler object to extract review IDs from.
     * @return array An array of review IDs.
     */
    public function getReviewsIds(Crawler $crawler): array|\Exception
    {
        $urls = $this->getProfilesUrls($crawler);

        $Ids = [];
        foreach ($urls as $url) {
            preg_match('/\/maps\/contrib\/(\d+)/', $url, $matches);
            if (isset($matches[1])) {
                $Ids[] = $matches[1];
            }
        }
        return $Ids;
    }
    /**
     * Retrieves the total number of reviews from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the reviews count from.
     * @return int|null The total number of reviews.
     * @throws \Exception If an error occurs during the extraction process.
     */
    public function getReviewsCount(Crawler $crawler): int|null|\Exception
    {
        $textElement = $crawler->filter('.z5jxId')->first();
        if ($textElement === null) {
            return null;
        }
        $upd = preg_replace('/\s+/u', '', $textElement->text());
        preg_match('/([\d\s]+)/u', $upd, $matches);
        return $matches[0];
    }
    /**
     * Retrieves the review texts from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the review texts from.
     * @return array The array of review texts.
     * @throws \Exception If an error occurs during the extraction process.
     */
    public function getReviewsTexts(Crawler $crawler): array|\Exception
    {
        $texts = [];
        $crawler->filter('div.k8MTF')->each(function (Crawler $node) {
            $node->getNode(0)->parentNode->removeChild($node->getNode(0));
        });

        $crawler->filter('span[jscontroller="MZnM8e"] span[data-expandable-section]')->each(function (Crawler $node) use (&$texts) {
            $texts[] = $node->text();
        });
        return $texts;
    }
    /**
     * Retrieves the replies from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the replies from.
     * @return array|\Exception The array of replies, or an exception if an error occurs during the extraction process.
     */
    public function getReviewsReply(Crawler $crawler): array|\Exception
    {
        $replys = [];
        $crawler->filter('div[jscontroller="fIQYlf"]')->each(function (Crawler $node) use (&$replys) {
            if ($node->filter('[jscontroller="MZnM8e"]')->count() > 4) {
                $node->filter('.d6SCIc')->each(function (Crawler $childNode) use (&$replys) {
                    $replys[] = $childNode->text();
                });
            } else {
                $replys[] = null;
                $replys[] = null;
            }
        });
        return $replys;
    }
    /**
     * Retrieves the names from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the names from.
     * @return array The array of names.
     * @throws \Exception If an error occurs during the extraction process.
     */
    public function getReviewsNames(Crawler $crawler): array|\Exception
    {
        $names = [];

        $crawler->filter('.TSUbDb a')->each(function (Crawler $node) use (&$names) {
            $names[] = $node->text();
        });
        return $names;
    }
    /**
     * Retrieves the ratings from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the ratings from.
     * @return array The array of ratings.
     * @throws \Exception If an error occurs during the extraction process.
     */
    public function getReviewsRatings(Crawler $crawler): array|\Exception
    {
        $ratings = [];

        $crawler->filter('.lTi8oc.z3HNkc')->each(function (Crawler $node, $i) use (&$ratings) {
            if ($i % 2 === 0) {
                $ariaLabel = $node->attr('aria-label');

                if ($ariaLabel) {
                    if (preg_match('/\d+,\d+/', $ariaLabel, $matches)) {
                        $ratings[] = floatval(preg_replace('/\s+/u', '', $matches[0]));
                    }
                }
            }
        });

        return $ratings;
    }
    /**
     * Retrieves URLs of profiles from the given Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract profile URLs from.
     * @return array The array of profile URLs.
     */
    public function getProfilesUrls(Crawler $crawler): array
    {
        $urls = [];

        $crawler->filter('div.TSUbDb a')->each(function (Crawler $node) use (&$urls) {
            $url = $node->attr('href');
            if ($url) {
                $urls[] = $url;
            }
        });

        return $urls;
    }
    /**
     * Retrieves the URLs of profile images from the given Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract profile image URLs from.
     * @return array The array of profile image URLs.
     */
    public function getProfileImg(Crawler $crawler): array
    {
        $urls = [];

        $crawler->filter('img.lDY1rd')->each(function (Crawler $node) use (&$urls) {
            $urls[] = $node->attr('src');
        });
        return $urls;
    }
}
