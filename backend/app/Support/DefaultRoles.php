<?php

namespace App\Support;

/**
 * Canonical definition of the eight system role templates described in the
 * brief (§4). Each organization gets its own concrete copy of these roles
 * (organization_id set) at creation time, cloned by RoleService::seedDefaultRolesFor().
 * Organizations may subsequently edit permission sets or create wholly
 * custom roles — this class only defines the initial/reset defaults.
 */
class DefaultRoles
{
    public static function definitions(): array
    {
        return [
            'owner' => [
                'name' => 'Organization Owner',
                'org_wide_visibility' => true,
                'permissions' => PermissionCatalogue::flat(), // full access
            ],
            'admin' => [
                'name' => 'Organization Admin',
                'org_wide_visibility' => true,
                'permissions' => array_values(array_diff(PermissionCatalogue::flat(), [
                    // Sensitive financial/administrative actions are
                    // configurable — excluded by default, grantable by the Owner.
                    'billing.approve', 'organization.manage', 'users.delete',
                    'materials.negative_stock_override',
                ])),
            ],
            'project_manager' => [
                'name' => 'Project Manager',
                'org_wide_visibility' => false, // scoped to assigned projects only
                'permissions' => [
                    'projects.view',
                    'sites.view', 'sites.create', 'sites.update',
                    'daily_reports.view', 'daily_reports.create', 'daily_reports.update', 'daily_reports.approve',
                    'labour.view', 'labour.create', 'labour.update', 'labour.attendance',
                    'materials.view', 'materials.create', 'materials.update',
                    'fuel.view', 'fuel.create',
                    'equipment.view', 'equipment.update', 'equipment.log_usage',
                    'measurements.view',
                    // documents.delete granted here so a PM can manage
                    // (remove) documents they or their team uploaded for
                    // their own assigned project — DocumentPolicy::delete()
                    // still separately requires org-wide visibility OR
                    // being the original uploader, so this alone does not
                    // let a PM delete another user's documents.
                    'documents.view', 'documents.upload', 'documents.delete',
                ],
            ],
            'site_supervisor' => [
                'name' => 'Site Engineer / Site Supervisor',
                'org_wide_visibility' => false, // scoped to assigned sites/projects only
                'permissions' => [
                    'projects.view', 'sites.view',
                    'daily_reports.view', 'daily_reports.create', 'daily_reports.update',
                    'labour.view', 'labour.attendance',
                    'materials.view', 'materials.create',
                    'fuel.view', 'fuel.create',
                    'equipment.view', 'equipment.log_usage',
                    'documents.view', 'documents.upload',
                ],
            ],
            'store_manager' => [
                'name' => 'Store Manager',
                'org_wide_visibility' => false,
                'permissions' => [
                    'materials.view', 'materials.create', 'materials.update',
                    'materials.issue', 'materials.transfer',
                    // Deliberately EXCLUDED by default: negative-stock
                    // override is reserved for Owner/Admin-tier roles who
                    // can absorb the operational risk of an emergency
                    // override; a Store Manager must escalate instead.
                    'documents.view',
                ],
            ],
            'hr_labour_manager' => [
                'name' => 'HR / Labour Manager',
                'org_wide_visibility' => false,
                'permissions' => [
                    'labour.view', 'labour.create', 'labour.update', 'labour.attendance',
                    // 'labour.wages' (financial visibility) withheld by default —
                    // permission-controlled per brief §4.7.
                    'documents.view',
                ],
            ],
            'accounts_manager' => [
                'name' => 'Accounts Manager',
                'org_wide_visibility' => false,
                'permissions' => [
                    'projects.view',
                    // Brief §4.8: "Can: Access BOQ financial values,
                    // Measurements, Bills, Payments, Outstanding amounts" —
                    // Accounts Manager owns the full measurement-to-payment
                    // financial pipeline, not just read access to it.
                    'measurements.view', 'measurements.create', 'measurements.update', 'measurements.approve',
                    'billing.view', 'billing.create', 'billing.update', 'billing.approve',
                    'payments.view', 'payments.create', 'payments.update',
                    'documents.view',
                ],
            ],
            'client_readonly' => [
                'name' => 'Client / Read-Only User',
                'org_wide_visibility' => false,
                'permissions' => [
                    'projects.view', 'daily_reports.view', 'documents.view',
                ],
            ],
        ];
    }
}
