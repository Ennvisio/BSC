<?php

namespace App\Http\Middleware;

use Closure;

class MemberMiddleWare
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
        if (auth()->check()) {
            if(auth()->user()->role->role=='second-engineer' || auth()->user()->role->role=='chief-officer'){
             return $next($request);
         }else{
              return redirect('/home');
         }
        }
       
       return redirect('/');
   }
}
