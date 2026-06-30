<?php

namespace App\Domain\Operations\Events;

final readonly class BillingBlocked extends BusinessEvent
{
    public function id(): EventId { return EventId::BILLING_BLOCKED; }
    public function owner(): BusinessOwner { return BusinessOwner::FINANCE; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::HIGH; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::FINANCIAL; }
    public function requiredAction(): string { return 'Complete the blocking documents'; }
}
