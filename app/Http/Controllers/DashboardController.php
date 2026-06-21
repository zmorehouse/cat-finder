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
            config('catfinder.twilio.sid')
            && config('catfinder.twilio.token')
            && config('catfinder.twilio.from')
            && config('catfinder.twilio.to')
        );

        return view('dashboard', [
            'logs' => $logs,
            'animals' => $animals,
            'twilioConfigured' => $twilioConfigured,
            'recipient' => config('catfinder.twilio.to'),
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
