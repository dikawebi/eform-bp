<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20)->withQueryString();
        $notifications->setCollection($notifications->getCollection()->map(fn ($notification) => [
            'id' => $notification->id,
            'title' => (string) data_get($notification->data, 'title', 'Notifikasi eForm BP'),
            'message' => (string) data_get($notification->data, 'message', ''),
            'url' => str_starts_with((string) data_get($notification->data, 'url', ''), '/') ? data_get($notification->data, 'url') : route('dashboard'),
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ]));

        return Inertia::render('Notifications/Index', ['notifications' => $notifications]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
