<?php

declare(strict_types=1);

it('humanizes the parameter name as the field label by default', function (): void {
    $field = novaInferredField(['name' => 'assessment_types', 'type' => 'string', 'rules' => ['required', 'string']]);

    expect($field->name)->toBe('Assessment types');
});

it('translates the humanized label through the host translator', function (): void {
    app('translator')->addLines(['*.Assessment types' => 'Tipos de prueba'], 'es');
    app()->setLocale('es');

    $field = novaInferredField(['name' => 'assessment_types', 'type' => 'string', 'rules' => ['required', 'string']]);

    expect($field->name)->toBe('Tipos de prueba');
});

it('uses an explicit label verbatim over the translated fallback', function (): void {
    app('translator')->addLines(['*.Interviewer id' => 'Should not win'], 'es');
    app()->setLocale('es');

    $field = novaInferredField([
        'name' => 'interviewer_id',
        'label' => 'Interviewer',
        'type' => 'string',
        'rules' => ['required', 'string'],
    ]);

    expect($field->name)->toBe('Interviewer');
});

it('applies the explicit label to option-driven Select fields too', function (): void {
    $field = novaInferredField([
        'name' => 'assessment_types',
        'label' => 'Tipos de prueba',
        'type' => 'array',
        'rules' => ['required', 'array'],
        'options' => ['disc' => 'DISC', 'iq' => 'IQ'],
    ]);

    expect($field->name)->toBe('Tipos de prueba');
});
