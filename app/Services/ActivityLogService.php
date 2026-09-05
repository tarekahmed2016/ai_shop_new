<?php

namespace App\Services;

use App\Enums\ActivityLogs\Event;
use App\Models\ActivityLog;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ActivityLogService
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_PARTS = [
        'password',
        'secret',
        'token',
        'credential',
    ];

    /**
     * @param  list<string>  $allowedFields
     * @param  array<string, mixed>  $metadata
     */
    public function recordCreated(
        Model $subject,
        array $allowedFields,
        ?string $subjectLabel = null,
        array $metadata = [],
        ?User $actor = null
    ): ActivityLog {
        return $this->record(
            subject: $subject,
            event: Event::Created,
            oldValues: [],
            newValues: $this->safeValues(values: $subject->only($allowedFields), allowedFields: $allowedFields),
            subjectLabel: $subjectLabel,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $originalValues
     * @param  list<string>  $allowedFields
     * @param  array<string, mixed>  $metadata
     */
    /**
     * @param  list<string>  $allowedFields
     * @param  array<string, mixed>  $metadata
     */
    public function recordDeleted(
        Model $subject,
        array $allowedFields,
        ?string $subjectLabel = null,
        array $metadata = [],
        ?User $actor = null
    ): ActivityLog {
        return $this->record(
            subject: $subject,
            event: Event::Deleted,
            oldValues: $this->safeValues(values: $subject->only($allowedFields), allowedFields: $allowedFields),
            newValues: [],
            subjectLabel: $subjectLabel,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $originalValues
     * @param  list<string>  $allowedFields
     * @param  array<string, mixed>  $metadata
     */
    public function recordChanges(
        Model $subject,
        array $originalValues,
        array $allowedFields,
        ?string $subjectLabel = null,
        array $metadata = [],
        ?User $actor = null
    ): ?ActivityLog {
        $changedValues = $subject->getChanges();
        $changedFields = array_values(array_filter(
            $allowedFields,
            fn (string $field) => array_key_exists($field, $changedValues)
                && ! $this->isSensitiveKey($field)
        ));

        if ($changedFields === [] && $metadata === []) {
            return null;
        }

        $oldValues = [];
        $newValues = [];

        foreach ($changedFields as $field) {
            $oldValues[$field] = $originalValues[$field] ?? null;
            $newValues[$field] = $subject->getAttribute($field);
        }

        if ($changedFields === []) {
            $oldValues = [];
            $newValues = [];
        }

        $oldValues = $this->sanitizeValues($oldValues);
        $newValues = $this->sanitizeValues($newValues);

        if ($oldValues === [] && $newValues === [] && $metadata === []) {
            return null;
        }

        return $this->record(
            subject: $subject,
            event: Event::Updated,
            oldValues: $oldValues,
            newValues: $newValues,
            subjectLabel: $subjectLabel,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  list<string>  $allowedFields
     * @param  array<string, mixed>  $metadata
     */
    public function recordSystem(
        Model $subject,
        Event $event,
        array $oldValues = [],
        array $newValues = [],
        array $allowedFields = [],
        ?string $subjectLabel = null,
        array $metadata = []
    ): ActivityLog {
        return $this->createActivityLog(
            subject: $subject,
            event: $event,
            source: ActivityLog::SourceSystem,
            oldValues: $this->safeValues(values: $oldValues, allowedFields: $allowedFields),
            newValues: $this->safeValues(values: $newValues, allowedFields: $allowedFields),
            subjectLabel: $subjectLabel,
            metadata: $metadata,
            actor: null,
            captureRequestContext: false,
        );
    }

    /**
     * Record an explicit admin/operator action that is not a model-field diff.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function recordAction(
        Model $subject,
        Event $event,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?string $subjectLabel = null,
        ?User $actor = null
    ): ActivityLog {
        $oldValues = $this->sanitizeValues($oldValues);
        $newValues = $this->sanitizeValues($newValues);

        if ($oldValues === [] && $newValues === [] && $metadata === []) {
            throw new LogicException('Activity action requires values or metadata.');
        }

        return $this->record(
            subject: $subject,
            event: $event,
            oldValues: $oldValues,
            newValues: $newValues,
            subjectLabel: $subjectLabel,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        Model $subject,
        Event $event,
        array $oldValues,
        array $newValues,
        ?string $subjectLabel,
        array $metadata,
        ?User $actor
    ): ActivityLog {
        return $this->createActivityLog(
            subject: $subject,
            event: $event,
            source: ActivityLog::SourceUser,
            oldValues: $oldValues,
            newValues: $newValues,
            subjectLabel: $subjectLabel,
            metadata: $metadata,
            actor: $this->resolveActor($actor),
            captureRequestContext: true,
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    private function createActivityLog(
        Model $subject,
        Event $event,
        string $source,
        array $oldValues,
        array $newValues,
        ?string $subjectLabel,
        array $metadata,
        ?User $actor,
        bool $captureRequestContext
    ): ActivityLog {
        return ActivityLog::forceCreate([
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'source' => $source,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'subject_label' => $subjectLabel ?? $this->subjectLabel($subject),
            'event' => $event,
            'old_values' => $oldValues === [] ? null : $this->sanitizeValues($oldValues),
            'new_values' => $newValues === [] ? null : $this->sanitizeValues($newValues),
            'ip_address' => $captureRequestContext && app()->bound('request') ? request()->ip() : null,
            'user_agent' => $captureRequestContext && app()->bound('request') ? request()->userAgent() : null,
            'metadata' => $metadata === [] ? null : $this->sanitizeValues($metadata),
        ]);
    }

    private function resolveActor(?User $actor): User
    {
        if ($actor) {
            return $actor;
        }

        $authenticatedUser = auth()->user();

        if (! $authenticatedUser instanceof User) {
            throw new LogicException('User activity requires an authenticated actor.');
        }

        return $authenticatedUser;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowedFields
     * @return array<string, mixed>
     */
    private function safeValues(array $values, array $allowedFields): array
    {
        $allowedValues = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $values) && ! $this->isSensitiveKey($field)) {
                $allowedValues[$field] = $values[$field];
            }
        }

        return $this->sanitizeValues($allowedValues);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitizeValues(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = $this->normalizeValue($value);
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        return Str::contains(Str::lower($key), self::SENSITIVE_KEY_PARTS);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_array($value)) {
            return $this->sanitizeValues($value);
        }

        return $value;
    }

    private function subjectLabel(Model $subject): string
    {
        foreach (['name', 'email', 'title'] as $attribute) {
            if (array_key_exists($attribute, $subject->getAttributes()) && $subject->getAttribute($attribute)) {
                return (string) $subject->getAttribute($attribute);
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }
}
