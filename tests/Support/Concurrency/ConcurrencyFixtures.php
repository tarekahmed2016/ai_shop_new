<?php

namespace Tests\Support\Concurrency;

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\CustomerRequests\AiStage;
use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\RequestClassifications\Status as ClassificationStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantOffer;
use App\Models\RequestClassification;
use App\Models\User;
use Illuminate\Support\Str;

final class ConcurrencyFixtures
{
    /**
     * @return array{user: User, customer: Customer}
     */
    public static function customer(array $userAttrs = []): array
    {
        $user = User::factory()->create($userAttrs);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => CustomerStatus::Active,
        ]);

        return compact('user', 'customer');
    }

    public static function category(): Category
    {
        return Category::factory()->create(['status' => CategoryStatus::Active]);
    }

    public static function merchantForCategory(Category $category): Merchant
    {
        $merchant = Merchant::factory()->create(['status' => MerchantStatus::Active]);
        MerchantCategory::factory()->create([
            'merchant_id' => $merchant->id,
            'category_id' => $category->id,
        ]);

        return $merchant;
    }

    public static function processingRequest(Customer $customer, ?string $token = null, ?string $jobToken = null): CustomerRequest
    {
        return CustomerRequest::factory()->create([
            'customer_id' => $customer->id,
            'source' => Source::Web,
            'status' => RequestStatus::PendingClassification,
            'request_text' => 'ABS Sensor for my car',
            'submission_token' => $token ?? (string) Str::ulid(),
            'ai_stage' => AiStage::QueuedClassification,
            'ai_job_token' => $jobToken ?? (string) Str::ulid(),
            'ai_stage_updated_at' => now(),
            'ai_attempts' => 0,
            'quota_consumed_at' => null,
        ]);
    }

    public static function queuedDuplicateCheck(
        Customer $customer,
        Category $category,
        string $jobToken,
    ): CustomerRequest {
        $request = CustomerRequest::factory()->create([
            'customer_id' => $customer->id,
            'source' => Source::Web,
            'status' => RequestStatus::PendingClassification,
            'request_text' => 'ABS Sensor for my car',
            'category_id' => $category->id,
            'ai_stage' => AiStage::QueuedDuplicateCheck,
            'ai_job_token' => $jobToken,
            'ai_stage_updated_at' => now(),
            'ai_attempts' => 0,
            'quota_consumed_at' => null,
            'confirmed_category_id' => $category->id,
        ]);

        $classification = RequestClassification::factory()->create([
            'customer_request_id' => $request->id,
            'suggested_category_id' => $category->id,
            'status' => ClassificationStatus::Suggested,
            'detected_item' => 'ABS Sensor',
            'confidence' => 0.9,
        ]);

        $request->confirmed_classification_id = $classification->id;
        $request->save();

        return $request;
    }

    /**
     * @return array{request: CustomerRequest, classification: RequestClassification}
     */
    public static function readyForFinalize(
        Customer $customer,
        Category $category,
        string $jobToken,
    ): array {
        $request = CustomerRequest::factory()->create([
            'customer_id' => $customer->id,
            'source' => Source::Web,
            'status' => RequestStatus::PendingClassification,
            'request_text' => 'ABS Sensor for my car',
            'category_id' => $category->id,
            'ai_stage' => AiStage::QueuedFinalDuplicateCheck,
            'ai_job_token' => $jobToken,
            'ai_stage_updated_at' => now(),
            'ai_attempts' => 0,
            'quota_consumed_at' => null,
            'confirmed_category_id' => $category->id,
        ]);

        $classification = RequestClassification::factory()->create([
            'customer_request_id' => $request->id,
            'suggested_category_id' => $category->id,
            'status' => ClassificationStatus::Suggested,
            'detected_item' => 'ABS Sensor',
            'confidence' => 0.9,
        ]);

        $request->confirmed_classification_id = $classification->id;
        $request->save();

        return compact('request', 'classification');
    }

    public static function readyRequest(Customer $customer, Category $category): CustomerRequest
    {
        return CustomerRequest::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'status' => RequestStatus::Ready,
            'source' => Source::Web,
            'request_text' => 'Need a replacement screen',
            'ai_stage' => AiStage::Ready,
            'quota_consumed_at' => now(),
            'matching_completed_at' => null,
            'matching_last_attempt_at' => null,
        ]);
    }

    public static function submittedOffer(CustomerRequest $request, Merchant $merchant): MerchantOffer
    {
        return MerchantOffer::factory()->create([
            'customer_request_id' => $request->id,
            'merchant_id' => $merchant->id,
            'status' => OfferStatus::Submitted,
            'availability_status' => AvailabilityStatus::Available,
            'submitted_at' => now(),
        ]);
    }
}
