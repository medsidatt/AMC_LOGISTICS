<?php

namespace App\Domain\Operations\Events;

final readonly class RevenueBlocked extends BusinessEvent
{
    public function id(): EventId { return EventId::REVENUE_BLOCKED; }
    public function owner(): BusinessOwner { return BusinessOwner::FINANCE; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::CRITICAL; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::FINANCIAL; }
    public function requiredAction(): string { return 'Complete documents to release revenue'; }
}
