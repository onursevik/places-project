<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlacesExport implements FromCollection, WithHeadings
{
    protected $places;

    public function __construct($places)
    {
        $this->places = $places;
    }

    public function collection()
    {
        return $this->places->map(function ($place) {
            return [
                'name' => $place->name,
                'address' => $place->address,
                'website' => $place->website,
                'emails' => implode(", ", $place->emails ?? []),
                'social_links' => implode(", ", $place->social_links ?? []),
                'types' => implode(", ", $place->types ?? []),
                'lat' => $place->lat,
                'lng' => $place->lng,
                'opening_hours' => implode(", ", $place->opening_hours ?? []),
                'rating' => $place->rating
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Firma Adı',
            'Adres',
            'Web site',
            'E-mailler',
            'Sosyal Medya',
            'Kategoriler',
            'Enlem',
            'Boylam',
            'Açılış Saatleri',
            'Rating'
        ];
    }
}
