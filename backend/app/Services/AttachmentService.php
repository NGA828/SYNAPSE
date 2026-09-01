<?php

namespace App\Services;

use App\Contracts\HasAttachments;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores uploaded files and decides who may read them back.
 *
 * One service for every attachable model so the rules cannot drift between
 * homework, submissions and (later) course materials.
 *
 * Files always land on a private disk — never `public` — and the download path
 * re-checks authorization on every request instead of trusting the URL.
 */
class AttachmentService
{
    /**
     * Store an upload and attach it to its owner.
     */
    public function store(
        Model $attachable,
        UploadedFile $file,
        User $uploader,
        string $role,
        string $visibility = Attachment::VISIBILITY_CLASS,
    ): Attachment {
        $this->assertWithinLimit($attachable);

        $extension = strtolower($file->getClientOriginalExtension());

        abort_unless(
            in_array($extension, config('synapse.attachments.mimes'), true),
            422,
            'That file type is not allowed. Accepted: '
                .implode(', ', config('synapse.attachments.mimes')).'.',
        );

        $disk = config('synapse.attachments.disk', 'local');
        $original = $file->getClientOriginalName();

        // Never trust the client's filename for the stored path: slug it, keep
        // the extension, and prefix a random segment so paths are unguessable.
        $storedName = Str::slug(pathinfo($original, PATHINFO_FILENAME) ?: 'file')
            .'-'.Str::lower(Str::random(12))
            .'.'.$extension;

        $directory = 'attachments/'
            .$attachable->school_id
            .'/'.Str::snake(class_basename($attachable));

        $path = $file->storeAs($directory, $storedName, ['disk' => $disk]);

        abort_unless($path, 500, 'The file could not be stored. Please try again.');

        return Attachment::create([
            'school_id' => $attachable->school_id,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'uploaded_by_role' => $role,
            'uploaded_by' => $uploader->id,
            'file_name' => $original,
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'disk' => $disk,
            'path' => $path,
            'visibility' => $visibility,
        ]);
    }

    /**
     * Store several uploads, skipping any that are absent.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Attachment>
     */
    public function storeMany(
        Model $attachable,
        array $files,
        User $uploader,
        string $role,
        string $visibility = Attachment::VISIBILITY_CLASS,
    ): Collection {
        return collect($files)
            ->filter()
            ->take(config('synapse.attachments.max_per_record'))
            ->map(fn (UploadedFile $file) => $this->store($attachable, $file, $uploader, $role, $visibility))
            ->values();
    }

    /**
     * Attachments belonging to one record.
     *
     * @return Collection<int, Attachment>
     */
    public function for(Model $attachable, ?string $visibility = null): Collection
    {
        return $attachable->attachments()
            ->when($visibility, fn ($query) => $query->where('visibility', $visibility))
            ->latest('id')
            ->get();
    }

    /**
     * Authorize a download, then stream the file.
     *
     * The caller passes the *viewer* and the attachment's owner record so the
     * rules can be evaluated with full context.
     */
    public function download(Attachment $attachment, User $viewer, Model $attachable): StreamedResponse
    {
        $this->authorize($attachment, $viewer, $attachable);

        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            410,
            'The stored file is no longer available.',
        );

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    /**
     * Who may read this file.
     *
     * The owning model answers the ownership and enrolment questions via the
     * HasAttachments contract, so this method stays correct for every
     * attachable model without knowing what any of them are.
     */
    public function authorize(Attachment $attachment, User $viewer, Model $attachable): void
    {
        abort_unless(
            $attachable instanceof HasAttachments,
            403,
            'This file belongs to a record type that does not support downloads.',
        );

        // Admins of the same school can always retrieve a file.
        if ($viewer->isAdmin() && $viewer->school_id === $attachment->school_id) {
            return;
        }

        if ($viewer->isTeacher()) {
            $teacher = $viewer->teacher;

            abort_unless($teacher, 403, 'No teacher profile is attached to this account.');
            abort_unless(
                $attachable->ownedByTeacher($teacher),
                403,
                'This file belongs to another teacher\'s work.',
            );

            return;
        }

        if ($viewer->isStudent()) {
            $student = $viewer->student;

            abort_unless($student, 403, 'No student profile is attached to this account.');

            // Their own upload: always readable by them.
            if ($attachment->uploaded_by === $viewer->id) {
                return;
            }

            abort_if($attachment->isPrivate(), 403, 'This file is not available to you.');

            abort_unless(
                $attachable->readableByStudent($student),
                403,
                'You are not enrolled in the class this file belongs to.',
            );

            return;
        }

        abort(403, 'You do not have permission to download this file.');
    }

    public function delete(Attachment $attachment): void
    {
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();
    }

    private function assertWithinLimit(Model $attachable): void
    {
        $max = (int) config('synapse.attachments.max_per_record');

        abort_if(
            $attachable->attachments()->count() >= $max,
            422,
            "A maximum of {$max} files can be attached.",
        );
    }
}
