<?php

namespace App\Support\Access;

/**
 * Single source of truth for every permission slug in the system.
 * Permissions are seeded into the `permissions` table from this list; code
 * (policies, gates, navigation) refers to the slugs defined here.
 */
final class PermissionCatalog
{
    /** @return array<string, array<string, string>> group => [slug => label] */
    public static function groups(): array
    {
        return [
            'Company' => [
                'dashboard.view' => 'View dashboard',
                'company.settings.manage' => 'Manage company settings',
                'company.users.manage' => 'Assign users and roles in the company',
                'activity_logs.view' => 'View activity logs',
            ],
            'Inventory' => [
                'inventory.view' => 'View inventory',
                'inventory.manage' => 'Manage products and categories',
                'inventory.adjust' => 'Adjust stock',
            ],
            'Sales' => [
                'customers.view' => 'View customers',
                'customers.manage' => 'Manage customers',
                'sales.view' => 'View sales',
                'sales.create' => 'Create sales',
                'sales.edit' => 'Edit sales',
                'sales.cancel' => 'Cancel sales',
                'payments.record' => 'Record customer payments',
            ],
            'Human resources' => [
                'employees.view' => 'View employees',
                'employees.manage' => 'Manage employees',
                'employees.view_sensitive' => 'View sensitive employee data',
                'attendance.manage' => 'Record and manage attendance',
                'leave.request' => 'Submit leave requests',
                'leave.approve' => 'Approve or reject leave',
            ],
            'Procurement' => [
                'suppliers.view' => 'View suppliers',
                'suppliers.manage' => 'Manage suppliers',
                'purchase_orders.view' => 'View purchase orders',
                'purchase_orders.manage' => 'Create and edit purchase orders',
                'purchase_orders.approve' => 'Approve purchase orders',
                'goods.receive' => 'Receive goods',
                'supplier_payments.record' => 'Record supplier payments',
            ],
            'Finance' => [
                'finance.view' => 'View income and expenses',
                'finance.manage' => 'Record income and expenses',
                'finance.categories.manage' => 'Configure financial categories',
                'reports.view' => 'View operational reports',
                'reports.financial.view' => 'View financial reports',
                'reports.export' => 'Export reports',
            ],
        ];
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::groups())));
    }
}
