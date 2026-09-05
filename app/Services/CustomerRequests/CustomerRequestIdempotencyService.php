<?php

namespace App\Services\CustomerRequests;

use App\Enums\CustomerRequests\IntakeAction;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\CustomerRequestIdempotencyKey;
use App\Support\CustomerRequests\CustomerRequestMessages;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Append-only store of accepted HTTP intake tokens. A token that has been
 * accepted for an action must always resolve to that same logical
 * operation, even after customer_requests.submission_token is overwritten
 * by a later retry/confirm on the same row.
 */
class CustomerRequestIdempotencyService
{
    public function find(Customer $customer, IntakeAction $action, string $token): ?CustomerRequestIdempotencyKey
    {
        return CustomerRequestIdempotencyKey::query()
            ->where('customer_id', $customer->id)
            ->where('action', $action)
            ->where('token', $token)
            ->first();
    }

    public function findRequest(Customer $customer, IntakeAction $action, string $token): ?CustomerRequest
    {
        $key = $this->find($customer, $action, $token);
        if ($key === null) {
            return null;
        }

        if ($key->customer_request_id === null) {
            throw ValidationException::withMessages([
                'request_text' => CustomerRequestMessages::requestNoLongerAvailable(),
            ]);
        }

        $request = $key->customerRequest()->first();
        if ($request === null) {
            throw ValidationException::withMessages([
                'request_text' => CustomerRequestMessages::requestNoLongerAvailable(),
            ]);
        }

        return $request;
    }

    public function remember(Customer $customer, CustomerRequest $request, IntakeAction $action, string $token): void
    {
        try {
            $row = new CustomerRequestIdempotencyKey;
            $row->customer_id = $customer->id;
            $row->customer_request_id = $request->id;
            $row->action = $action;
            $row->token = $token;
            $row->save();
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->find($customer, $action, $token);
            if ($existing === null || (int) $existing->customer_request_id !== (int) $request->id) {
                throw $exception;
            }
        }
    }
}
