<?php

declare(strict_types=1);

use Opscale\Actions\Decorators\NovaActionDecorator;
use Opscale\Actions\Tests\Fixtures\CustomMetaAction;
use Opscale\Actions\Tests\Fixtures\MetaProbeAction;
use Opscale\Actions\Tests\Fixtures\PropertyMetaAction;

it('applies the meta declared by the wrapped action through getActionMeta', function (): void {
    $decorator = new NovaActionDecorator(new CustomMetaAction);

    expect($decorator->jsonSerialize())->toMatchArray([
        'detachedAction' => true,
        'label' => 'Run now',
    ]);
});

it('applies the meta declared by the wrapped action through the actionMeta property', function (): void {
    $decorator = new NovaActionDecorator(new PropertyMetaAction);

    expect($decorator->jsonSerialize())->toMatchArray(['icon' => 'lock-closed']);
});

it('adds no extra meta for an action without getActionMeta or actionMeta', function (): void {
    $decorator = new NovaActionDecorator(new MetaProbeAction);

    expect($decorator->meta)->toBe([]);
});
