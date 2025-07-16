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

    public $website, $name, $address;

    public function __construct($website, $name, $address)
    {
        $this->website = $website;
        $this->name = $name;
        $this->address = $address;
    }

    public function handle(): void
    {
        try {
            if (Cache::has('scraped:' . md5($this->website)) || Places::where('website', $this->website)->exists()) {
                return;
            }
            
            $res = Http::timeout(120)
                ->accept('text/html')
                ->withHeaders([
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                    'User-Agent' => 'Mozilla/5.0 (compatible; LaravelScraper/1.0)',
                ])
                ->get('http://api.scraperapi.com', [
                    'api_key' => env('SCRAPER_API_KEY'),
                    'url' => $this->website,
                    'render' => 'true',
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

                if ($href && (str_contains($href, 'contact') || str_contains($href, 'iletisim') || str_contains($text, 'contact') || str_contains($text, 'iletişim') || str_contains($text, 'neredeyiz'))) {
                    $contactPages[] = $href;
                }
            });

            $contactPages = array_map(function ($link) {
                return str_starts_with($link, 'http') ? $link : rtrim($this->website, '/') . '/' . ltrim($link, '/');
            }, array_unique($contactPages));
            
            foreach ($contactPages as $url) {
                $html2 = Http::timeout(120)
                    ->accept('text/html')
                    ->withHeaders([
                        'Accept-Encoding' => 'gzip, deflate',
                        'Connection' => 'keep-alive',
                        'User-Agent' => 'Mozilla/5.0 (compatible; LaravelScraper/1.0)',
                    ])
                    ->get('http://api.scraperapi.com', [
                        'api_key' => env('SCRAPER_API_KEY'),
                        'url' => $url,
                        'render' => 'true',
                    ])->body();

                $cleanHtml = str_replace(
                    ['[at]', '(at)', '{at}', ' at ', '[dot]', '(dot)', '{dot}', ' dot '],
                    ['@', '@', '@', '@', '.', '.', '.', '.'],
                    strtolower($html2)
                );

                preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $cleanHtml, $matches);
                $emails = array_merge($emails, $matches[0]);

                $sub = new Crawler($html2);
                $sub->filter('a')->each(function ($node) use (&$emails, &$socialLinks) {
                    $href = $node->attr('href');
                    if ($href) {
                        if (str_starts_with($href, 'mailto:')) {
                            $emails[] = substr($href, 7);
                        }

                        if (preg_match('/(facebook|instagram|twitter|linkedin)\.com/', $href)) {
                            $socialLinks[] = $href;
                        }
                    }
                });
            }
            
            Places::create([
                'name' => $this->name,
                'address' => $this->address,
                'website' => $this->website,
                'emails' => array_unique(array_filter($emails)),
                'social_links' => array_unique($socialLinks),
            ]);
            
            Cache::put('scraped:' . md5($this->website), true, now()->addDay());
        } catch (\Throwable $e) {
            logger()->error("Scrape failed for {$this->website}: " . $e->getMessage());
        }
    }

}