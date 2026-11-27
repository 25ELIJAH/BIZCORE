<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\Notifier;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /** Keys that must never be persisted, at any nesting depth. */
    private const REDACTED = ['password', 'password_confirmation', 'current_password', 'remember_token', 'token', 'secret'];

    public function __construct(private CurrentCompany $currentCompany, private Request $request) {}

    /**
     * @param  array<string, mixed>  $properties  e.g. ['old' => [...], 'new' => [...]]
     */
    public function log(string $action, ?Model $subject = null, array $properties = [], ?string $description = null, ?int $companyId = null, ?int $userId = null): ActivityLog
    {
        $companyId ??= $this->currentCompany->id();
        $userId ??= Auth::id();

        $entry = ActivityLog::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $this->redact($properties),
            'ip_address' => $this->request->ip(),
        ]);

        // Business events (sales, leave, purchase orders...) also tell the right people. Never fails the caller.
        app(Notifier::class)->notify($action, $subject, $properties, $companyId, $userId);

        return $entry;
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
