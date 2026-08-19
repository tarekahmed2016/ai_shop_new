<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function __construct(public ServiceService $serviceService) {}

    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $sortBy = in_array($request->input('sort_column'), ['id', 'name_ar', 'name_en', 'ordering', 'created_at']) ? $request->input('sort_column') : 'ordering';
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $services = $this->serviceService->getPaginatedServices(search: $search, sortBy: $sortBy, sortDir: $sortDir);

        return Inertia::render('Services/ServicesPage', [
            'services' => $services,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function getNextOrdering()
    {
        return response()->json([
            'ordering' => nextOrdering(model: $this->serviceService->orderingQuery()),
        ]);
    }

    public function store(ServiceRequest $request)
    {
        $this->serviceService->store(
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(ServiceRequest $request, Service $service)
    {
        $this->serviceService->update(
            service: $service,
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Service $service)
    {
        $this->serviceService->delete(service: $service);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
