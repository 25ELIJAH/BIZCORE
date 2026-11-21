<?php

namespace App\Support\Access;

/**
 * Initial roles seeded for every installation. Administrators can edit them
 * afterwards; `is_system` roles cannot be deleted.
 */
final class DefaultRoles
{
    /** @return array<string, array{name: string, description: string, permissions: list<string>}> */
    public static function definitions(): array
    {
        $all = PermissionCatalog::slugs();
        $views = ['dashboard.view', 'inventory.view', 'customers.view', 'sales.view', 'employees.view',
            'suppliers.view', 'purchase_orders.view', 'finance.view', 'reports.view'];

        return [
            'super-administrator' => [
                'name' => 'Super Administrator',
                'description' => 'Full access to everything.',
                'permissions' => $all,
            ],
            'company-administrator' => [
                'name' => 'Company Administrator',
                'description' => 'Full access within a company.',
                'permissions' => $all,
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Oversees operations, approvals and reports.',
                'permissions' => array_merge($views, [
                    'reports.financial.view', 'reports.export', 'activity_logs.view', 'sales.create', 'sales.edit',
                    'sales.cancel', 'purchase_orders.approve', 'leave.approve', 'inventory.adjust', 'leave.request',
                ]),
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Finance, payments and financial reports.',
                'permissions' => [
                    'dashboard.view', 'finance.view', 'finance.manage', 'finance.categories.manage', 'reports.view',
                    'reports.financial.view', 'reports.export', 'payments.record', 'supplier_payments.record',
                    'sales.view', 'customers.view', 'suppliers.view', 'purchase_orders.view', 'leave.request',
                ],
            ],
            'inventory-officer' => [
                'name' => 'Inventory Officer',
                'description' => 'Products, stock and adjustments.',
                'permissions' => ['dashboard.view', 'inventory.view', 'inventory.manage', 'inventory.adjust',
                    'goods.receive', 'purchase_orders.view', 'reports.view', 'leave.request'],
            ],
            'hr-officer' => [
                'name' => 'HR Officer',
                'description' => 'Employees, attendance and leave.',
                'permissions' => ['dashboard.view', 'employees.view', 'employees.manage', 'employees.view_sensitive',
                    'attendance.manage', 'leave.request', 'leave.approve', 'reports.view'],
            ],
            'sales-officer' => [
                'name' => 'Sales Officer',
                'description' => 'Customers, sales and customer payments.',
                'permissions' => ['dashboard.view', 'customers.view', 'customers.manage', 'sales.view', 'sales.create',
                    'sales.edit', 'payments.record', 'inventory.view', 'leave.request'],
            ],
            'procurement-officer' => [
                'name' => 'Procurement Officer',
                'description' => 'Suppliers, purchase orders and receiving.',
                'permissions' => ['dashboard.view', 'suppliers.view', 'suppliers.manage', 'purchase_orders.view',
                    'purchase_orders.manage', 'goods.receive', 'inventory.view', 'leave.request'],
            ],
            'employee' => [
                'name' => 'Employee',
                'description' => 'Basic self-service access.',
                'permissions' => ['dashboard.view', 'leave.request'],
            ],
        ];
    }
}
