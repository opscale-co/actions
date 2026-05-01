<?php

declare(strict_types=1);

use Opscale\Actions\Action;

test('the Pest + Testbench harness boots', function (): void {
    expect(app())->toBeInstanceOf(\Illuminate\Foundation\Application::class);
});

test('the package Action base class is loaded', function (): void {
    expect(class_exists(Action::class))->toBeTrue();
});
