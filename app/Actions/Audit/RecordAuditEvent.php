<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RecordAuditEvent
{
    /**
     * Record an audit event for a team-scoped administrative action.
     *
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        Team $team,
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        return AuditLog::create([
            'team_id' => $team->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Resolve actor, IP, and user-agent from the incoming HTTP request.
     *
     * @return array{0: ?User, 1: ?string, 2: ?string}
     */
    public function fromRequest(Request $request): array
    {
        return [
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        ];
    }
}
