<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Nova\Fields\Select;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

beforeEach(function (): void {
    Schema::create('statuses', function (Blueprint $blueprint): void {
        $blueprint->string('id')->primary();
        $blueprint->string('name');
    });

    DB::table('statuses')->insert([
        ['id' => 'uuid-1', 'name' => 'Open'],
        ['id' => 'uuid-2', 'name' => 'Closed'],
    ]);
});

it('renders an exists: rule as a lazy Select plucked from the referenced table', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status_id', 'type' => 'string', 'rules' => ['required', 'uuid', 'exists:statuses,id']],
    ];

    DB::enableQueryLog();
    $field = $action->getActionFields()[0];

    expect($field)->toBeInstanceOf(Select::class)
        ->and($field->optionsCallback)->toBeInstanceOf(Closure::class)
        ->and(DB::getQueryLog())->toBe([])
        ->and(value($field->optionsCallback))->toBe(['uuid-1' => 'Open', 'uuid-2' => 'Closed']);
});

it('defaults the exists: key column to id when the rule omits it', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status_id', 'type' => 'string', 'rules' => ['required', 'exists:statuses']],
    ];

    $field = $action->getActionFields()[0];

    expect($field)->toBeInstanceOf(Select::class)
        ->and(value($field->optionsCallback))->toBe(['uuid-1' => 'Open', 'uuid-2' => 'Closed']);
});

it('renders a uuid rule without any options source as an empty Select and warns', function (): void {
    Log::shouldReceive('warning')->once();

    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'record_id', 'type' => 'string', 'rules' => ['required', 'uuid']],
    ];

    $field = $action->getActionFields()[0];

    expect($field)->toBeInstanceOf(Select::class)
        ->and(value($field->optionsCallback))->toBe([]);
});
