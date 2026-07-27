<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Image;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

it('renders mimes: rules as a File with dot-prefixed accepted types', function (): void {
    $file = novaFileField(['required', 'file', 'mimes:pdf,docx']);

    expect($file::class)->toBe(File::class)
        ->and($file->acceptedTypes)->toBe('.pdf,.docx');
});

it('renders image mimes as an Image field', function (): void {
    $file = novaFileField(['required', 'file', 'mimes:jpg,png']);

    expect($file::class)->toBe(Image::class)
        ->and($file->acceptedTypes)->toBe('.jpg,.png');
});

it('renders mimetypes: rules as a File with the raw mime accepted types', function (): void {
    $file = novaFileField(['required', 'mimetypes:application/pdf']);

    expect($file::class)->toBe(File::class)
        ->and($file->acceptedTypes)->toBe('application/pdf');
});

it('renders a bare file rule as a File without accepted types', function (): void {
    $file = novaFileField(['required', 'file']);

    expect($file::class)->toBe(File::class)
        ->and($file->acceptedTypes)->toBeNull();
});

it('stores an uploaded file and passes the path string to handle()', function (): void {
    Storage::fake('local');

    $captured = null;

    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'attachment', 'type' => 'string', 'rules' => ['required', 'file', 'mimes:pdf']],
    ];
    $action->handleImpl = function (array $inputs) use (&$captured, $action): array {
        $captured = $inputs['attachment'];

        return $action->succeed(['echoed' => 'stored']);
    };

    $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

    $action->asNovaAction(new ActionFields(collect(['attachment' => $file]), collect()), collect());

    expect($captured)->toBeString()->toStartWith('sipoc-probe/');
    Storage::disk('local')->assertExists($captured);
});
