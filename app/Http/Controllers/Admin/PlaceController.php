<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Jobs\ScrapeWebsite;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlacesExport;

class PlaceController extends Controller
{
    public function getPlaces()
    {
        Cache::flush();
        
        $limit = 15;
        $count = 0;
        
        $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
            'query' => 'agricultural machinery spare parts',
            'region' => 'ge',
            'language' => 'en',
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
                'fields' => 'website,name,formatted_address,types',
                'key' => env('GOOGLE_PLACES_API_KEY')
            ])->json();

            $website = $details['result']['website'] ?? null;
            $types = $details['result']['types'] ?? [];

            if ($website) {
                ScrapeWebsite::dispatch($website, $name, $address, $types);

                $count++;
            }
        }

        return "Yerler alındı ($count adet), scraper kuyruğa gönderildi.";
    }

    public function export()
    {
        return Excel::download(new PlacesExport, 'places.xlsx');
    }
}
