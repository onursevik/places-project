<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

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
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Address',
            'Website',
            'Emails',
            'Social Links',
            'Types',
        ];
    }
}
