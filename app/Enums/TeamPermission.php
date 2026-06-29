<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ManageApiKeys = 'api-key:manage';
    case ManageQuota = 'quota:manage';
    case ViewUsage = 'usage:view';
    case ManageGatewayConfig = 'gateway:manage';
    case ManageEntitlements = 'entitlement:manage';
    case ViewBilling = 'billing:view';
    case ManageBilling = 'billing:manage';
}
