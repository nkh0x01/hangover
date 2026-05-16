<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public static function record(string $actorType, string $action, ?string $subjectType = null, ?int $subjectId = null, array $payload = [], ?int $employeeId = null): void
    {
        static::create([
            'actor_type'        => $actorType,
            'actor_employee_id' => $employeeId,
            'action'            => $action,
            'subject_type'      => $subjectType,
            'subject_id'        => $subjectId,
            'payload_json'      => $payload,
            'ip'                => request()?->ip(),
        ]);
    }
}
