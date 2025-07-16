<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Jobs\ScrapeWebsite;

class PlaceController extends Controller
{
    public function getPlaces()
    {
        $limit = 20;
        $count = 0;

        $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
            'query' => 'kafe izmir',
            'components' => 'country:TR',
            'key' => env('GOOGLE_PLACES_API_KEY')
        ]);

        $places = $response->json()['results'] ?? [];

        foreach ($places as $place) {
            if ($count >= $limit) break;

            $name = $place['name'];
            $address = $place['formatted_address'] ?? '';
            $placeId = $place['place_id'];

            $details = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'website,name,formatted_address',
                'key' => env('GOOGLE_PLACES_API_KEY')
            ])->json();

            $website = $details['result']['website'] ?? null;

            if ($website) {
                ScrapeWebsite::dispatch($website, $name, $address);
                
                $count++;
            }
        }

        return "Yerler alındı ($count adet), scraper kuyruğa gönderildi.";
    }
}
