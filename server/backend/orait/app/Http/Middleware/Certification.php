<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Session;
use App\Http\Controllers\CODE;
use Closure;
use App\Http\Controllers\Controller;

class Certification
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
        if ($request->path() != 'api/login' && $request->path() != 'api/logout' && null == Session::get('username')) {
            //this class is not controller, so this result will not be auto generated to response, will fail.
            return json_encode(CODE::NOT_LOGIN);
        }
        return $next($request);
    }
}
