@props(['sale'])
@if ($sale->isCancelled())
    <span class="badge bg-slate-200 text-slate-700">Cancelled</span>
@elseif ($sale->isOverdue())
    <span class="badge bg-red-100 text-red-700">Overdue</span>
@elseif ($sale->payment_status === 'paid')
    <span class="badge bg-emerald-100 text-emerald-700">Paid</span>
@elseif ($sale->payment_status === 'partial')
    <span class="badge bg-amber-100 text-amber-800">Partially paid</span>
@else
    <span class="badge bg-sky-100 text-sky-700">Unpaid</span>
@endif
