<?php

namespace App\View\Components\Dashboard;

use Illuminate\View\Component;

class Kpi extends Component
{
    public string $title;
    public string|int|float $value;
    public ?float $percentage;
    public ?string $unit;
    public string $color;

    /**
     * Create a new component instance.
     */
    public function __construct(
        string $title,
               $value,
        string $color = 'info',
        float $percentage = null,
        string $unit = null
    ) {
        $this->title = $title;
        $this->value = $value;
        $this->percentage = $percentage;
        $this->unit = $unit;
        $this->color = $color;
    }

    public function render()
    {
        return view('components.dashboard.kpi');
    }
}
