<?php

namespace App\Actions\SubmissionGalleys;

use App\Constants\SubmissionFileCategory;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionGalley;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateSubmissionGalleyAction
{
    use AsAction;

    public function handle(Submission $submission, array $data, ?SpatieMediaLibraryFileUpload $componentMedia): SubmissionGalley
    {
        try {
            DB::beginTransaction();

            $submissionGalley = $submission->galleys()->create($data);

            if ($media = data_get($data, 'media')) {
                $temporaryFileUpload = $componentMedia->getState();
                $fileName = data_get($data, 'media.name') ?? null;
                $saveGalleyMedia = $this->saveUploadedMedia($submissionGalley, reset($temporaryFileUpload), $componentMedia, $fileName);

                $files = SubmissionFile::create([
                    'submission_id' => $submission->id,
                    'media_id' => $saveGalleyMedia->id,
                    'submission_file_type_id' => $media['type'],
                    'category' => SubmissionFileCategory::GALLEY_FILES,
                ]);

                $submissionGalley->update([
                    'submission_file_id' => $files->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $submissionGalley;
    }

    private function saveUploadedMedia(SubmissionGalley $record, $file, ?SpatieMediaLibraryFileUpload $component, ?string $customFileName = null)
    {
        $mediaAdder = $record->addMediaFromString($file->get());

        $fileExtension = $file->getClientOriginalExtension();
        $filename = $customFileName ? $customFileName.'.'.$fileExtension : $component->getUploadedFileNameForStorage($file);

        $media = $mediaAdder
            ->addCustomHeaders($component->getCustomHeaders())
            ->usingFileName($filename)
            ->usingName($customFileName ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->storingConversionsOnDisk($component->getConversionsDisk() ?? '')
            ->withCustomProperties($component->getCustomProperties())
            ->withManipulations($component->getManipulations())
            ->withResponsiveImagesIf($component->hasResponsiveImages())
            ->withProperties($component->getProperties())
            ->toMediaCollection($component->getCollection() ?? 'default', $component->getDiskName());

        return $media;
    }
}
