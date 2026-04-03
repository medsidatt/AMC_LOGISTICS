<?php

namespace App\View\Components\layouts;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MainHorizontal extends Component
{

    // title
    public string $title = '';
    // actions
    public array $actions = [];
    // breadcrumbs
    public array $breadcrumbs = [];

    public function __construct($actions = [], $title = '',  $breadcrumbs = [])
    {
        $this->title = $title;
        $this->actions = $actions;
        $this->breadcrumbs = $breadcrumbs;
    }

    public function render(): View
    {
        return view('components.main-horizontal');
    }
}
