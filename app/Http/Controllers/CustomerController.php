<?php

namespace App\Http\Controllers;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\Customers\Status;
use App\Http\Requests\CustomerDailyRequestLimitRequest;
use App\Http\Requests\CustomerEnablePortalRequest;
use App\Http\Requests\CustomerFormRequest;
use App\Models\Customer;
use App\Services\CustomerDailyRequestLimitAuditService;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function __construct(
        public CustomerService $customerService,
        public CustomerDailyRequestLimitAuditService $customerDailyRequestLimitAuditService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $globalLimitHistory = $this->customerDailyRequestLimitAuditService
            ->paginatedGlobalLimitChanges()
            ->through(fn ($change) => $this->customerDailyRequestLimitAuditService->presentGlobalChange($change));

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
            'dailyCustomerRequestLimit' => $this->customerService->customerRequestLimitService->globalLimit(),
            'dailyCustomerRequestTimezone' => $this->customerService->customerRequestLimitService->timezone(),
            'globalLimitHistory' => $globalLimitHistory,
            'extraRequestSources' => TransactionSource::manualChoicesToArray(),
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

    public function enablePortal(CustomerEnablePortalRequest $request, Customer $customer)
    {
        $this->customerService->createLoginAccount($customer, $request->validated());

        return redirect()->back()->with('success', 'تم تفعيل دخول بوابة العميل');
    }

    public function updateDailyLimit(CustomerDailyRequestLimitRequest $request)
    {
        $this->authorize('manageLimits', Customer::class);
        $this->customerService->updateDailyLimit(
            (int) $request->validated('daily_limit'),
            $request->validated('notes'),
        );

        return redirect()->back()->with('success', 'تم تحديث الحد اليومي لطلبات العملاء');
    }

    public function dailyLimitHistory(Customer $customer)
    {
        $this->authorize('view', $customer);

        $changes = $this->customerDailyRequestLimitAuditService
            ->paginatedForCustomer($customer)
            ->through(fn ($change) => $this->customerDailyRequestLimitAuditService->presentChange($change));

        return Inertia::render('Customers/CustomerDailyLimitHistoryPage', [
            'customer' => [
                'id' => $customer->id,
                'public_id' => $customer->public_id,
                'name' => $customer->display_name,
            ],
            'globalLimit' => $this->customerService->customerRequestLimitService->globalLimit(),
            'changes' => $changes,
        ]);
    }

    public function reactivate(Customer $customer)
    {
        $this->authorize('update', $customer);
        $this->customerService->reactivate($customer);

        return redirect()->back()->with('success', 'تم إعادة تفعيل العميل');
    }
}
