<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $users = User::query()
            ->with(['subscriptions' => fn ($s) => $s->orderByDesc('created_at')])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('email', 'like', '%' . $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                if ($status === 'master') {
                    $query->where('is_master_admin', true);
                    return;
                }
                if ($status === 'subscribed') {
                    $query->whereHas('subscriptions', fn ($s) => $s->where('type', 'default')->whereIn('stripe_status', ['active', 'trialing']));
                    return;
                }
                if ($status === 'trial') {
                    $query->where(function ($w) {
                        $w->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now());
                    });
                    return;
                }
                if ($status === 'unpaid') {
                    $query->where('is_master_admin', false)
                        ->whereNull('onboarding_completed_at')
                        ->whereDoesntHave('subscriptions', fn ($s) => $s->where('type', 'default')->whereIn('stripe_status', ['active', 'trialing']));
                    return;
                }
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.subscriptions.index', compact('users', 'q', 'status'));
    }
}

