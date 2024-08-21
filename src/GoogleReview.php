<?php

namespace Ostapovich;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class GoogleReview
{
    protected $client;
    /**
     * Initializes a new instance of the GoogleReview class.
     *
     * @return void
     */
    public function __construct()
    {
        $this->client = new Client();
    }
    /**
     * Fetches all reviews from the provided URL.
     *
     * @param string $url The URL to fetch.
     * @return array|\Exception The array of reviews or an Exception if an error occurs.
     */
    public function getReviewsAll(string $url): array|\Exception
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
        }

        return $reviews;
    }
    /**
     * Returns a Crawler object for the provided URL.
     *
     * @param string $url The URL to fetch the Crawler object for.
     * @throws \Exception If an error occurs while creating the Crawler object.
     * @return Crawler|\Exception The Crawler object or an Exception if an error occurs.
     */
    public function getCrawlerObj(string $url): Crawler|\Exception
    {
        return new Crawler($this->getNextReviewsPage($url));
    }
    /**
     * Retrieves the next page of reviews from the provided URL.
     *
     * @param string $nextPageUrl The URL of the next page of reviews.
     * @param int $page The page number (default: 0)
     * @throws \Exception If an error occurs while fetching the reviews page.
     * @return string The contents of the response body.
     */
    public function getNextReviewsPage(string $nextPageUrl, int $page = 0): string
    {
        try {
            $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

            $response = $this->client->request('GET', $nextPageUrl, [
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Accept-Language' => 'uk-UA,uk;q=0.9,en-US;q=0.8,en;q=0.7',
                ]
            ]);

            // Перевіряємо статус відповіді
            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Failed to fetch the reviews page. Status code: " . $response->getStatusCode());
            }

            // Повертаємо тіло відповіді як рядок
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            throw new \Exception("Error occurred while fetching the reviews page: " . $e->getMessage());
        }
    }
    /**
     * Retrieves the next page token from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the next page token from.
     * @throws \Exception If an error occurs while extracting the next page token.
     * @return string|null The next page token as a string, or null if not found.
     */
    public function getNextPageToken(Crawler $crawler): string|null|\Exception
    {
        return $crawler->filter('div.gws-localreviews__general-reviews-block')->attr('data-next-page-token') ?: null;
    }
    /**
     * Retrieves the overall rating from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the rating from.
     * @throws \Exception If an error occurs while extracting the rating.
     * @return float|null The overall rating as a float value, or null if not found.
     */
    public function getRating(Crawler $crawler): float|null|\Exception
    {
        $ratingNode = $crawler->filter('span.Aq14fc')->first();
        if ($ratingNode->count() > 0) {
            return floatval(str_replace(',', '.', $ratingNode->text()));
        }
        return null;
    }
    /**
     * Retrieves the reviews from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the reviews from.
     * @throws \Exception If an error occurs while extracting the reviews.
     * @return array|\Exception The reviews as an array, or an exception if not found.
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
        $created_at = $this->getReviewsDates($crawler);
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
                'profile_img' => $profileImgs[$i] ?? null,
                'created_at' => $created_at[$i] ?? null,
            ];
        }
        return $reviews;
    }
    /**
     * Retrieves the IDs of the reviews from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the reviews IDs from.
     * @throws \Exception If an error occurs while extracting the reviews IDs.
     * @return array|\Exception The reviews IDs as an array, or an exception if not found.
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
     * @return int|null The total number of reviews as an integer, or null if not found.
     */
    public function getReviewsCount(Crawler $crawler): int|null
    {
        $textElement = $crawler->filter('.z5jxId')->first();

        // Перевірка на наявність елементів
        if ($textElement->count() === 0) {
            return null;
        }

        $upd = preg_replace('/\s+/u', '', $textElement->text());

        // Перевірка наявності збігів
        if (preg_match('/([\d]+)/u', $upd, $matches)) {
            return (int)$matches[0];
        }

        return null;
    }
    /**
     * Retrieves the texts of reviews from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the reviews texts from.
     * @return array|\Exception The reviews texts as an array, or an exception if not found.
     */
    public function getReviewsTexts(Crawler $crawler): array|\Exception
    {
        $texts = [];
        $crawler->filter('div.k8MTF')->each(function (Crawler $node) {
            $node->getNode(0)->parentNode->removeChild($node->getNode(0));
        });

        $crawler->filter('div.jxjCjc')->each(function (Crawler $divNode) use (&$texts) {

            if ($divNode->filter('span[jscontroller="MZnM8e"] span[data-expandable-section]')->count() > 0) {
                $divNode->filter('span[jscontroller="MZnM8e"] span[data-expandable-section]')->each(function (Crawler $node) use (&$texts) {
                    $texts[] = $node->text();
                });
            } else {
                $texts[] = null;
                $texts[] = null;
            }
        });
        return $texts;
    }
    /**
     * Retrieves the replies to reviews from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the replies from.
     * @return array|\Exception The replies as an array, or an exception if not found.
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
     * Retrieves the names of reviewers from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the names from.
     * @return array|\Exception The names as an array, or an exception if not found.
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
     * Retrieves the ratings of reviews from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the ratings from.
     * @return array|\Exception The ratings as an array, or an exception if not found.
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
     * Retrieves a list of URLs for reviewer profiles from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the URLs from.
     * @return array A list of URLs for reviewer profiles.
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
     * Retrieves a list of URLs for reviewer profile images from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the URLs from.
     * @return array A list of URLs for reviewer profile images.
     */
    public function getProfileImg(Crawler $crawler): array
    {
        $urls = [];

        $crawler->filter('img.lDY1rd')->each(function (Crawler $node) use (&$urls) {
            $urls[] = $node->attr('src');
        });
        return $urls;
    }
    /**
     * Retrieves a list of dates for reviews from the provided Crawler object.
     *
     * @param Crawler $crawler The Crawler object to extract the dates from.
     * @throws \Exception If an error occurs during date parsing.
     * @return null|array A list of dates for reviews, or null if no dates are found.
     */
    public function getReviewsDates(Crawler $crawler): null|array|\Exception
    {

        $dates = [];
        $crawler->filter('.jxjCjc')->each(function (Crawler $node) use (&$dates) {
            $dates[] = $this->parseRelativeDate($node->filter('.dehysf.lTi8oc')->text())->toDateTimeString();
        });
        return $dates;
    }
    /**
     * Parses a relative date string and returns a Carbon object representing the date.
     *
     * @param string $relativeDate The relative date string to parse.
     * @return Carbon The Carbon object representing the parsed date.
     * @throws \Exception If the format of the relative date string is invalid.
     */
    public function parseRelativeDate(string $relativeDate): Carbon
    {
        $now = Carbon::now();

        // Обрізаємо всі пробіли
        $relativeDate = trim($relativeDate);

        // Перевіряємо строки
        if (preg_match('/(\d*)\s*(година|години|годину|хвилина|хвилини|хвилину|день|дні|тиждень|тижні|місяць|місяці|рік|роки|років|тому)/u', $relativeDate, $matches)) {
            $value = (int)($matches[1] ?: 1); // Якщо немає числа, припускаємо 1 (як у випадку з "день тому")
            $unit = $matches[2];
            // Перетворення одиниць часу
            switch ($unit) {
                    // Українська мова
                case 'година':
                case 'години':
                case 'годину':
                    return $now->subHours($value);
                case 'хвилина':
                case 'хвилини':
                case 'хвилину':
                    return $now->subMinutes($value);
                case 'день':
                case 'дні':
                    return $now->subDays($value);
                case 'тиждень':
                case 'тижні':
                    return $now->subWeeks($value);
                case 'місяць':
                case 'місяці':
                    return $now->subMonths($value);
                case 'рік':
                case 'роки':
                case 'років':
                    return $now->subYears($value);
            }
        }

        throw new \Exception("Could not parse '$relativeDate': Invalid format.");
    }
}
