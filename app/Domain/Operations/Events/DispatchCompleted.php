<?php

namespace App\Domain\Operations\Events;

final readonly class DispatchCompleted extends BusinessEvent
{
    public function id(): EventId { return EventId::DISPATCH_COMPLETED; }
    public function owner(): BusinessOwner { return BusinessOwner::DISPATCH; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::INFORMATIONAL; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'None'; }
}
