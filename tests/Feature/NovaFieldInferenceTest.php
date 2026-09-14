<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\URL;

it('renders an in: rule as a Select with the rule values', function (): void {
    $field = novaInferredField(['name' => 'status', 'type' => 'string', 'rules' => ['required', 'in:a,b,c']]);

    expect($field::class)->toBe(Select::class);
    assert($field instanceof Select);
    expect(value($field->optionsCallback))->toBe(['a' => 'a', 'b' => 'b', 'c' => 'c']);
});

it('renders a date rule as Date when the name has no time hint', function (): void {
    $field = novaInferredField(['name' => 'birthday', 'type' => 'string', 'rules' => ['required', 'date']]);

    expect($field::class)->toBe(Date::class);
});

it('renders a date rule as DateTime when the name ends in _at', function (): void {
    $field = novaInferredField(['name' => 'published_at', 'type' => 'string', 'rules' => ['required', 'date']]);

    expect($field::class)->toBe(DateTime::class);
});

it('renders a date_format rule with time tokens as DateTime', function (): void {
    $field = novaInferredField(['name' => 'window', 'type' => 'string', 'rules' => ['required', 'date_format:Y-m-d H:i']]);

    expect($field::class)->toBe(DateTime::class);
});

it('renders a date-only date_format rule as Date', function (): void {
    $field = novaInferredField(['name' => 'window', 'type' => 'string', 'rules' => ['required', 'date_format:d/m/Y']]);

    expect($field::class)->toBe(Date::class);
});

it('renders numeric min:0 with a monetary name as Currency', function (): void {
    $field = novaInferredField(['name' => 'unit_price', 'type' => 'string', 'rules' => ['required', 'numeric', 'min:0']]);

    expect($field::class)->toBe(Currency::class);
});

it('renders a decimal:2 rule as Currency regardless of the name', function (): void {
    $field = novaInferredField(['name' => 'weight', 'type' => 'string', 'rules' => ['required', 'numeric', 'decimal:2']]);

    expect($field::class)->toBe(Currency::class);
});

it('renders numeric min:0 with a non-monetary name as Number', function (): void {
    $field = novaInferredField(['name' => 'retry_count', 'type' => 'string', 'rules' => ['required', 'numeric', 'min:0']]);

    expect($field::class)->toBe(Number::class);
});

it('renders a boolean rule as Boolean', function (): void {
    $field = novaInferredField(['name' => 'active', 'type' => 'string', 'rules' => ['required', 'boolean']]);

    expect($field::class)->toBe(Boolean::class);
});

it('renders an email rule as Email', function (): void {
    $field = novaInferredField(['name' => 'contact', 'type' => 'string', 'rules' => ['required', 'email']]);

    expect($field::class)->toBe(Email::class);
});

it('renders a url rule as URL', function (): void {
    $field = novaInferredField(['name' => 'website', 'type' => 'string', 'rules' => ['required', 'url']]);

    expect($field::class)->toBe(URL::class);
});

it('renders a json rule as Code', function (): void {
    $field = novaInferredField(['name' => 'payload', 'type' => 'string', 'rules' => ['required', 'json']]);

    expect($field::class)->toBe(Code::class);
});

it('renders string with max over 255 as Textarea', function (): void {
    $field = novaInferredField(['name' => 'notes', 'type' => 'string', 'rules' => ['required', 'string', 'max:500']]);

    expect($field::class)->toBe(Textarea::class);
});

it('renders string with min of at least 10 and no max as Textarea', function (): void {
    $field = novaInferredField(['name' => 'justification', 'type' => 'string', 'rules' => ['required', 'string', 'min:10']]);

    expect($field::class)->toBe(Textarea::class);
});

it('renders string with a small max as Text', function (): void {
    $field = novaInferredField(['name' => 'code', 'type' => 'string', 'rules' => ['required', 'string', 'max:100']]);

    expect($field::class)->toBe(Text::class);
});

it('renders an array parameter without options as KeyValue', function (): void {
    $field = novaInferredField(['name' => 'metadata', 'type' => 'array', 'rules' => ['required']]);

    expect($field::class)->toBe(KeyValue::class);
});

it('uses the rule-derived field instead of falling back to the primitive type', function (): void {
    $field = novaInferredField(['name' => 'start', 'type' => 'string', 'rules' => ['required', 'date']]);

    expect($field::class)->toBe(Date::class);
});

it('renders an exists: rule as a Select mapping the key to the name column', function (): void {
    Schema::create('probe_named', function ($table): void {
        $table->string('id')->primary();
        $table->string('name');
    });
    DB::table('probe_named')->insert([
        ['id' => '01A', 'name' => 'Alpha'],
        ['id' => '01B', 'name' => 'Beta'],
    ]);

    $field = novaInferredField(['name' => 'ref', 'type' => 'string', 'rules' => ['required', 'exists:probe_named,id']]);

    expect($field::class)->toBe(Select::class);
    assert($field instanceof Select);
    expect(value($field->optionsCallback))->toBe(['01A' => 'Alpha', '01B' => 'Beta']);
});

it('falls back to the key column when the exists: table has no name column', function (): void {
    // Transactional aggregates (portfolio_credits, write_offs, …) reference by
    // id and have no "name" column; the Select must not blow up on it.
    Schema::create('probe_nameless', function ($table): void {
        $table->string('id')->primary();
        $table->string('status');
    });
    DB::table('probe_nameless')->insert([
        ['id' => '01X', 'status' => 'open'],
        ['id' => '01Y', 'status' => 'closed'],
    ]);

    $field = novaInferredField(['name' => 'ref', 'type' => 'string', 'rules' => ['required', 'exists:probe_nameless,id']]);

    expect($field::class)->toBe(Select::class);
    assert($field instanceof Select);
    expect(value($field->optionsCallback))->toBe(['01X' => '01X', '01Y' => '01Y']);
});
