<?php

namespace App\Services;

use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Exceptions\OfferContactRevealLimitException;
use App\Models\Customer;
use App\Models\CustomerOfferContactReveal;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Support\WhatsAppLink;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferContactRevealService
{
    public function limit(): int
    {
        return max(1, (int) config('customer_requests.contact_reveal_limit', 3));
    }

    /**
     * @return array{limit: int, used: int, remaining: int}
     */
    public function quotaSnapshot(CustomerRequest $customerRequest, Customer $customer): array
    {
        $limit = $this->limit();
        $used = $this->distinctRevealedMerchantCount((int) $customerRequest->id, (int) $customer->id);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
        ];
    }

    /**
     * @return list<int>
     */
    public function revealedMerchantIds(CustomerRequest $customerRequest, Customer $customer): array
    {
        return CustomerOfferContactReveal::query()
            ->forRequest($customerRequest)
            ->forCustomer($customer)
            ->pluck('merchant_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function reveal(Customer $customer, MerchantOffer $offer): CustomerOfferContactReveal
    {
        try {
            return DB::transaction(function () use ($customer, $offer) {
                $request = CustomerRequest::query()
                    ->whereKey($offer->customer_request_id)
                    ->lockForUpdate()
                    ->first();

                if ($request === null || (int) $request->customer_id !== (int) $customer->id) {
                    abort(404);
                }

                $lockedOffer = MerchantOffer::query()
                    ->whereKey($offer->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedOffer === null || (int) $lockedOffer->customer_request_id !== (int) $request->id) {
                    abort(404);
                }

                if ((int) $lockedOffer->merchant_id < 1) {
                    abort(404);
                }

                if ($lockedOffer->status !== OfferStatus::Submitted) {
                    abort(404);
                }

                $merchant = Merchant::query()->whereKey($lockedOffer->merchant_id)->first();
                if ($merchant === null) {
                    abort(404);
                }

                $existing = CustomerOfferContactReveal::query()
                    ->forRequest($request)
                    ->forMerchant($merchant)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ((int) $existing->customer_id !== (int) $customer->id) {
                        abort(404);
                    }

                    if ((int) $existing->merchant_offer_id !== (int) $lockedOffer->id) {
                        $existing->merchant_offer_id = $lockedOffer->id;
                        $existing->save();
                    }

                    return $existing;
                }

                $used = $this->distinctRevealedMerchantCount((int) $request->id, (int) $customer->id);

                if ($used >= $this->limit()) {
                    throw OfferContactRevealLimitException::forRequest();
                }

                return CustomerOfferContactReveal::query()->create([
                    'customer_request_id' => $request->id,
                    'merchant_offer_id' => $lockedOffer->id,
                    'merchant_id' => $merchant->id,
                    'customer_id' => $customer->id,
                    'revealed_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $repeat = CustomerOfferContactReveal::query()
                ->where('customer_request_id', $offer->customer_request_id)
                ->where('merchant_id', $offer->merchant_id)
                ->where('customer_id', $customer->id)
                ->first();

            if ($repeat !== null) {
                return $repeat;
            }

            throw OfferContactRevealLimitException::forRequest();
        } catch (OfferContactRevealLimitException $exception) {
            throw ValidationException::withMessages([
                'offer' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{merchant_name: string|null, phone: string|null, whatsapp_mobile_url: string|null, whatsapp_web_url: string|null}
     */
    public function contactPayload(CustomerRequest $customerRequest, MerchantOffer $offer): array
    {
        $merchant = Merchant::query()->whereKey($offer->merchant_id)->first(['id', 'name', 'phone']);
        $urls = $this->customerWhatsAppUrls($customerRequest, $offer, $merchant);

        return [
            'merchant_name' => $merchant?->name,
            'phone' => $merchant?->phone,
            'whatsapp_mobile_url' => $urls['whatsapp_mobile_url'],
            'whatsapp_web_url' => $urls['whatsapp_web_url'],
        ];
    }

    /**
     * @return array{whatsapp_mobile_url: ?string, whatsapp_web_url: ?string}
     */
    public function customerWhatsAppUrls(CustomerRequest $customerRequest, MerchantOffer $offer, ?Merchant $merchant = null): array
    {
        $merchant ??= $offer->merchant;

        if ($merchant !== null && ! $merchant->relationLoaded('categoryAssignments')) {
            $merchant->load([
                'categoryAssignments' => fn ($query) => $query
                    ->where('category_id', $customerRequest->category_id)
                    ->select(['id', 'merchant_id', 'category_id', 'whatsapp_phone']),
            ]);
        }

        $message = $this->customerWhatsAppMessage($customerRequest, $offer);
        $activityPhone = $merchant?->categoryAssignments
            ->firstWhere('category_id', $customerRequest->category_id)
            ?->whatsapp_phone;

        foreach ([$activityPhone, $merchant?->phone] as $phone) {
            $pair = WhatsAppLink::pair($phone, $message);

            if ($pair !== null) {
                return [
                    'whatsapp_mobile_url' => $pair['mobile'],
                    'whatsapp_web_url' => $pair['web'],
                ];
            }
        }

        return [
            'whatsapp_mobile_url' => null,
            'whatsapp_web_url' => null,
        ];
    }

    private function customerWhatsAppMessage(CustomerRequest $customerRequest, MerchantOffer $offer): string
    {
        $reference = (string) $customerRequest->public_id;
        $price = (string) $offer->price;

        if (str_starts_with(strtolower((string) app()->getLocale()), 'ar')) {
            return "مرحبًا، أنا مهتم بالعرض المقدم على طلبي رقم {$reference} بقيمة {$price} OMR.";
        }

        return "Hello, I'm interested in your offer for request {$reference} priced at {$price} OMR.";
    }

    private function distinctRevealedMerchantCount(int $customerRequestId, int $customerId): int
    {
        return (int) CustomerOfferContactReveal::query()
            ->where('customer_request_id', $customerRequestId)
            ->where('customer_id', $customerId)
            ->distinct()
            ->count('merchant_id');
    }
}
