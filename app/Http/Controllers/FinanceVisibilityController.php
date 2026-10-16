<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\Finance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** Olhinho dos valores: mostra os valores financeiros após confirmar a senha. */
class FinanceVisibilityController extends Controller
{
    public function show(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $key = 'finance-unlock:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('finance_error', 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($key).' segundos.');
        }

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            RateLimiter::hit($key, 60);
            ActivityLog::record('finance.failed', 'Errou a senha ao tentar ver os valores financeiros');

            return back()->with('finance_error', 'Senha incorreta.');
        }

        RateLimiter::clear($key);
        Finance::unlock();
        ActivityLog::record('finance.unlocked', 'Desbloqueou a visualização dos valores financeiros');

        return back()->with('success', 'Valores visíveis por '.Finance::minutes().' minutos.');
    }

    public function hide(): RedirectResponse
    {
        Finance::lock();

        return back()->with('success', 'Valores ocultos.');
    }
}
