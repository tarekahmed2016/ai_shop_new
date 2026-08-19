<?php

namespace App\Http\Controllers;

use App\Enums\ClientPartnerType;
use App\Http\Requests\ClientPartnerRequest;
use App\Models\ClientPartner;
use App\Services\ClientPartnerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClientPartnerController extends Controller
{
    public function __construct(public ClientPartnerService $clientPartnerService) {}

    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $typeFilter = in_array($request->input('type'), ['all', ...ClientPartnerType::values()]) ? $request->input('type') : 'all';
        $sortBy = in_array($request->input('sort_column'), ['id', 'type', 'name_ar', 'name_en', 'website', 'ordering', 'created_at']) ? $request->input('sort_column') : ($typeFilter === 'all' ? 'type' : 'ordering');
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $clientPartners = $this->clientPartnerService->getPaginatedClientPartners(
            search: $search,
            typeFilter: $typeFilter,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        return Inertia::render('ClientsPartners/ClientsPartnersPage', [
            'clientPartners' => $clientPartners,
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
            'type' => ['required', Rule::enum(ClientPartnerType::class)],
        ]);

        $type = ClientPartnerType::from($validated['type']);

        return response()->json([
            'ordering' => nextOrdering(model: $this->clientPartnerService->orderingQuery($type)),
        ]);
    }

    public function store(ClientPartnerRequest $request)
    {
        $this->clientPartnerService->store(
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(ClientPartnerRequest $request, ClientPartner $clientsPartner)
    {
        $this->clientPartnerService->update(
            clientPartner: $clientsPartner,
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(ClientPartner $clientsPartner)
    {
        $this->clientPartnerService->delete(clientPartner: $clientsPartner);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
