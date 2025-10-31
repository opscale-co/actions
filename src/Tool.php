<?php

namespace :namespace_vendor\:namespace_tool_name;

use Laravel\Nova\Tool as NovaTool;
use Laravel\Nova\Nova;
use Laravel\Nova\Menu\MenuSection;
use Illuminate\Http\Request;

class Tool extends NovaTool
{
    public function boot()
    {
        Nova::script(':package_name', __DIR__.'/../dist/js/tool.js');
        Nova::style(':package_name', __DIR__.'/../dist/css/tool.css');
    }

    public function menu(Request $request)
    {
        return MenuSection::make(':namespace_tool_name')
            ->path(':package_name')
            ->icon('server');
    }
}
