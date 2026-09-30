<?php

namespace App\Http\Requests\Upload;

use App\Domain\Media\Data\CreateUploadIntentData;
use App\Domain\Media\Enums\MediaCollection;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * First-pass filter on what the browser declares (specs/10 §3 "Intent endpoint rules"). The worker
 * re-checks the real bytes, so nothing here is trusted beyond choosing limits.
 */
class CreateUploadIntentRequest extends FormRequest
{
    /**
     * Authorization runs in UploadIntentService through MediaPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collection' => ['required', 'string', Rule::in(array_map(fn (MediaCollection $c): string => $c->value, MediaCollection::uploadable()))],
            'filename' => ['required', 'string', 'max:'.(int) config('media.filename.input_max_length')],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Limits that depend on the chosen collection.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $collection = $this->collection();
                $kind = $collection->kind()->value;

                /** @var array<string, string> $mimes */
                $mimes = config("media.{$kind}.mimes", []);
                /** @var list<string> $extensions */
                $extensions = config("media.{$kind}.extensions", []);
                $extension = strtolower(pathinfo($this->string('filename')->toString(), PATHINFO_EXTENSION));

                if (! array_key_exists($this->string('mime')->toString(), $mimes) || ! in_array($extension, $extensions, true)) {
                    $validator->errors()->add('mime', 'This file type is not supported. Use a '.config("media.{$kind}.types_label").' image.');
                }

                if ($this->integer('size') > $collection->maxBytes()) {
                    $validator->errors()->add('size', 'This file is larger than '.Number::fileSize($collection->maxBytes()).'.');
                }
            },
        ];
    }

    public function toData(): CreateUploadIntentData
    {
        return new CreateUploadIntentData(
            collection: $this->collection(),
            filename: $this->string('filename')->toString(),
            sizeBytes: $this->integer('size'),
            mimeType: $this->string('mime')->toString(),
        );
    }

    private function collection(): MediaCollection
    {
        return MediaCollection::from($this->string('collection')->toString());
    }
}
