<?php

namespace App\Support;

/**
 * Canonical, single source of truth for every permission string recognised
 * by the system. Used by PermissionSeeder to populate the `permissions`
 * table, and referenced by RoleSeeder when assembling default role→
 * permission sets. Adding a new permission is a data change (seed +
 * migration if needed), never a new `if ($user->role === ...)` branch.
 */
class PermissionCatalogue
{
    public static function all(): array
    {
        return [
            'projects' => [
                'projects.view', 'projects.create', 'projects.update', 'projects.delete',
            ],
            'sites' => [
                'sites.view', 'sites.create', 'sites.update', 'sites.delete',
            ],
            'daily_reports' => [
                'daily_reports.view', 'daily_reports.create', 'daily_reports.update',
                'daily_reports.approve', 'daily_reports.delete',
            ],
            'materials' => [
                'materials.view', 'materials.create', 'materials.update',
                'materials.issue', 'materials.transfer', 'materials.delete',
                // Distinct, highly-restricted permission required to force a
                // stock transaction through when it would otherwise result
                // in negative stock (brief §16: "Prevent negative stock
                // unless an explicitly authorized override exists").
                'materials.negative_stock_override',
            ],
            'fuel' => [
                'fuel.view', 'fuel.create', 'fuel.update', 'fuel.approve',
            ],
            'equipment' => [
                'equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete',
                // Distinct from 'equipment.create' (which registers brand
                // new equipment into the org's fleet, an Owner/Admin-tier
                // action). This permission gates logging HOURS OF USE
                // against already-registered equipment — the field-level
                // action described in brief §4 ("Site Engineer/Supervisor
                // can: Report equipment usage").
                'equipment.log_usage',
            ],
            'labour' => [
                'labour.view', 'labour.create', 'labour.update',
                'labour.attendance', 'labour.wages',
            ],
            'measurements' => [
                'measurements.view', 'measurements.create', 'measurements.update',
                'measurements.approve',
            ],
            'billing' => [
                'billing.view', 'billing.create', 'billing.update', 'billing.approve',
            ],
            'payments' => [
                'payments.view', 'payments.create', 'payments.update',
            ],
            'documents' => [
                'documents.view', 'documents.upload', 'documents.download',
                'documents.delete', 'documents.share',
            ],
            'compliance' => [
                'compliance.view', 'compliance.create', 'compliance.update', 'compliance.delete',
            ],
            'users' => [
                'users.view', 'users.create', 'users.update', 'users.delete',
            ],
            'roles' => [
                'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            ],
            'audit_logs' => [
                'audit_logs.view',
            ],
            'organization' => [
                'organization.manage',
            ],
        ];
    }

    public static function flat(): array
    {
        return collect(self::all())->flatten()->values()->all();
    }
}
