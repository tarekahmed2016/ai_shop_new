<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartMerchantRequest;
use App\Services\CategoryService;
use App\Services\MerchantContextService;
use App\Services\MerchantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StartMerchantController extends Controller
{
    public function __construct(
        public MerchantService $merchantService,
        public MerchantContextService $merchantContextService,
        public CategoryService $categoryService,
    ) {}

    public function create(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        return Inertia::render('Account/StartMerchantPage', [
            'availableCategories' => $this->categoryService->activeCategoriesForAssignment(),
        ]);
    }

    public function store(StartMerchantRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $merchant = $this->merchantService->createForUser($user, $request->safe()->only([
            'name',
            'phone',
            'email',
            'category_ids',
        ]));

        $this->merchantContextService->activateByPublicId($user, $merchant->public_id, $request);

        return redirect()->route('merchant.home');
    }
}
