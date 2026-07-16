<?php

namespace App\Actions\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class RecordAuditEvent
{
    /**
     * Record an audit event for an administrative action.
     *
     * The acting user is stored via Spatie's native causer relation; system
     * events pass a null actor and are recorded anonymously.
     *
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): Activity {
        $logger = activity();

        if ($subject instanceof Model) {
            $logger->performedOn($subject);
        }

        if ($actor instanceof User) {
            $logger->causedBy($actor);
        } else {
            $logger->causedByAnonymous();
        }

        $logger->withProperties($properties);

        /** @var Activity $activity */
        return $logger->log($action);
    }
}
