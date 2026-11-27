<?php

namespace App\Support;

use App\Mail\BusinessEventMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tells the right people when something happens in the business: a sale, a leave request, a purchase order
 * waiting for approval, stock running low. Each notice lands in the person's in-app inbox (the bell) and,
 * when the company has that event's email switched on, in their mailbox too.
 *
 * Who hears about what is decided by permission, not by a fixed list of people, so it follows whatever roles
 * the company has set up. A notice never reaches the person who caused it, and a failure here (no mail
 * server, missing table) is logged and swallowed: it must never break the sale or approval that triggered it.
 */
class Notifier
{
    /**
     * event => label (shown on the Company page), default for email, who is told.
     * 'permission' => anyone whose role holds it; 'recipients' => a method that names people for this event.
     *
     * @var array<string, array{label: string, email: bool, permission?: string, recipients?: string}>
     */
    public const EVENTS = [
        'sale.created' => ['label' => 'A sale is made', 'email' => true, 'permission' => 'activity_logs.view'],
        'sale.cancelled' => ['label' => 'A sale is cancelled', 'email' => true, 'permission' => 'activity_logs.view'],
        'payment.recorded' => ['label' => 'A customer payment is recorded', 'email' => false, 'permission' => 'finance.manage'],
        'leave.requested' => ['label' => 'An employee asks for leave', 'email' => true, 'permission' => 'leave.approve'],
        'leave.decided' => ['label' => 'Leave is approved or rejected (tells the employee)', 'email' => true, 'recipients' => 'leavePeople'],
        'purchase_order.submitted' => ['label' => 'A purchase order needs approval', 'email' => true, 'permission' => 'purchase_orders.approve'],
        'purchase_order.decided' => ['label' => 'A purchase order is approved, sent back or cancelled (tells the buyer)', 'email' => true, 'recipients' => 'purchaseOrderBuyer'],
        'stock.low' => ['label' => 'A product runs low on stock', 'email' => true, 'permission' => 'inventory.manage'],
    ];

    /** Activity-log actions that map onto a notice event. */
    private const ALIASES = [
        'leave.approved' => 'leave.decided',
        'leave.rejected' => 'leave.decided',
        'purchase_order.approved' => 'purchase_order.decided',
        'purchase_order.rejected' => 'purchase_order.decided',
        'purchase_order.cancelled' => 'purchase_order.decided',
    ];

    private static ?bool $tableExists = null;

    public static function emailEnabled(Company $company, string $event): bool
    {
        $stored = $company->setting('notifications_email.'.self::key($event));

        return $stored === null ? (self::EVENTS[$event]['email'] ?? false) : (bool) $stored;
    }

    public static function key(string $event): string
    {
        return str_replace('.', '_', $event);
    }

    /** Called after something happened. $action is an activity-log action name or one of EVENTS. */
    public function notify(string $action, ?Model $subject, array $properties = [], ?int $companyId = null, ?int $actorId = null): void
    {
        $event = self::ALIASES[$action] ?? $action;

        if (! isset(self::EVENTS[$event]) || $companyId === null) {
            return;
        }

        // Seeding and artisan work stay quiet; tests exercise the real thing.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $actorId ??= Auth::id();

        // Wait for the surrounding transaction: a rolled-back sale must not announce itself.
        DB::afterCommit(function () use ($action, $event, $subject, $properties, $companyId, $actorId) {
            try {
                $this->deliver($action, $event, $subject, $properties, $companyId, $actorId);
            } catch (Throwable $e) {
                Log::error("Notification for {$event} could not be created: ".$e->getMessage());
            }
        });
    }

    private function deliver(string $action, string $event, ?Model $subject, array $properties, int $companyId, ?int $actorId): void
    {
        $company = Company::find($companyId);
        $message = $company ? $this->describe($action, $subject, $properties, $company) : null;

        if (! $company || ! $message) {
            return;
        }

        $recipients = $this->recipients($event, $subject, $companyId)->reject(fn (User $u) => $u->id === $actorId);
        $sendEmail = self::emailEnabled($company, $event);
        $hasTable = $this->hasTable();

        foreach ($recipients as $user) {
            if ($hasTable) {
                try {
                    DatabaseNotification::create([
                        'id' => (string) Str::uuid(),
                        'type' => $event,
                        'notifiable_type' => $user->getMorphClass(),
                        'notifiable_id' => $user->getKey(),
                        'company_id' => $companyId,
                        'data' => $message + ['event' => $event],
                    ]);
                } catch (Throwable $e) {
                    Log::error('In-app notice could not be saved: '.$e->getMessage());
                }
            }

            if ($sendEmail && filled($user->email)) {
                try {
                    Mail::to($user->email)->send(new BusinessEventMail($company, $user, $message));
                } catch (Throwable $e) {
                    Log::error("Notice email to user {$user->id} could not be sent: ".$e->getMessage());
                }
            }
        }
    }

    /** @return array{title: string, body: string, url: ?string, icon: string} */
    private function describe(string $action, ?Model $subject, array $p, Company $company): ?array
    {
        $money = fn ($v) => $company->currency.' '.number_format((float) $v, 2);
        $url = fn (string $route, $param = null) => $param === null ? route($route, absolute: true) : route($route, $param, absolute: true);

        switch (true) {
            case $subject instanceof Sale && $action === 'sale.created':
                $who = $subject->customer?->name ?? 'a customer';
                $by = $subject->user?->name;

                return ['icon' => 'sale', 'title' => 'New sale '.($p['invoice'] ?? ''), 'body' => trim("{$money($subject->total)} to {$who}".($by ? ", made by {$by}" : '')), 'url' => $url('sales.show', $subject)];

            case $subject instanceof Sale && $action === 'sale.cancelled':
                return ['icon' => 'warn', 'title' => 'Sale cancelled '.($p['invoice'] ?? ''), 'body' => 'Reason: '.($p['reason'] ?? 'not given'), 'url' => $url('sales.show', $subject)];

            case $subject instanceof Payment:
                $sale = $subject->sale;

                return ['icon' => 'money', 'title' => 'Payment received', 'body' => $money($p['amount'] ?? $subject->amount).' by '.($p['method'] ?? 'payment'), 'url' => $sale ? $url('sales.show', $sale) : null];

            case $subject instanceof LeaveRequest && $action === 'leave.requested':
                $name = $subject->employee?->fullName() ?? 'An employee';

                return ['icon' => 'leave', 'title' => "{$name} requested leave", 'body' => $this->leaveSummary($subject).'. Waiting for your decision.', 'url' => $url('hr.leave')];

            case $subject instanceof LeaveRequest:
                $approved = $action === 'leave.approved';
                $note = $subject->decision_note ? ' Note: '.$subject->decision_note : '';

                return ['icon' => $approved ? 'ok' : 'warn', 'title' => 'Leave '.($approved ? 'approved' : 'rejected'), 'body' => $this->leaveSummary($subject).'.'.$note, 'url' => $url('hr.leave')];

            case $subject instanceof PurchaseOrder:
                $po = $subject->po_number;
                $total = isset($subject->total) ? ' ('.$money($subject->total).')' : '';

                return match ($action) {
                    'purchase_order.submitted' => ['icon' => 'po', 'title' => "Purchase order {$po} needs approval", 'body' => "Submitted for approval{$total}.", 'url' => $url('purchase-orders.show', $subject)],
                    'purchase_order.approved' => ['icon' => 'ok', 'title' => "Purchase order {$po} approved", 'body' => 'It can now be sent to the supplier and received.', 'url' => $url('purchase-orders.show', $subject)],
                    'purchase_order.rejected' => ['icon' => 'warn', 'title' => "Purchase order {$po} sent back", 'body' => 'Reason: '.($subject->rejection_reason ?: 'not given'), 'url' => $url('purchase-orders.show', $subject)],
                    'purchase_order.cancelled' => ['icon' => 'warn', 'title' => "Purchase order {$po} cancelled", 'body' => 'Reason: '.($subject->cancel_reason ?: 'not given'), 'url' => $url('purchase-orders.show', $subject)],
                    default => null,
                };

            case $subject instanceof Product && $action === 'stock.low':
                return ['icon' => 'stock', 'title' => "Low stock: {$subject->name}", 'body' => 'Only '.rtrim(rtrim((string) $subject->quantity, '0'), '.').' left, at or below the minimum of '.rtrim(rtrim((string) $subject->min_stock, '0'), '.').'.', 'url' => $url('inventory.products')];
        }

        return null;
    }

    private function leaveSummary(LeaveRequest $leave): string
    {
        $type = LeaveRequest::TYPES[$leave->type] ?? ucfirst((string) $leave->type);
        $days = rtrim(rtrim((string) $leave->days, '0'), '.');

        return "{$type}, {$days} working day".($days === '1' ? '' : 's').', '.$leave->start_date->format('d M').' to '.$leave->end_date->format('d M Y')
            .($leave->employee ? ' ('.$leave->employee->fullName().')' : '');
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function recipients(string $event, ?Model $subject, int $companyId)
    {
        $rule = self::EVENTS[$event];

        if (isset($rule['permission'])) {
            return $this->holdersOf($rule['permission'], $companyId);
        }

        $ids = $this->{$rule['recipients']}($subject);

        return User::query()->whereIn('id', array_filter($ids))->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->canAccessCompany($companyId));
    }

    /** Active members of the company whose role holds the permission. */
    private function holdersOf(string $permission, int $companyId)
    {
        $roleIds = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.slug', $permission)
            ->pluck('role_permissions.role_id');

        $userIds = DB::table('company_user')->where('company_id', $companyId)->whereIn('role_id', $roleIds)->pluck('user_id');

        return User::query()->whereIn('id', $userIds)->where('is_active', true)->get();
    }

    /** The person who asked for the leave and, if they have a login, the employee it is for. */
    private function leavePeople(?Model $leave): array
    {
        if (! $leave instanceof LeaveRequest) {
            return [];
        }

        return [$leave->requested_by, Employee::withTrashed()->find($leave->employee_id)?->user_id];
    }

    private function purchaseOrderBuyer(?Model $order): array
    {
        return $order instanceof PurchaseOrder ? [$order->user_id] : [];
    }

    private function hasTable(): bool
    {
        return self::$tableExists ??= Schema::hasTable('notifications');
    }

    /** Tests migrate fresh databases; forget what an earlier run learned. */
    public static function forgetTableCheck(): void
    {
        self::$tableExists = null;
    }
}
