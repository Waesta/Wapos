<?php

class HospitalityPermissionSeeder
{
    /** @var Database */
    private $db;
    /** @var array<string,int> */
    private $moduleIdMap = [];
    /** @var array<string,int> */
    private $actionIdMap = [];

    private function __construct(Database $db)
    {
        $this->db = $db;
    }

    public static function sync(Database $db): void
    {
        static $hasRun = false;
        if ($hasRun) {
            return;
        }
        $hasRun = true;

        try {
            $instance = new self($db);
            $instance->run();
        } catch (Throwable $e) {
            error_log('HospitalityPermissionSeeder failure: ' . $e->getMessage());
        }
    }

    private function run(): void
    {
        $this->ensureSystemActions();
        $this->ensureModules();
        $this->refreshLookups();
        $this->ensureModuleActions();
        $this->ensurePermissionGroups();
    }

    private function ensureSystemActions(): void
    {
        foreach ($this->getActionBlueprints() as $actionKey => $action) {
            $existing = $this->db->fetchOne('SELECT id, name, display_name, description, is_sensitive, requires_approval FROM system_actions WHERE action_key = ?', [$actionKey]);
            $payload = [
                'name' => $action['name'],
                'display_name' => $action['display_name'],
                'description' => $action['description'],
                'action_key' => $actionKey,
                'is_sensitive' => $action['is_sensitive'],
                'requires_approval' => $action['requires_approval'],
            ];

            if (!$existing) {
                $this->db->insert('system_actions', $payload);
                continue;
            }

            $update = [];
            foreach (['name', 'display_name', 'description', 'is_sensitive', 'requires_approval'] as $field) {
                if ((string)($existing[$field] ?? '') !== (string)$payload[$field]) {
                    $update[$field] = $payload[$field];
                }
            }

            if (!empty($update)) {
                $this->db->update('system_actions', $update, 'id = :id', ['id' => $existing['id']]);
            }
        }
    }

    private function ensureModules(): void
    {
        foreach ($this->getModuleBlueprints() as $key => $module) {
            $existing = $this->db->fetchOne('SELECT id, name, display_name, description, icon, sort_order, is_active FROM system_modules WHERE module_key = ?', [$key]);
            $payload = [
                'name' => $module['name'],
                'display_name' => $module['display_name'],
                'description' => $module['description'],
                'module_key' => $key,
                'icon' => $module['icon'],
                'sort_order' => $module['sort_order'],
                'is_active' => 1,
            ];

            if (!$existing) {
                $this->db->insert('system_modules', $payload);
                continue;
            }

            $update = [];
            foreach (['name', 'display_name', 'description', 'icon', 'sort_order'] as $field) {
                if (($existing[$field] ?? null) !== $payload[$field]) {
                    $update[$field] = $payload[$field];
                }
            }

            if (($existing['is_active'] ?? 0) != 1) {
                $update['is_active'] = 1;
            }

            if (!empty($update)) {
                $this->db->update('system_modules', $update, 'id = :id', ['id' => $existing['id']]);
            }
        }
    }

    private function ensureModuleActions(): void
    {
        $moduleActionMap = $this->getModuleActionBlueprints();
        foreach ($moduleActionMap as $moduleKey => $actions) {
            $moduleId = $this->moduleIdMap[$moduleKey] ?? null;
            if (!$moduleId) {
                continue;
            }

            foreach ($actions as $actionKey) {
                $actionId = $this->actionIdMap[$actionKey] ?? null;
                if (!$actionId) {
                    continue;
                }

                $isDefault = in_array($actionKey, ['view', 'create', 'update'], true) ? 1 : 0;
                $this->db->execute(
                    'INSERT INTO module_actions (module_id, action_id, is_default) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE is_default = VALUES(is_default)',
                    [$moduleId, $actionId, $isDefault]
                );
            }
        }
    }

    private function ensurePermissionGroups(): void
    {
        $blueprints = $this->getPermissionGroupBlueprints();
        foreach ($blueprints as $groupName => $config) {
            $group = $this->db->fetchOne('SELECT id, description, color FROM permission_groups WHERE name = ?', [$groupName]);
            if (!$group) {
                $groupId = $this->db->insert('permission_groups', [
                    'name' => $groupName,
                    'description' => $config['description'],
                    'color' => $config['color'],
                    'is_active' => 1,
                ]);
            } else {
                $groupId = (int)$group['id'];
                $update = [];
                if (($group['description'] ?? '') !== $config['description']) {
                    $update['description'] = $config['description'];
                }
                if (($group['color'] ?? '') !== $config['color']) {
                    $update['color'] = $config['color'];
                }
                if (!empty($update)) {
                    $this->db->update('permission_groups', $update, 'id = :id', ['id' => $groupId]);
                }
            }

            if (!$groupId) {
                continue;
            }

            $existingPermissions = $this->db->fetchAll(
                'SELECT CONCAT(sm.module_key, ":", sa.action_key) AS slug
                 FROM group_permissions gp
                 JOIN system_modules sm ON gp.module_id = sm.id
                 JOIN system_actions sa ON gp.action_id = sa.id
                 WHERE gp.group_id = ?',
                [$groupId]
            );
            $existingSet = array_flip(array_column($existingPermissions, 'slug'));

            foreach ($config['permissions'] as $permission) {
                if (isset($existingSet[$permission])) {
                    continue;
                }
                if (strpos($permission, ':') === false) {
                    continue;
                }
                [$moduleKey, $actionKey] = array_map('trim', explode(':', $permission, 2));
                $moduleId = $this->moduleIdMap[$moduleKey] ?? null;
                $actionId = $this->actionIdMap[$actionKey] ?? null;
                if (!$moduleId || !$actionId) {
                    continue;
                }

                $this->db->insert('group_permissions', [
                    'group_id' => $groupId,
                    'module_id' => $moduleId,
                    'action_id' => $actionId,
                    'is_granted' => 1,
                    'granted_by' => null,
                ]);
            }
        }
    }

    private function refreshLookups(): void
    {
        $this->moduleIdMap = [];
        $modules = $this->db->fetchAll('SELECT id, module_key FROM system_modules');
        foreach ($modules as $module) {
            $this->moduleIdMap[$module['module_key']] = (int)$module['id'];
        }

        $this->actionIdMap = [];
        $actions = $this->db->fetchAll('SELECT id, action_key FROM system_actions');
        foreach ($actions as $action) {
            $this->actionIdMap[$action['action_key']] = (int)$action['id'];
        }
    }

    private function getModuleBlueprints(): array
    {
        return [
            'frontdesk' => [
                'name' => 'FrontDesk',
                'display_name' => 'Front Desk',
                'description' => 'Guest check-in, check-out, and lobby operations.',
                'icon' => 'bi-bell',
                'sort_order' => 16,
            ],
            'housekeeping' => [
                'name' => 'Housekeeping',
                'display_name' => 'Housekeeping',
                'description' => 'Room readiness, cleaning schedules, and inspections.',
                'icon' => 'bi-brush',
                'sort_order' => 17,
            ],
            'maintenance' => [
                'name' => 'Maintenance',
                'display_name' => 'Maintenance',
                'description' => 'Facility upkeep, work orders, and technician dispatch.',
                'icon' => 'bi-tools',
                'sort_order' => 18,
            ],
            'concierge' => [
                'name' => 'Concierge',
                'display_name' => 'Concierge',
                'description' => 'Guest experiences, itineraries, and VIP services.',
                'icon' => 'bi-star',
                'sort_order' => 19,
            ],
            'spa' => [
                'name' => 'Spa',
                'display_name' => 'Spa & Wellness',
                'description' => 'Spa treatments, therapist schedules, and billing.',
                'icon' => 'bi-heart',
                'sort_order' => 20,
            ],
            'events' => [
                'name' => 'Events',
                'display_name' => 'Events & Banquets',
                'description' => 'Event planning, banquets, and conference services.',
                'icon' => 'bi-calendar-event',
                'sort_order' => 21,
            ],
            'room_service' => [
                'name' => 'RoomService',
                'display_name' => 'Room Service',
                'description' => 'In-room dining orders and dispatch.',
                'icon' => 'bi-cup-hot',
                'sort_order' => 22,
            ],
            'security' => [
                'name' => 'Security',
                'display_name' => 'Security',
                'description' => 'Security patrols, incident logs, and escalations.',
                'icon' => 'bi-shield-lock',
                'sort_order' => 23,
            ],
            'hr' => [
                'name' => 'HR',
                'display_name' => 'HR & Compliance',
                'description' => 'Human resources, onboarding, and compliance tasks.',
                'icon' => 'bi-people',
                'sort_order' => 24,
            ],
            'revenue' => [
                'name' => 'Revenue',
                'display_name' => 'Revenue Management',
                'description' => 'Pricing, forecasting, and yield management.',
                'icon' => 'bi-graph-up-arrow',
                'sort_order' => 25,
            ],
            'sales_office' => [
                'name' => 'SalesOffice',
                'display_name' => 'Corporate Sales',
                'description' => 'Corporate contracts and travel agent negotiations.',
                'icon' => 'bi-briefcase',
                'sort_order' => 26,
            ],
        ];
    }

    private function getModuleActionBlueprints(): array
    {
        return [
            'frontdesk' => ['view', 'create', 'update', 'delete', 'send_receipts', 'customer_credit'],
            'housekeeping' => ['view', 'create', 'update', 'delete'],
            'maintenance' => ['view', 'create', 'update', 'delete'],
            'concierge' => ['view', 'create', 'update', 'send_receipts'],
            'spa' => ['view', 'create', 'update', 'delete', 'refund', 'discount'],
            'events' => ['view', 'create', 'update', 'delete', 'export'],
            'room_service' => ['view', 'create', 'update', 'delete', 'send_receipts'],
            'security' => ['view', 'update', 'audit_logs'],
            'hr' => ['view', 'create', 'update', 'delete'],
            'revenue' => ['view', 'view_reports', 'financial_reports', 'export'],
            'sales_office' => ['view', 'create', 'update', 'delete', 'export'],
        ];
    }

    private function getPermissionGroupBlueprints(): array
    {
        return [
            'Front Office Leadership' => [
                'color' => '#0d6efd',
                'description' => 'Oversees front desk operations, reservations, and arrivals.',
                'permissions' => [
                    'frontdesk:view', 'frontdesk:create', 'frontdesk:update', 'frontdesk:delete',
                    'rooms:view', 'rooms:update',
                    'customers:view', 'customers:update',
                    'reports:view', 'reports:view_reports', 'reports:export'
                ],
            ],
            'Guest Relations & Concierge' => [
                'color' => '#6f42c1',
                'description' => 'Handles VIP experiences, excursions, and loyalty guests.',
                'permissions' => [
                    'concierge:view', 'concierge:create', 'concierge:update',
                    'frontdesk:view',
                    'customers:view', 'customers:update',
                    'sales_office:view'
                ],
            ],
            'Spa & Wellness' => [
                'color' => '#d63384',
                'description' => 'Manages spa treatments, therapists, and upsells.',
                'permissions' => [
                    'spa:view', 'spa:create', 'spa:update', 'spa:delete',
                    'sales:view', 'sales:update',
                    'customers:view',
                    'reports:export'
                ],
            ],
            'Events & Banquets' => [
                'color' => '#fd7e14',
                'description' => 'Plans banquets, conferences, and corporate events.',
                'permissions' => [
                    'events:view', 'events:create', 'events:update', 'events:delete', 'events:export',
                    'sales_office:view', 'sales_office:update',
                    'reports:view_reports'
                ],
            ],
            'Room Service Operations' => [
                'color' => '#20c997',
                'description' => 'Coordinates in-room dining and delivery SLAs.',
                'permissions' => [
                    'room_service:view', 'room_service:create', 'room_service:update', 'room_service:delete',
                    'restaurant:view', 'restaurant:update',
                    'customers:view',
                ],
            ],
            'Security Operations' => [
                'color' => '#212529',
                'description' => 'Incident response, patrol logs, and escalations.',
                'permissions' => [
                    'security:view', 'security:update', 'security:audit_logs',
                    'reports:view',
                ],
            ],
            'HR & Compliance' => [
                'color' => '#17a2b8',
                'description' => 'Employee lifecycle, onboarding, and compliance trackers.',
                'permissions' => [
                    'hr:view', 'hr:create', 'hr:update', 'hr:delete',
                    'users:view', 'users:create', 'users:update',
                    'settings:view'
                ],
            ],
            'Revenue Management' => [
                'color' => '#6610f2',
                'description' => 'Pricing strategy, forecasting, and yield controls.',
                'permissions' => [
                    'revenue:view', 'revenue:view_reports', 'revenue:financial_reports', 'revenue:export',
                    'reports:view_reports', 'reports:financial_reports',
                    'sales:view'
                ],
            ],
            'Corporate Sales' => [
                'color' => '#198754',
                'description' => 'Account executives managing contracts and agencies.',
                'permissions' => [
                    'sales_office:view', 'sales_office:create', 'sales_office:update', 'sales_office:delete', 'sales_office:export',
                    'customers:view', 'customers:create', 'customers:update',
                    'reports:export'
                ],
            ],
            'Housekeeping Leadership' => [
                'color' => '#ffc107',
                'description' => 'Supervises housekeeping assignments and inspections.',
                'permissions' => [
                    'housekeeping:view', 'housekeeping:create', 'housekeeping:update', 'housekeeping:delete',
                    'rooms:view', 'rooms:update',
                    'reports:view'
                ],
            ],
        ];
    }

    private function getActionBlueprints(): array
    {
        return [
            'view' => [
                'name' => 'view',
                'display_name' => 'View/Read',
                'description' => 'View and read data',
                'is_sensitive' => 0,
                'requires_approval' => 0,
            ],
            'create' => [
                'name' => 'create',
                'display_name' => 'Create/Add',
                'description' => 'Create new records',
                'is_sensitive' => 0,
                'requires_approval' => 0,
            ],
            'update' => [
                'name' => 'update',
                'display_name' => 'Edit/Update',
                'description' => 'Modify existing records',
                'is_sensitive' => 0,
                'requires_approval' => 0,
            ],
            'delete' => [
                'name' => 'delete',
                'display_name' => 'Delete/Remove',
                'description' => 'Delete records',
                'is_sensitive' => 1,
                'requires_approval' => 1,
            ],
            'send_receipts' => [
                'name' => 'send_receipts',
                'display_name' => 'Send Receipts',
                'description' => 'Email/SMS receipts to guests',
                'is_sensitive' => 0,
                'requires_approval' => 0,
            ],
            'customer_credit' => [
                'name' => 'customer_credit',
                'display_name' => 'Customer Credit',
                'description' => 'Manage customer credit accounts',
                'is_sensitive' => 1,
                'requires_approval' => 1,
            ],
            'refund' => [
                'name' => 'refund',
                'display_name' => 'Process Refunds',
                'description' => 'Handle customer refunds',
                'is_sensitive' => 1,
                'requires_approval' => 1,
            ],
            'discount' => [
                'name' => 'discount',
                'display_name' => 'Apply Discounts',
                'description' => 'Apply discounts to charges',
                'is_sensitive' => 1,
                'requires_approval' => 0,
            ],
            'export' => [
                'name' => 'export',
                'display_name' => 'Export Data',
                'description' => 'Export reports and datasets',
                'is_sensitive' => 0,
                'requires_approval' => 0,
            ],
            'audit_logs' => [
                'name' => 'audit_logs',
                'display_name' => 'Audit Logs',
                'description' => 'View audit and incident logs',
                'is_sensitive' => 1,
                'requires_approval' => 0,
            ],
            'view_reports' => [
                'name' => 'view_reports',
                'display_name' => 'View Reports',
                'description' => 'Access performance and KPI reports',
                'is_sensitive' => 0,
                'requires_approval' => 0,
            ],
            'financial_reports' => [
                'name' => 'financial_reports',
                'display_name' => 'Financial Reports',
                'description' => 'Access financial statements',
                'is_sensitive' => 1,
                'requires_approval' => 0,
            ],
        ];
    }
}
