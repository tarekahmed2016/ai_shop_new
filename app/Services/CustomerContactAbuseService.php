<?php

namespace App\Services;

use App\Enums\Customers\Status;
use App\Models\Customer;
use App\Support\CustomerRequests\CustomerRequestMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerContactAbuseService
{
    public const REASON_CONTACT_INFORMATION = 'contact_information_in_request';

    public function __construct(
        public ActivityLogService $activityLogService,
        public ContactInformationScanner $scanner,
    ) {}

    /**
     * @param  list<string>  $types
     */
    public function suspendForContact(Customer $customer, array $types): void
    {
        $types = $this->scanner->sanitizedTypes($types);
        $originalStatus = $customer->status;
        $originalSuspendedAt = $customer->suspended_at;

        $customer->status = Status::Suspended;
        $customer->suspended_at = now();
        $customer->suspension_reason = self::REASON_CONTACT_INFORMATION;
        $customer->suspension_types = $types;
        $customer->save();

        $this->activityLogService->recordChanges(
            subject: $customer,
            originalValues: [
                'status' => $originalStatus,
                'suspended_at' => $originalSuspendedAt,
            ],
            allowedFields: ['status', 'suspended_at'],
            subjectLabel: $customer->display_name,
            metadata: [
                'action' => 'customer.suspended_contact_information',
                'reason' => self::REASON_CONTACT_INFORMATION,
                'types' => $types,
            ],
        );
    }

    public function reactivate(Customer $customer): Customer
    {
        return DB::transaction(function () use ($customer) {
            $originalStatus = $customer->status;
            $originalSuspendedAt = $customer->suspended_at;

            $customer->status = Status::Active;
            $customer->suspended_at = null;
            $customer->save();

            $this->activityLogService->recordChanges(
                subject: $customer,
                originalValues: [
                    'status' => $originalStatus,
                    'suspended_at' => $originalSuspendedAt,
                ],
                allowedFields: ['status', 'suspended_at'],
                subjectLabel: $customer->display_name,
                metadata: [
                    'action' => 'customer.reactivated',
                    'reason' => $customer->suspension_reason,
                    'types' => $this->scanner->sanitizedTypes($customer->suspension_types ?? []),
                ],
            );

            return $customer->fresh();
        });
    }

    public function assertCanCreate(Customer $customer): void
    {
        if ($customer->isSuspended()) {
            throw ValidationException::withMessages([
                'request_text' => CustomerRequestMessages::suspended(),
            ]);
        }

        if (! $customer->isActive()) {
            throw ValidationException::withMessages([
                'customer' => 'Inactive customers cannot create requests.',
            ]);
        }
    }

    /**
     * @param  list<string>  $types
     */
    public function blockAndSuspend(Customer $customer, array $types): never
    {
        $this->suspendForContact($customer, $types);

        throw ValidationException::withMessages([
            'request_text' => CustomerRequestMessages::suspended(),
        ]);
    }
}
