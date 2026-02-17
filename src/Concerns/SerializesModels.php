<?php

namespace Opscale\Actions\Concerns;

use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;

trait SerializesModels
{
    use SerializesAndRestoresModelIdentifiers;

    protected function getModelParameters(): array
    {
        return collect($this->parameters())
            ->filter(fn ($parameter) => isset($parameter['type']) && class_exists($parameter['type']))
            ->pluck('name')
            ->all();
    }

    public function __serialize(): array
    {
        $modelKeys = $this->getModelParameters();

        $attributes = $this->attributes ?? [];

        foreach ($modelKeys as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $this->getSerializedPropertyValue($attributes[$key]);
            }
        }

        return ['attributes' => $attributes];
    }

    public function __unserialize(array $values): void
    {
        $this->attributes = $values['attributes'] ?? [];

        $modelKeys = $this->getModelParameters();

        foreach ($modelKeys as $key) {
            if (array_key_exists($key, $this->attributes)) {
                $this->attributes[$key] = $this->getRestoredPropertyValue($this->attributes[$key]);
            }
        }
    }
}
