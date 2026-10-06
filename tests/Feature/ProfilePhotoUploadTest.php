<?php

namespace Tests\Feature;

use App\Http\Controllers\FileUploadController;
use App\Models\Conference;
use App\Models\User;
use App\Panel\Administration\Pages\Profile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Tests\TestCase;

class ProfilePhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('tmp-for-tests');
        Queue::fake();
    }

    public function test_profile_photo_rejects_svg_file(): void
    {
        [$component, $user] = $this->submitProfilePhoto('avatar.svg', $this->svgBytes());

        $component->assertHasErrors('informationFormData.profile');
        $this->assertFalse($user->hasMedia('profile'));
    }

    public function test_profile_photo_rejects_svg_disguised_as_png(): void
    {
        [$component, $user] = $this->submitProfilePhoto('avatar.png', $this->svgBytes());

        $component->assertHasErrors('informationFormData.profile');
        $this->assertFalse($user->hasMedia('profile'));
    }

    public function test_profile_photo_accepts_valid_png(): void
    {
        [$component, $user] = $this->submitProfilePhoto('avatar.png', $this->pngBytes());

        $this->assertEmpty($component->errors());
        $this->assertTrue($user->hasMedia('profile'));
    }

    public function test_profile_photo_media_collection_enforces_allowed_types(): void
    {
        $user = User::factory()->create(['password' => 'password-12345']);

        $this->expectException(FileUnacceptableForCollection::class);

        $user->addMediaFromString($this->svgBytes())
            ->usingFileName('avatar.svg')
            ->toMediaCollection('profile', 'public');
    }

    public function test_profile_photo_media_collection_accepts_valid_raster_image(): void
    {
        $user = User::factory()->create(['password' => 'password-12345']);

        $media = $user->addMediaFromString($this->pngBytes())
            ->usingFileName('avatar.png')
            ->toMediaCollection('profile', 'public');

        $this->assertSame('image/png', $media->mime_type);
        $this->assertTrue($user->fresh()->hasMedia('profile'));
    }

    /**
     * @return array{0: \Livewire\Features\SupportTesting\Testable, 1: User}
     */
    private function submitProfilePhoto(string $name, string $contents): array
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);

        $conference = Conference::factory()->create(['path' => 'upload-probe']);
        app()->setCurrentConferenceId($conference->getKey());
        Filament::setCurrentPanel(Filament::getPanel('administration'));

        $user = User::factory()->create(['password' => 'password-12345']);
        $file = $this->uploadedFile($name, $contents);
        $paths = app(FileUploadController::class)->validateAndStore([$file], 'tmp-for-tests');

        $component = Livewire::actingAs($user)->test(Profile::class);
        $component->call('_finishUpload', 'informationFormData.profile', $paths->all(), true)
            ->call('submitInformationForm');

        return [$component, $user->fresh()];
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'leconfe-probe');

        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function svgBytes(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>';
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(32, 32);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 200, 120));

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
