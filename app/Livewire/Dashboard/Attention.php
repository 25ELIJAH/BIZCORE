<?php

namespace App\Livewire\Dashboard;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Services\LeaveService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * "Needs your attention" on the dashboard. Every block appears only for people whose role covers that work:
 * approvers see the leave and purchase orders waiting for them (leave can be approved or rejected right here),
 * sales and stock people see their own areas, and anyone who asks for leave sees how their requests stand.
 */
class Attention extends Component
{
    public ?int $rejectId = null;

    public string $reason = '';

    public string $notice = '';

    public function approve(int $id, LeaveService $service): void
    {
        $this->authorize('leave.approve');
        $request = LeaveRequest::where('status', LeaveRequest::PENDING)->findOrFail($id);

        try {
            $service->approve($request);
            $this->notice = 'Leave approved. The employee has been told.';
        } catch (BusinessRuleException $e) {
            $this->addError('leave', $e->getMessage());
        }
    }

    public function startReject(int $id): void
    {
        $this->authorize('leave.approve');
        $this->resetValidation();
        $this->rejectId = $id;
        $this->reason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectId = null;
    }

    public function reject(LeaveService $service): void
    {
        $this->authorize('leave.approve');
        $this->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'reason']);
        $request = LeaveRequest::where('status', LeaveRequest::PENDING)->findOrFail($this->rejectId);

        try {
            $service->reject($request, $this->reason);
            $this->notice = 'Leave rejected. The employee has been told.';
            $this->rejectId = null;
        } catch (BusinessRuleException $e) {
            $this->addError('leave', $e->getMessage());
        }
    }

    public function render()
    {
        $data = [];

        if (Gate::allows('leave.approve')) {
            $pending = LeaveRequest::with('employee')->where('status', LeaveRequest::PENDING);
            $data['leave'] = ['count' => (clone $pending)->count(), 'rows' => $pending->oldest('start_date')->limit(5)->get()];
        }

        if (Gate::allows('purchase_orders.approve')) {
            $orders = PurchaseOrder::with('supplier')->where('status', PurchaseOrder::PENDING);
            $data['orders'] = ['count' => (clone $orders)->count(), 'rows' => $orders->oldest('submitted_at')->limit(5)->get()];
        }

        if (Gate::allows('sales.view')) {
            $data['sales'] = [
                'overdue' => Sale::overdue()->count(),
                'recent' => Sale::with(['customer', 'invoice', 'user:id,name'])->where('status', Sale::STATUS_COMPLETED)->latest()->limit(5)->get(),
            ];
        }

        if (Gate::allows('inventory.view')) {
            $data['stock'] = Product::lowStock()->count();
        }

        $own = Employee::where('user_id', Auth::id())->first();
        if ($own) {
            $data['mine'] = LeaveRequest::where('employee_id', $own->id)->latest()->limit(3)->get();
        }

        $unread = 0;
        if (Schema::hasTable('notifications')) {
            $unread = Auth::user()->unreadNotifications()->where('company_id', app(\App\Support\Tenancy\CurrentCompany::class)->id())->count();
        }

        return view('livewire.dashboard.attention', ['data' => $data, 'unread' => $unread, 'currency' => app(\App\Support\Tenancy\CurrentCompany::class)->get()->currency]);
    }
}
