<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Controllers;

use App\Models\User;
use App\Modules\Audit\Audit;
use App\Modules\Files\FileStorage;
use App\Modules\Pets\Http\Resources\PetDocumentResource;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Models\PetDocument;
use App\Support\Digits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Pet documents: national ID record, clinic ID, certificates (FR-1.7). */
class PetDocumentController
{
    public function __construct(private readonly FileStorage $files) {}

    public function index(Pet $pet): AnonymousResourceCollection
    {
        Gate::authorize('view', $pet);

        return PetDocumentResource::collection($pet->documents()->latest()->get());
    }

    public function store(Request $request, Pet $pet): JsonResponse
    {
        Gate::authorize('update', $pet);
        $data = $this->validated($request, null);

        /** @var User $user */
        $user = $request->user();

        $document = new PetDocument([
            'kind' => $data['kind'],
            'number' => $data['number'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'uploaded_by' => $user->id,
        ]);
        if ($request->hasFile('file')) {
            $document->file_path = $this->files->store($request->file('file'), 'pet-documents');
            $document->file_mime = $request->file('file')->getMimeType();
        }
        $pet->documents()->save($document);

        return (new PetDocumentResource($document))->response()->setStatusCode(201);
    }

    /** Editing the number or replacing the scan clears verification (see PetDocument::booted). */
    public function update(Request $request, Pet $pet, PetDocument $document): PetDocumentResource
    {
        Gate::authorize('update', $pet);
        abort_unless($document->pet_id === $pet->id, 404);
        $data = $this->validated($request, $document);

        $document->fill(array_intersect_key($data, array_flip(['number', 'issued_at'])));
        $old = null;
        if ($request->hasFile('file')) {
            $old = $document->file_path;
            $document->file_path = $this->files->store($request->file('file'), 'pet-documents');
            $document->file_mime = $request->file('file')->getMimeType();
        }
        $document->save();
        $this->files->delete($old);

        return new PetDocumentResource($document);
    }

    public function destroy(Pet $pet, PetDocument $document): JsonResponse
    {
        Gate::authorize('update', $pet);
        abort_unless($document->pet_id === $pet->id, 404);

        // Soft delete: the scan stays for the audit trail.
        $document->delete();

        return response()->json(status: 204);
    }

    /** Staff only: mark a document checked against the original, or clear that mark. */
    public function verify(Request $request, PetDocument $document): PetDocumentResource
    {
        Gate::authorize('verifyDocument', [Pet::class, $document]);
        $data = $request->validate(['verified' => ['required', 'boolean']]);

        /** @var User $staff */
        $staff = $request->user();
        $document->markVerified($staff, (bool) $data['verified']);
        Audit::record($data['verified'] ? 'pet_document.verified' : 'pet_document.unverified', $document);

        return new PetDocumentResource($document);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PetDocument $existing): array
    {
        if ($request->filled('number')) {
            $request->merge(['number' => Digits::toLatin(trim((string) $request->input('number')))]);
        }

        $kind = $request->input('kind', $existing?->kind);
        $creating = $existing === null;

        $numberRules = ['nullable', 'string', 'max:64', Rule::requiredIf($creating && $kind === PetDocument::KIND_NATIONAL)];
        if ($kind === PetDocument::KIND_NATIONAL) {
            // Mirrors the partial unique index on live national documents.
            $numberRules[] = Rule::unique('pet_documents', 'number')
                ->where('kind', PetDocument::KIND_NATIONAL)
                ->whereNull('deleted_at')
                ->ignore($existing?->id);
        }

        return $request->validate([
            'kind' => [$creating ? 'required' : 'prohibited', Rule::in(PetDocument::KINDS)],
            'number' => $numberRules,
            'issued_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'file' => [
                Rule::requiredIf($creating && $kind === PetDocument::KIND_NATIONAL),
                'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('files.max_document_kb'),
            ],
        ], [
            'number.unique' => __('pets.national_number_taken'),
        ]);
    }
}
