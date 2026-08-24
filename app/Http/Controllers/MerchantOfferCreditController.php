<?php

namespace App\Http\Controllers;

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use App\Http\Requests\MerchantOfferCreditAddRequest;
use App\Http\Requests\MerchantOfferCreditBulkAddRequest;
use App\Http\Requests\MerchantOfferCreditDeductRequest;
use App\Http\Requests\MerchantOfferCreditEnforcementRequest;
use App\Models\Merchant;
use App\Services\MerchantOfferCreditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantOfferCreditController extends Controller
{
    public function __construct(
        public MerchantOfferCreditService $merchantOfferCreditService,
    ) {}

    public function transactions(Request $request)
    {
        $this->authorize('viewCreditHistory', Merchant::class);

        $filters = $this->ledgerFilters($request);

        $transactions = $this->merchantOfferCreditService->paginatedGlobalLedger($filters);
        $transactions->getCollection()->transform(fn ($transaction) => $this->presentTransaction($transaction, includeMerchant: true));

        return Inertia::render('Merchants/MerchantCreditTransactionsPage', [
            'transactions' => $transactions,
            'summary' => $this->merchantOfferCreditService->globalLedgerSummary($filters),
            'merchants' => Merchant::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'public_id', 'name']),
            'filterSources' => TransactionSource::toArray(),
            'filterTypes' => TransactionType::toArray(),
            'filters' => $this->presentedFilters($filters),
        ]);
    }

    public function index(Request $request, Merchant $merchant)
    {
        $this->authorize('viewCredits', $merchant);

        $filters = $this->ledgerFilters($request);

        $transactions = $this->merchantOfferCreditService->paginatedLedger($merchant, $filters);
        $transactions->getCollection()->transform(fn ($transaction) => $this->presentTransaction($transaction));

        return Inertia::render('Merchants/MerchantCreditsPage', [
            'merchant' => [
                'id' => $merchant->id,
                'public_id' => $merchant->public_id,
                'name' => $merchant->name,
            ],
            'balance' => $this->merchantOfferCreditService->balance((int) $merchant->id),
            'enforcement_enabled' => $this->merchantOfferCreditService->isEnforcementEnabled(),
            'transactions' => $transactions,
            'sources' => TransactionSource::manualChoicesToArray(),
            'filterSources' => TransactionSource::toArray(),
            'filterTypes' => TransactionType::toArray(),
            'filters' => $this->presentedFilters($filters),
            'permissions' => [
                'add' => request()->user()?->can('addCredits', $merchant) === true,
                'deduct' => request()->user()?->can('deductCredits', $merchant) === true,
            ],
        ]);
    }

    public function store(MerchantOfferCreditAddRequest $request, Merchant $merchant)
    {
        $data = $request->validated();
        $source = TransactionSource::from((int) $data['source']);

        $this->merchantOfferCreditService->addCredits(
            merchant: $merchant,
            amount: (int) $data['amount'],
            source: $source,
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            actor: $request->user(),
            paidAmount: $data['paid_amount'] ?? null,
        );

        return redirect()
            ->route('merchants.credits.index', $merchant)
            ->with('success', 'تم إضافة رصيد العروض بنجاح');
    }

    public function deduct(MerchantOfferCreditDeductRequest $request, Merchant $merchant)
    {
        $data = $request->validated();
        $source = TransactionSource::from((int) $data['source']);

        $this->merchantOfferCreditService->deductCredits(
            merchant: $merchant,
            amount: (int) $data['amount'],
            source: $source,
            notes: (string) $data['notes'],
            reference: $data['reference'] ?? null,
            actor: $request->user(),
        );

        return redirect()
            ->route('merchants.credits.index', $merchant)
            ->with('success', 'تم خصم رصيد العروض بنجاح');
    }

    public function bulkStore(MerchantOfferCreditBulkAddRequest $request)
    {
        $data = $request->validated();
        $source = TransactionSource::from((int) $data['source']);

        $merchantIds = Merchant::query()
            ->whereIn('public_id', $data['merchant_public_ids'])
            ->pluck('id')
            ->all();

        $result = $this->merchantOfferCreditService->bulkAdd(
            merchantIds: $merchantIds,
            amount: (int) $data['amount'],
            source: $source,
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            actor: $request->user(),
            paidAmount: $data['paid_amount'] ?? null,
        );

        return redirect()
            ->back()
            ->with('success', $this->bulkSuccessMessage($result['amount'], $result['merchant_count']));
    }

    public function updateEnforcement(MerchantOfferCreditEnforcementRequest $request)
    {
        $enabled = $request->boolean('enabled');
        $this->merchantOfferCreditService->setEnforcementEnabled($enabled);

        return redirect()
            ->back()
            ->with('success', $enabled ? 'تم تفعيل رصيد تقديم العروض' : 'تم إيقاف رصيد تقديم العروض');
    }

    private function bulkSuccessMessage(int $amount, int $count): string
    {
        if (str_starts_with(strtolower((string) app()->getLocale()), 'ar')) {
            return "تمت إضافة {$amount} عرضًا إلى {$count} تاجرًا بنجاح.";
        }

        return "{$amount} offer credits were added to {$count} merchants successfully.";
    }

    /**
     * @return array{merchant: mixed, type: mixed, source: mixed, date_from: mixed, date_to: mixed, paid_only: mixed}
     */
    private function ledgerFilters(Request $request): array
    {
        return [
            'merchant' => $request->input('merchant'),
            'type' => $request->input('type'),
            'source' => $request->input('source'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'paid_only' => $request->input('paid_only'),
        ];
    }

    /**
     * @param  array{merchant: mixed, type: mixed, source: mixed, date_from: mixed, date_to: mixed, paid_only: mixed}  $filters
     * @return array{merchant: ?string, type: ?int, source: ?int, date_from: ?string, date_to: ?string, paid_only: bool}
     */
    private function presentedFilters(array $filters): array
    {
        return [
            'merchant' => $filters['merchant'] !== null && $filters['merchant'] !== '' ? (string) $filters['merchant'] : null,
            'type' => $filters['type'] !== null && $filters['type'] !== '' ? (int) $filters['type'] : null,
            'source' => $filters['source'] !== null && $filters['source'] !== '' ? (int) $filters['source'] : null,
            'date_from' => $filters['date_from'] ?: null,
            'date_to' => $filters['date_to'] ?: null,
            'paid_only' => in_array($filters['paid_only'], [true, 1, '1', 'true', 'on', 'yes'], true),
        ];
    }

    private function presentTransaction($transaction, bool $includeMerchant = false)
    {
        $createdBy = $transaction->createdBy;
        $transaction->setAttribute('created_by', $createdBy === null ? null : [
            'id' => $createdBy->id,
            'name' => $createdBy->name,
            'email' => $createdBy->email,
        ]);
        $transaction->unsetRelation('createdBy');

        $transaction->setAttribute(
            'paid_amount',
            $this->merchantOfferCreditService->formatPaidAmount($transaction->paid_amount),
        );

        if ($includeMerchant) {
            $merchant = $transaction->merchant;
            $transaction->setAttribute('merchant', $merchant === null ? null : [
                'id' => $merchant->id,
                'public_id' => $merchant->public_id,
                'name' => $merchant->name,
            ]);
            $transaction->unsetRelation('merchant');
        }

        return $transaction;
    }
}
