<?php

namespace :namespace_vendor\:namespace_tool_name\Http\Middleware;

use Illuminate\Http\Request;
use :namespace_vendor\:namespace_tool_name\Tool;
use Laravel\Nova\Nova;

class Authorize
{
    public function handle(Request $request, $next)
    {
        $tool = collect(Nova::registeredTools())->first([$this, 'matchesTool']);

        return optional($tool)->authorize($request) ? $next($request) : abort(403);
    }

    public function matchesTool($tool)
    {
        return $tool instanceof Tool;
    }
}