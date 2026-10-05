<?php

namespace App\Http\Controllers;

use App\Models\VaccineType;
use Illuminate\View\View;

class VaccineInformationController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->isParent() || auth()->user()->isNurse(), 403);

        $vaccines = VaccineType::query()
            ->where('active', true)
            ->with(['schedules' => fn ($query) => $query->where('active', true)->orderBy('dose_number')])
            ->orderBy('name')
            ->get()
            ->map(function (VaccineType $vaccine): VaccineType {
                $vaccine->parent_information = config("immunization.vaccine_information.{$vaccine->code}", []);
                $summary = strtolower((string) data_get($vaccine->parent_information, 'summary', ''));
                $image = match (true) {
                    str_contains($summary, 'mouth') || str_contains($summary, 'oral') => 'liquid-medicine.jpg',
                    str_contains($summary, 'oblong') || str_contains($summary, 'capsule') => 'oblong-pill-vector.jpg',
                    str_contains($summary, 'pill') || str_contains($summary, 'tablet') => 'round-pills.jpeg',
                    default => 'injection-icon-vector.jpg',
                };
                $vaccine->parent_information_image = asset('storage/'.$image);

                return $vaccine;
            });

        return view('vaccines.index', compact('vaccines'));
    }

    public function show(VaccineType $vaccineType): View
    {
        abort_unless(auth()->user()->isParent() || auth()->user()->isNurse(), 403);

        $vaccineType->load(['schedules' => fn ($query) => $query
            ->where('active', true)
            ->orderBy('dose_number')]);

        $summary = strtolower((string) data_get(config("immunization.vaccine_information.{$vaccineType->code}", []), 'summary', ''));
        $deliveryImage = match (true) {
            str_contains($summary, 'mouth') || str_contains($summary, 'oral') => 'liquid-medicine.jpg',
            str_contains($summary, 'oblong') || str_contains($summary, 'capsule') => 'oblong-pill-vector.jpg',
            str_contains($summary, 'pill') || str_contains($summary, 'tablet') => 'round-pills.jpeg',
            default => 'injection-icon-vector.jpg',
        };

        return view('vaccines.information', [
            'vaccine' => $vaccineType,
            'information' => config("immunization.vaccine_information.{$vaccineType->code}", []),
            'deliveryImage' => asset('storage/'.$deliveryImage),
            'safetyUrl' => 'https://www.who.int/news-room/questions-and-answers/item/vaccines-and-immunization-vaccine-safety',
            'scheduleSource' => config('immunization.source'),
            'scheduleSourceUrl' => config('immunization.source_url'),
        ]);
    }
}
