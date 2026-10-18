<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MakeSuperAdmin extends Command
{
    protected $signature = 'user:make-super-admin {email : Email of an existing user} {--revoke : Remove the role instead}';
    protected $description = 'Grant (or revoke) the global super-admin role, which unlocks the System Docs page';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('No user with that email.');
            return self::FAILURE;
        }

        // The role is global, not tied to a brand.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        if ($this->option('revoke')) {
            $user->removeRole($role);
            $this->info("Removed super-admin from {$user->email}.");
        } else {
            $user->assignRole($role);
            $this->info("{$user->email} is now a super-admin.");
        }

        return self::SUCCESS;
    }
}
