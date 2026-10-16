<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'financeMinutes' => (int) Setting::get('finance_minutes'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'finance_minutes' => ['required', 'integer', 'in:5,15,30,60'],
        ], [
        ], [
            'finance_minutes' => 'tempo dos valores visíveis',
        ]);

        Setting::put('finance_minutes', (string) $data['finance_minutes']);
        ActivityLog::record('settings.updated', 'Alterou as configurações (tempo dos valores visíveis)');

        return back()->with('success', 'Configurações salvas.');
    }
}
