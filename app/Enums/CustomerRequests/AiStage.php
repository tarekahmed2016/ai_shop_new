<?php

namespace App\Enums\CustomerRequests;

/**
 * Pipeline state for the async AI classification / duplicate-check /
 * finalization flow. Only meaningful while CustomerRequest::status is
 * PendingClassification; always null for Source::Admin rows and for rows
 * created before this pipeline existed (legacy rows — see
 * CustomerRequestAiStageService::legacyStageFor()).
 */
enum AiStage: string
{
    case QueuedClassification = 'queued_classification';
    case Classifying = 'classifying';
    case QueuedDuplicateCheck = 'queued_duplicate_check';
    case CheckingDuplicate = 'checking_duplicate';
    case ReadyForReview = 'ready_for_review';
    case QueuedFinalDuplicateCheck = 'queued_final_duplicate_check';
    case CheckingFinalDuplicate = 'checking_final_duplicate';
    case Finalizing = 'finalizing';
    case Ready = 'ready';
    case Failed = 'failed';
    case DuplicateBlocked = 'duplicate_blocked';
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public static function classificationInFlight(): array
    {
        return [self::QueuedClassification, self::Classifying, self::QueuedDuplicateCheck, self::CheckingDuplicate];
    }

    /**
     * @return list<self>
     */
    public static function finalizationInFlight(): array
    {
        return [self::QueuedFinalDuplicateCheck, self::CheckingFinalDuplicate, self::Finalizing];
    }

    /**
     * @return list<self>
     */
    public static function anyInFlight(): array
    {
        return [...self::classificationInFlight(), ...self::finalizationInFlight()];
    }

    public function isClassificationInFlight(): bool
    {
        return in_array($this, self::classificationInFlight(), true);
    }

    public function isFinalizationInFlight(): bool
    {
        return in_array($this, self::finalizationInFlight(), true);
    }

    /**
     * A row the customer may act on again (retry classification).
     */
    public function isTerminalClassification(): bool
    {
        return $this === self::ReadyForReview || $this === self::Failed;
    }

    /**
     * Rows that still count against the per-customer open-attempt ceiling
     * (everything that hasn't reached an accepted/terminal-resolved state).
     */
    public function isOpenAttempt(): bool
    {
        return ! in_array($this, [self::Ready, self::DuplicateBlocked, self::Expired], true);
    }
}
