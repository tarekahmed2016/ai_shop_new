<?php

namespace App\Services;

use App\Enums\Categories\Status;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MerchantCategoryService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'merchant_id',
        'category_id',
        'whatsapp_phone',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public RequestMatchingService $requestMatchingService,
    ) {}

    public function getPaginatedAssignments(
        Merchant $merchant,
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['created_at', 'id'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return MerchantCategory::query()
            ->where('merchant_id', $merchant->id)
            ->with('category:id,public_id,name_ar,name_en,status')
            ->when($search, function ($q) use ($search) {
                $q->whereHas('category', function ($query) use ($search) {
                    $query->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function attach(Merchant $merchant, string $categoryPublicId, ?string $whatsappPhone = null): MerchantCategory
    {
        $category = Category::query()->where('public_id', $categoryPublicId)->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category is invalid.',
            ]);
        }

        if ($category->status !== Status::Active) {
            throw ValidationException::withMessages([
                'category_id' => 'Only active categories can be assigned.',
            ]);
        }

        $exists = MerchantCategory::query()
            ->where('merchant_id', $merchant->id)
            ->where('category_id', $category->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'category_id' => 'This category is already assigned to the merchant.',
            ]);
        }

        $assignment = new MerchantCategory;
        $assignment->merchant_id = $merchant->id;
        $assignment->category_id = $category->id;
        $assignment->whatsapp_phone = $this->normalizedStoredPhone($whatsappPhone);
        $assignment->save();

        $this->activityLogService->recordCreated(
            subject: $assignment,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $merchant->name.' / '.$category->name_en,
        );

        return $assignment;
    }

    public function detach(Merchant $merchant, MerchantCategory $assignment): void
    {
        if ($assignment->merchant_id !== $merchant->id) {
            abort(404);
        }

        $assignment->loadMissing('category');

        $this->activityLogService->recordDeleted(
            subject: $assignment,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $merchant->name.' / '.($assignment->category?->name_en ?? 'category'),
        );

        $categoryId = $assignment->category_id;
        $assignment->delete();

        $this->requestMatchingService->removeMatchesForMerchantCategory($merchant, (int) $categoryId);
    }

    public function updateWhatsappPhone(Merchant $merchant, MerchantCategory $assignment, string $whatsappPhone): MerchantCategory
    {
        if ($assignment->merchant_id !== $merchant->id) {
            abort(404);
        }

        $originalValues = $assignment->only(self::ACTIVITY_FIELDS);
        $assignment->whatsapp_phone = $this->normalizedStoredPhone($whatsappPhone);
        $assignment->save();

        $assignment->loadMissing('category');

        $this->activityLogService->recordChanges(
            subject: $assignment,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $merchant->name.' / '.($assignment->category?->name_en ?? 'category'),
        );

        return $assignment;
    }

    private function normalizedStoredPhone(?string $whatsappPhone): ?string
    {
        if ($whatsappPhone === null) {
            return null;
        }

        $trimmed = trim($whatsappPhone);

        return $trimmed === '' ? null : $trimmed;
    }
}
