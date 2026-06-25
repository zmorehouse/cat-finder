<?php

namespace App\Http\Controllers;

use App\Models\AdoptionAnimal;
use App\Models\NotificationLog;
use App\Services\AdoptionWatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $logs = NotificationLog::query()
            ->latest()
            ->paginate(6, ['*'], 'logPage');

        $tab = $request->input('tab', 'available');

        $animals = AdoptionAnimal::query()
            ->when($tab === 'adopted', fn ($q) => $q->whereNotNull('removed_at'))
            ->when($tab !== 'adopted', fn ($q) => $q->whereNull('removed_at'))
            ->orderByDesc('first_seen_at')
            ->paginate(20, ['*'], 'animalPage');

        $availableCount = AdoptionAnimal::whereNull('removed_at')->count();
        $adoptedCount = AdoptionAnimal::whereNotNull('removed_at')->count();

        $twilioConfigured = (bool) (
            config('catfinder.vonage.api_key')
            && config('catfinder.vonage.api_secret')
            && config('catfinder.vonage.from')
            && config('catfinder.vonage.to')
        );

        return view('dashboard', [
            'logs' => $logs,
            'animals' => $animals,
            'tab' => $tab,
            'availableCount' => $availableCount,
            'adoptedCount' => $adoptedCount,
            'twilioConfigured' => $twilioConfigured,
            'notificationsEnabled' => Cache::get('notifications_enabled', true),
            'recipient' => config('catfinder.vonage.to'),
        ]);
    }

    public function check(Request $request, AdoptionWatcher $watcher): RedirectResponse
    {
        try {
            $summary = $watcher->run();
            $request->session()->flash('status', $summary['message']);
        } catch (Throwable $e) {
            $request->session()->flash('error', 'Check failed: '.$e->getMessage());
        }

        return redirect()->route('dashboard');
    }

    public function toggleNotifications(Request $request): RedirectResponse
    {
        $current = Cache::get('notifications_enabled', true);
        Cache::forever('notifications_enabled', ! $current);

        return redirect()->route('dashboard');
    }
}
