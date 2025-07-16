<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Places;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Jobs\ScrapeWebsite;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlacesExport;

class PlaceController extends Controller
{
    public function getPlaces(Request $request)
    {
        Cache::flush();

        $query = $request->get('query', 'tarım makinaları');
        //$city = $request->get('city', 'izmir');
        $language = $request->get('language', 'en');
        $region = $request->get('region', 'ge');
        $limit = (int)$request->get('limit', 20);
        $count = 0;

        $allPlaces = [];
        $nextPageToken = null;
        $repeat = 0;

        do {
            $params = [
                'query' => "$query",
                'key' => env('GOOGLE_PLACES_API_KEY'),
                'language' => $language,
                'region' => $region
            ];

            if ($nextPageToken) {
                $params['pagetoken'] = $nextPageToken;
                sleep(2);
            }

            $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', $params);
            $data = $response->json();

            $allPlaces = array_merge($allPlaces, $data['results'] ?? []);
            $nextPageToken = $data['next_page_token'] ?? null;
            $repeat++;

        } while ($nextPageToken && $repeat < 3 && count($allPlaces) < $limit);

        foreach ($allPlaces as $place) {
            if ($count >= $limit) break;

            $name = $place['name'];
            $address = $place['formatted_address'] ?? '';
            $businessStatus = $place['business_status'] ?? '';
            $rating = $place['rating'] ?? 0;
            $placeId = $place['place_id'];

            $details = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'website,name,formatted_address,types,geometry,opening_hours',
                'key' => env('GOOGLE_PLACES_API_KEY')
            ])->json();

            $website = $details['result']['website'] ?? null;
            $types = $details['result']['types'] ?? [];
            $openingHours = $details['result']['opening_hours']['weekday_text'] ?? [];
            $lat = $details['result']['geometry']['location']['lat'];
            $lng = $details['result']['geometry']['location']['lng'];

            if ($website) {
                ScrapeWebsite::dispatch(
                    $website,
                    $name,
                    $address,
                    $types,
                    $lat,
                    $lng,
                    $openingHours,
                    $businessStatus,
                    $rating
                );

                $count++;
            }
        }

        return "Yerler alındı ($count adet), scraper kuyruğa gönderildi.";
    }

    public function export(Request $request)
    {
        $query = Places::query();

        if ($request->filled('city')) {
            $query->where('address', 'like', '%' . $request->city . '%');
        }

        if ($request->filled('type')) {
            $query->whereJsonContains('types', $request->type);
        }

        $places = $query->get();

        return Excel::download(new PlacesExport($places), 'places_filtered_' . date('d_m_Y') . '.xlsx');
    }
}
