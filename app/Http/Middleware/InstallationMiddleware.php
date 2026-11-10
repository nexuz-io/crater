<?php

namespace Crater\Http\Middleware;

use Closure;
use Crater\Models\Setting;

class InstallationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! database_is_ready()) {
            return redirect('/installation');
        }

        if (Setting::getSetting('profile_complete') !== 'COMPLETED') {
            return redirect('/installation');
        }

        return $next($request);
    }
}
