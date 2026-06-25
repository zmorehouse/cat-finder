<?php

namespace App\Http\Controllers;

use App\Models\AdoptionAnimal;
use App\Models\NotificationLog;
use App\Services\AdoptionWatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function index(): View
    {
        $logs = NotificationLog::query()
            ->latest()
            ->paginate(15);

        $animals = AdoptionAnimal::query()
            ->orderByDesc('first_seen_at')
            ->get();

        $twilioConfigured = (bool) (
            config('catfinder.vonage.api_key')
            && config('catfinder.vonage.api_secret')
            && config('catfinder.vonage.from')
            && config('catfinder.vonage.to')
        );

        return view('dashboard', [
            'logs' => $logs,
            'animals' => $animals,
            'twilioConfigured' => $twilioConfigured,
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
}
