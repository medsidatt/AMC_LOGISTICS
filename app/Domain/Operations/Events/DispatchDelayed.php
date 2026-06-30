<?php

namespace App\Domain\Operations\Events;

final readonly class DispatchDelayed extends BusinessEvent
{
    public function id(): EventId { return EventId::DISPATCH_DELAYED; }
    public function owner(): BusinessOwner { return BusinessOwner::DISPATCH; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::HIGH; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'Call the driver or reassign'; }
}
