<?php
/**
 * RePlug — item status labels and workflow transitions.
 */

function item_workflow_labels(): array
{
    return [
        'draft' => 'Draft',
        'pickup_requested' => 'Pickup requested',
        'scheduled' => 'Pickup scheduled',
        'picked_up' => 'Picked up',
        'assigned_to_technician' => 'Assigned to technician',
        'technician_inspection' => 'Technician inspection',
        'waiting_for_admin_approval' => 'Waiting for admin approval',
        'repair_approved_by_admin' => 'Repair approved by admin',
        'repair_in_progress' => 'Repair in progress',
        'repaired' => 'Repaired',
        'ready_for_marketplace' => 'Ready for marketplace',
        'approved_for_sale' => 'Approved for sale',
        'listed_for_sale' => 'Listed on marketplace',
        'sold' => 'Sold',
        'recycled' => 'Recycled',
        'not_repairable' => 'Not repairable',
        'recyclable' => 'Recyclable',
        'inspected' => 'Inspected',
    ];
}

function item_status_label(string $status): string
{
    $labels = item_workflow_labels();
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/** Client-facing pickup / item tracking (dashboard). */
function client_item_tracking_label(string $itemStatus, ?string $pickupStatus = null): string
{
    if ($pickupStatus === 'scheduled') {
        return 'Pickup assigned';
    }
    if ($pickupStatus === 'picked_up') {
        return 'Pickup completed';
    }

    $map = [
        'assigned_to_technician' => 'Under inspection',
        'technician_inspection' => 'Under inspection',
        'waiting_for_admin_approval' => 'Awaiting depot review',
        'repair_approved_by_admin' => 'Repair approved',
        'repair_in_progress' => 'Repair in progress',
        'repaired' => 'Repair complete',
        'ready_for_marketplace' => 'Ready for resale',
        'approved_for_sale' => 'Ready for resale',
        'listed_for_sale' => 'Listed on marketplace',
        'sold' => 'Sold',
        'recycled' => 'Sent for recycling',
        'not_repairable' => 'Not repairable',
        'recyclable' => 'Marked recyclable',
        'picked_up' => 'Pickup completed',
    ];

    return $map[$itemStatus] ?? item_status_label($itemStatus);
}

function replug_maps_search_url(string $address): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim($address));
}

function inspection_result_sets_status(string $result): string
{
    return 'waiting_for_admin_approval';
}

function inspection_result_is_valid(string $result): bool
{
    return in_array($result, ['working', 'repairable', 'recyclable', 'not_repairable'], true);
}

/**
 * Status a technician may set after initial inspection (dropdown).
 */
function technician_status_options(string $currentStatus): array
{
    $options = [
        'assigned_to_technician' => [],
        'repair_approved_by_admin' => ['repair_in_progress' => 'Repair in progress'],
        'repair_in_progress' => ['repaired' => 'Repaired'],
        'repaired' => ['ready_for_marketplace' => 'Ready for marketplace (send to admin)'],
    ];
    return $options[$currentStatus] ?? [];
}

function technician_can_set_status(string $current, string $next): bool
{
    $allowed = technician_status_options($current);
    return array_key_exists($next, $allowed);
}

/**
 * Resolve admin approval action from latest inspection result.
 */
function admin_approval_target_status(string $inspectionResult, string $adminAction): ?string
{
    if ($adminAction === 'reject') {
        return 'assigned_to_technician';
    }
    if ($adminAction === 'approve_repair' && $inspectionResult === 'repairable') {
        return 'repair_approved_by_admin';
    }
    if ($adminAction === 'approve_sale' && $inspectionResult === 'working') {
        return 'ready_for_marketplace';
    }
    if ($adminAction === 'approve_recycle' && in_array($inspectionResult, ['recyclable', 'not_repairable'], true)) {
        return $inspectionResult === 'not_repairable' ? 'not_repairable' : 'recycled';
    }
    return null;
}

function admin_can_override_to(string $from, string $to): bool
{
    $all = array_keys(item_workflow_labels());
    if (!in_array($to, $all, true)) {
        return false;
    }
    if ($from === $to) {
        return true;
    }
    if (in_array($to, ['listed_for_sale', 'sold'], true) && !in_array($from, ['approved_for_sale', 'ready_for_marketplace', 'listed_for_sale'], true)) {
        return false;
    }
    return true;
}

function marketplace_listable_statuses(): array
{
    return ['approved_for_sale', 'ready_for_marketplace'];
}
