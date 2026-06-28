<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Services\QrCodeService;
use Illuminate\View\View;
use Spatie\LaravelPdf\Facades\Pdf;

class VaccineCardController extends Controller
{
    public function show(ChildProfile $child, QrCodeService $qrCodes): View
    {
        $this->authorizeChild($child);

        return view('children.card', $this->cardData($child, $qrCodes));
    }

    public function pdf(ChildProfile $child, QrCodeService $qrCodes)
    {
        $this->authorizeChild($child);

        return Pdf::view('children.card-pdf', $this->cardData($child, $qrCodes))
            ->format('a4')
            ->margins(8, 8, 8, 8)
            ->name("{$child->full_name}-vaccine-card.pdf");
    }

    public function validateToken(string $token, QrCodeService $qrCodes): View
    {
        $child = ChildProfile::query()
            ->where('vaccine_card_token', $token)
            ->with(['barangay', 'vaccinations.vaccineType', 'vaccinations.verifier'])
            ->firstOrFail();

        return view('children.card-validate', $this->cardData($child, $qrCodes));
    }

    /**
     * @return array<string, mixed>
     */
    private function cardData(ChildProfile $child, QrCodeService $qrCodes): array
    {
        $child->loadMissing(['barangay', 'vaccinations.vaccineType', 'vaccinations.verifier']);
        $validationUrl = route('vaccine-cards.validate', $child->vaccine_card_token);

        return [
            'child' => $child,
            'records' => $child->vaccinations->where('verification_status', '!=', 'rejected')->sortByDesc('administered_at')->values(),
            'validationUrl' => $validationUrl,
            'qrCode' => $qrCodes->svgDataUri($validationUrl),
        ];
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(), 403);
    }
}
