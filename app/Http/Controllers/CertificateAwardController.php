<?php

namespace App\Http\Controllers;

use App\Enums\CertificateAwardType;
use App\Http\Requests\CertificateAwardRequest;
use App\Models\CertificateAward;
use App\Services\CertificateAwardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CertificateAwardController extends Controller
{
    public function __construct(public CertificateAwardService $certificateAwardService) {}

    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $typeFilter = in_array($request->input('type'), ['all', ...CertificateAwardType::values()]) ? $request->input('type') : 'all';
        $sortBy = in_array($request->input('sort_column'), ['id', 'type', 'title_ar', 'title_en', 'issuer_ar', 'issuer_en', 'issued_date', 'ordering', 'created_at']) ? $request->input('sort_column') : ($typeFilter === 'all' ? 'type' : 'ordering');
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $certificateAwards = $this->certificateAwardService->getPaginatedCertificateAwards(
            search: $search,
            typeFilter: $typeFilter,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        return Inertia::render('CertificatesAwards/CertificatesAwardsPage', [
            'certificateAwards' => $certificateAwards,
            'filters' => [
                'search' => $search,
                'type' => $typeFilter,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function getNextOrdering(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(CertificateAwardType::class)],
        ]);

        $type = CertificateAwardType::from($validated['type']);

        return response()->json([
            'ordering' => nextOrdering(model: $this->certificateAwardService->orderingQuery($type)),
        ]);
    }

    public function store(CertificateAwardRequest $request)
    {
        $this->certificateAwardService->store(
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(CertificateAwardRequest $request, CertificateAward $certificatesAward)
    {
        $this->certificateAwardService->update(
            certificateAward: $certificatesAward,
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(CertificateAward $certificatesAward)
    {
        $this->certificateAwardService->delete(certificateAward: $certificatesAward);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
