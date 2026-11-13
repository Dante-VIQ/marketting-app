<?php

namespace App\Http\Controllers;

use App\Mail\BrandInvitationMail;
use App\Models\Brand;
use App\Models\BrandInvitation;
use App\Services\BrandManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class BrandInvitationController extends Controller
{
    public function __construct(private BrandManagementService $brandService) {}

    /**
     * Send an invitation email for a brand.
     */
    public function invite(Request $request, Brand $brand)
    {
        $this->authorizeOwnerOrAdmin($brand);

        $data = $request->validate([
            'email' => 'required|email|max:255',
            'role'  => 'required|in:admin,editor,viewer',
        ]);

        // Don't invite someone already on the brand
        if ($brand->users()->where('users.email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'That person already has access to this brand.']);
        }

        // Upsert: replace any old pending invite for the same email+brand
        BrandInvitation::where('brand_id', $brand->id)
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->delete();

        $invitation = BrandInvitation::create([
            'brand_id'   => $brand->id,
            'invited_by' => Auth::id(),
            'email'      => $data['email'],
            'role'       => $data['role'],
            'token'      => BrandInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load('brand', 'inviter');

        Mail::to($invitation->email)->send(new BrandInvitationMail($invitation));

        return back()->with('message', "Invitation sent to {$data['email']}.");
    }

    /**
     * Accept page — shown when the invitee clicks the link.
     * If they're logged in, accept immediately. Otherwise, redirect to register/login.
     */
    public function accept(string $token)
    {
        $invitation = BrandInvitation::where('token', $token)
            ->whereNull('accepted_at')
            ->with('brand', 'inviter')
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return view('invitations.expired', compact('invitation'));
        }

        if (!Auth::check()) {
            // Store token in session and redirect to login/register
            session(['invitation_token' => $token]);
            return redirect()->route('register')
                ->with('message', "Create an account to join {$invitation->brand->name} on Vumbi AI.");
        }

        return $this->processAccept($invitation);
    }

    /**
     * Called after login/register to redeem a pending session token.
     */
    public function redeemFromSession()
    {
        $token = session('invitation_token');
        if (!$token) {
            return redirect()->route('dashboard');
        }

        $invitation = BrandInvitation::where('token', $token)
            ->whereNull('accepted_at')
            ->with('brand', 'inviter')
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            session()->forget('invitation_token');
            return redirect()->route('dashboard')
                ->with('warning', 'Your invitation has expired or is no longer valid.');
        }

        return $this->processAccept($invitation);
    }

    /**
     * Remove a member from a brand.
     */
    public function removeMember(Request $request, Brand $brand)
    {
        $this->authorizeOwnerOrAdmin($brand);

        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        // Can't remove yourself as the sole owner
        if ($data['user_id'] == Auth::id()) {
            return back()->withErrors(['Cannot remove yourself. Transfer ownership first.']);
        }

        $target = \App\Models\User::findOrFail($data['user_id']);
        $this->brandService->removeUserFromBrand($brand, $target, Auth::user());

        return back()->with('message', "{$target->name} has been removed from {$brand->name}.");
    }

    /**
     * Revoke a pending invitation.
     */
    public function revoke(BrandInvitation $invitation)
    {
        $this->authorizeOwnerOrAdmin($invitation->brand);

        if ($invitation->accepted_at) {
            return back()->withErrors(['This invitation has already been accepted.']);
        }

        $invitation->delete();

        return back()->with('message', "Invitation to {$invitation->email} revoked.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function processAccept(BrandInvitation $invitation): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        try {
            $this->brandService->assignUserToBrand(
                $invitation->brand,
                $user,
                $invitation->role,
                null
            );
        } catch (\InvalidArgumentException $e) {
            // Role doesn't exist for this brand — fall back to 'editor'
            $this->brandService->assignUserToBrand($invitation->brand, $user, 'editor', null);
        }

        $invitation->update(['accepted_at' => now()]);
        session()->forget('invitation_token');

        // Switch to the newly joined brand
        $user->switchBrand($invitation->brand);

        return redirect()->route('dashboard', ['brand' => $invitation->brand->slug])
            ->with('message', "Welcome! You now have {$invitation->role} access to {$invitation->brand->name}.");
    }

    private function authorizeOwnerOrAdmin(Brand $brand): void
    {
        $user = Auth::user();
        abort_unless(
            $user->isSuperAdmin() || $brand->isAdmin($user),
            403,
            'Only brand admins can manage members.'
        );
    }
}
