<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Users\UserApprovalService;
use DomainException;
use Illuminate\Support\Facades\Auth;

class UserApprovalController extends Controller
{
    public function index()
    {
        $users = User::orderByDesc('created_at')->get(['id', 'name', 'email', 'approval_status', 'approved_at', 'approved_by', 'created_at']);
        $roles = fn (User $u) => ['super_admin' => $u->isSuperAdmin(), 'approver' => $u->hasGlobalRole('user-approver')];

        return view('admin.users', [
            'pending' => $users->where('approval_status', 'pending'),
            'approved' => $users->where('approval_status', 'approved')->values(),
            'rejected' => $users->where('approval_status', 'rejected'),
            'roles' => $roles,
            'isSuperAdmin' => Auth::user()->isSuperAdmin(),
            'me' => Auth::id(),
        ]);
    }

    public function approve(int $user, UserApprovalService $svc)
    {
        return $this->run(fn ($t) => $svc->approve($t, Auth::user()), $user, 'approved');
    }

    public function reject(int $user, UserApprovalService $svc)
    {
        return $this->run(fn ($t) => $svc->reject($t, Auth::user()), $user, 'rejected');
    }

    public function makeApprover(int $user, UserApprovalService $svc)
    {
        return $this->run(fn ($t) => $svc->makeApprover($t, Auth::user()), $user, 'appointed as an approver');
    }

    public function removeApprover(int $user, UserApprovalService $svc)
    {
        return $this->run(fn ($t) => $svc->removeApprover($t, Auth::user()), $user, 'removed as an approver');
    }

    private function run(callable $action, int $id, string $what)
    {
        $target = User::findOrFail($id);
        try {
            $action($target);
        } catch (DomainException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return back()->with('message', "{$target->name} {$what}.");
    }
}
