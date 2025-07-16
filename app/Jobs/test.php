<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\Places;
use Illuminate\Support\Facades\Cache;

class ScrapeWebsite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $website, $name, $address, $types, $lat, $lng, $openingHours, $businessStatus, $rating;
    public $tries = 2;

    public function __construct($website, $name, $address, $types, $lat, $lng, $openingHours, $businessStatus, $rating)
    {
        $this->website = $website;
        $this->name = $name;
        $this->address = $address;
        $this->types = $types;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->openingHours = $openingHours;
        $this->businessStatus = $businessStatus;
        $this->rating = $rating;
    }

    public function handle(): void
    {
        try {
            if (Cache::has('scraped:' . md5($this->website)) || Places::where('website', $this->website)->exists()) {
                return;
            }

            $res = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . env('ZENROWS_API_KEY'),
            ])->get('https://api.zenrows.com/v1/', [
                'url' => $this->website,
                'js_render' => 'true',
                'premium_proxy' => 'true',
                'antibot' => 'true',
                'retry' => 'true',
                'block_resources' => 'true',
                'custom_headers' => json_encode([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36'
                ])
            ]);

            if (!$res->successful()) return;

            $html = strtolower($res->body());
            $crawler = new Crawler($html);

            $socialLinks = [];
            $emails = [];
            $contactPages = [];

            $crawler->filter('a')->each(function ($node) use (&$contactPages) {
                $href = $node->attr('href');
                $text = strtolower($node->text());

                if ($href && (str_contains($href, 'contact') || str_contains($href, 'iletisim') || str_contains($text, 'contact') || str_contains($text, 'iletişim') || str_contains($text, 'neredeyiz') || str_contains($href, 'neredeyiz') || str_contains($text, 'bize-ulasin') || str_contains($href, 'bize-ulasin') || str_contains($text, 'bize-ulaşın') || str_contains($href, 'bize-ulaşın') || str_contains($text, 'contacts') || str_contains($href, 'contacts'))) {
                    $contactPages[] = $href;
                }
            });

            $contactPages = array_map(function ($link) {
                return str_starts_with($link, 'http') ? $link : rtrim($this->website, '/') . '/' . ltrim($link, '/');
            }, array_unique($contactPages));

            foreach ($contactPages as $url) {
                $html2 =  Http::timeout(60)->withHeaders([
                    'Authorization' => 'Bearer ' . env('ZENROWS_API_KEY'),
                ])->get('https://api.zenrows.com/v1/', [
                    'url' => $url,
                    'js_render' => 'true',
                    'premium_proxy' => 'true',
                    'antibot' => 'true',
                    'retry' => 'true',
                    'block_resources' => 'true',
                    'custom_headers' => json_encode([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36'
                    ]),
                ]);

                $cleanHtml = str_replace(
                    ['[at]', '(at)', '{at}', ' at ', '[dot]', '(dot)', '{dot}', ' dot '],
                    ['@', '@', '@', '@', '.', '.', '.', '.'],
                    strtolower($html2)
                );

                preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $cleanHtml, $matches);
                if (!empty($matches[0])) {
                    $emails = array_merge($emails, $matches[0]);
                }

                $sub = new Crawler($html2);
                $sub->filter('a')->each(function ($node) use (&$emails, &$socialLinks) {
                    $href = $node->attr('href');
                    if ($href) {
                        if ($href !== null && stripos($href, 'mailto:') !== false) {
                            $emails[] = substr($href, 7);
                        }

                        if (preg_match('/(facebook|instagram|twitter|linkedin)\.com/', $href)) {
                            $socialLinks[] = $href;
                        }
                    }
                });
            }

            $create = Places::create([
                'name' => $this->name,
                'address' => $this->address,
                'website' => $this->website,
                'emails' => array_unique(array_filter($emails)),
                'social_links' => array_unique($socialLinks),
                'types' => $this->types,
                'opening_hours' => $this->openingHours,
                'business_status' => $this->businessStatus,
                'rating' => $this->rating,
                'lat' => $this->lat,
                'lng' => $this->lng
            ]);

            if ($create) {
                logger()->error("Scraped for {$this->website}: ");
            } else {
                logger()->error("Not Scraped for {$this->website}: ");
            }

            Cache::put('scraped:' . md5($this->website), true, now()->addDay());
        } catch (\Throwable $e) {
            logger()->error("Scrape failed for {$this->website}: " . $e->getMessage());
        }
    }
}
