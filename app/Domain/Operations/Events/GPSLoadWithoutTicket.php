<?php

namespace App\Domain\Operations\Events;

final readonly class GPSLoadWithoutTicket extends BusinessEvent
{
    public function id(): EventId { return EventId::GPS_LOAD_WITHOUT_TICKET; }
    public function owner(): BusinessOwner { return BusinessOwner::OPERATIONS; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::HIGH; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::FINANCIAL; }
    public function requiredAction(): string { return 'Create the missing ticket'; }
}
