<?php

namespace App\Http\Controllers;

use App\Enums\Customers\Status;
use App\Http\Requests\CustomerFormRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function __construct(public CustomerService $customerService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Customers/CustomersPage', [
            'customers' => $this->customerService->getPaginatedCustomers(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
            ),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'statuses' => Status::toArray(),
        ]);
    }

    public function store(CustomerFormRequest $request)
    {
        $this->customerService->store(data: $request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(CustomerFormRequest $request, Customer $customer)
    {
        $this->customerService->update(customer: $customer, data: $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
