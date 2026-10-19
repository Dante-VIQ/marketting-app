<?php

namespace App\Services\Users;

use App\Models\User;
use DomainException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may approve whom. Approvers (super-admin, or someone the super-admin appointed) approve or
 * reject sign-ups. Only the super-admin appoints or removes approvers. Nobody can act on themselves,
 * and nobody but a super-admin can touch a super-admin.
 */
class UserApprovalService
{
    public function approve(User $target, User $actor): void
    {
        $this->guardApprovalChange($target, $actor);
        $target->forceFill(['approval_status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->getKey()])->save();
    }

    public function reject(User $target, User $actor): void
    {
        $this->guardApprovalChange($target, $actor);
        $target->forceFill(['approval_status' => 'rejected', 'approved_at' => null, 'approved_by' => $actor->getKey()])->save();
    }

    public function makeApprover(User $target, User $actor): void
    {
        $this->guardRoleChange($target, $actor);
        if (!$target->isApproved()) {
            throw new DomainException('Approve this person first; only approved users can become approvers.');
        }
        if (!$target->hasGlobalRole('user-approver')) {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
            $target->assignRole(Role::firstOrCreate(['name' => 'user-approver', 'guard_name' => 'web']));
        }
    }

    public function removeApprover(User $target, User $actor): void
    {
        $this->guardRoleChange($target, $actor);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $role = Role::where('name', 'user-approver')->where('guard_name', 'web')->first();
        if ($role) {
            $target->removeRole($role);
        }
    }

    private function guardApprovalChange(User $target, User $actor): void
    {
        if (!$actor->isApprover()) {
            throw new DomainException('Only approvers can approve or reject sign-ups.');
        }
        if ($target->is($actor)) {
            throw new DomainException('You cannot change your own approval.');
        }
        if ($target->isSuperAdmin()) {
            throw new DomainException('The platform administrator cannot be approved or rejected.');
        }
    }

    private function guardRoleChange(User $target, User $actor): void
    {
        if (!$actor->isSuperAdmin()) {
            throw new DomainException('Only the platform administrator can appoint or remove approvers.');
        }
        if ($target->isSuperAdmin()) {
            throw new DomainException('The platform administrator already has every permission.');
        }
    }
}
