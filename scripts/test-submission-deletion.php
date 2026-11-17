<?php

declare(strict_types=1);

// Standalone regression check: SQLite in memory and a unique temporary media disk only.
use App\Application;
use App\Models\Media;
use App\Models\Presentation;
use App\Models\PresentationComment;
use App\Models\PresentationLike;
use App\Models\PresentationView;
use App\Models\Submission;
use GeneaLabs\LaravelModelCaching\Providers\Service as ModelCachingServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use Plank\Metable\MetableServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$mediaRoot = sys_get_temp_dir().'/leconfe-submission-deletion-'.bin2hex(random_bytes(8));
$app = new Application($root);
$app->instance('config', new Repository([
    'app' => ['env' => 'testing', 'key' => str_repeat('t', 32)],
    'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
    'laravel-model-caching' => ['enabled' => true],
    'media-library' => array_replace(
        require $root.'/vendor/spatie/laravel-medialibrary/config/media-library.php',
        require $root.'/config/media-library.php',
    ),
    'metable' => require $root.'/config/metable.php',
    'filesystems' => [
        'default' => 'deletion-tests',
        'disks' => ['deletion-tests' => ['driver' => 'local', 'root' => $mediaRoot]],
    ],
]));
Facade::setFacadeApplication($app);
$app->instance('events', new Dispatcher($app));
$app->register(CacheServiceProvider::class);
$app->register(ModelCachingServiceProvider::class);
$app->register(FilesystemServiceProvider::class);
$app->register(MetableServiceProvider::class);

$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
$capsule->setEventDispatcher($app['events']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
$app->instance('db.schema', $capsule->schema());
Media::observe(new MediaObserver);
$db = $capsule->getConnection();
$schema = $capsule->schema();

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function presentationFixture(int $submissionId): array
{
    $db = Capsule::connection();
    $presentationId = $db->table('presentations')->insertGetId([
        'submission_id' => $submissionId, 'scheduled_conference_id' => 1, 'type' => 1,
    ]);
    $parentId = null;
    $commentIds = [];
    for ($level = 0; $level < 3; $level++) {
        $parentId = $db->table('presentation_comments')->insertGetId([
            'presentation_id' => $presentationId, 'user_id' => 1, 'parent_id' => $parentId,
        ]);
        $commentIds[] = $parentId;
        $db->table('meta')->insert([
            'metable_type' => PresentationComment::class, 'metable_id' => $parentId,
            'key' => 'content', 'type' => 'string', 'value' => 'Test comment',
        ]);
    }
    $db->table('meta')->insert([
        'metable_type' => Presentation::class, 'metable_id' => $presentationId,
        'key' => 'title', 'type' => 'string', 'value' => 'Test presentation',
    ]);
    foreach (['presentation_views', 'presentation_likes'] as $table) {
        $db->table($table)->insert(['presentation_id' => $presentationId, 'user_id' => 1]);
    }
    $paths = [];
    foreach (['pdf', 'thumbnail'] as $collection) {
        $mediaId = $db->table('media')->insertGetId([
            'model_type' => Presentation::class, 'model_id' => $presentationId,
            'collection_name' => $collection, 'name' => 'test', 'file_name' => 'test.pdf',
            'disk' => 'deletion-tests', 'conversions_disk' => 'deletion-tests', 'size' => 4,
            'manipulations' => '[]', 'custom_properties' => '[]',
            'generated_conversions' => '[]', 'responsive_images' => '[]',
        ]);
        $media = Media::findOrFail($mediaId);
        $path = PathGeneratorFactory::create($media)->getPath($media).'test.pdf';
        $paths[] = $path;
        Storage::disk('deletion-tests')->put($path, 'test');
    }
    app('cache')->flush();

    return ['id' => $presentationId, 'comment_ids' => $commentIds, 'paths' => $paths];
}

function checkPresentation(array $fixture, bool $exists): void
{
    $db = Capsule::connection();
    foreach (['presentations' => 'id', 'presentation_comments' => 'presentation_id',
        'presentation_views' => 'presentation_id', 'presentation_likes' => 'presentation_id'] as $table => $column) {
        check($db->table($table)->where($column, $fixture['id'])->exists() === $exists, $table.' cleanup mismatch');
    }
    check($db->table('media')->where('model_type', Presentation::class)
        ->where('model_id', $fixture['id'])->exists() === $exists, 'Media records cleanup mismatch');
    check($db->table('meta')->where('metable_type', Presentation::class)
        ->where('metable_id', $fixture['id'])->exists() === $exists, 'Presentation metadata cleanup mismatch');
    foreach ($fixture['comment_ids'] as $commentId) {
        check($db->table('meta')->where('metable_type', PresentationComment::class)
            ->where('metable_id', $commentId)->exists() === $exists, 'Reply metadata cleanup mismatch');
    }
    foreach ($fixture['paths'] as $path) {
        check(Storage::disk('deletion-tests')->exists($path) === $exists, 'Media file cleanup mismatch');
    }
}

try {
    // Only the presentation graph uses production migrations; unrelated relations are empty fixtures.
    foreach (['users', 'scheduled_conferences', 'submissions'] as $table) {
        $schema->create($table, function (Blueprint $table) {
            $table->id();
        });
    }
    foreach (['discussion_topics', 'submission_files', 'authors', 'submission_has_participants', 'reviews'] as $table) {
        $schema->create($table, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->integer('order_column')->nullable();
        });
    }
    $schema->table('submission_files', function (Blueprint $table) {
        $table->unsignedBigInteger('media_id')->nullable();
    });
    $schema->create('payments', function (Blueprint $table) {
        $table->id();
        $table->morphs('model');
    });
    foreach (['tags', 'topics'] as $table) {
        $schema->create($table, function (Blueprint $table) {
            $table->id();
            $table->integer('order_column')->nullable();
        });
    }
    $schema->create('taggables', function (Blueprint $table) {
        $table->unsignedBigInteger('tag_id');
        $table->morphs('taggable');
    });
    $schema->create('topicables', function (Blueprint $table) {
        $table->unsignedBigInteger('topic_id');
        $table->morphs('topicable');
    });
    require $root.'/database/migrations/2017_01_01_000000_create_meta_table.php';
    (new CreateMetaTable)->up();
    foreach ([
        '2023_04_17_060431_create_media_table.php',
        '2026_01_27_071544_create_presentations_table.php',
        '2026_02_03_072416_create_presentation_comments_table.php',
        '2026_02_10_090000_create_presentation_views_table.php',
        '2026_02_10_090100_create_presentation_likes_table.php',
    ] as $migration) {
        (require $root.'/database/migrations/'.$migration)->up();
    }
    $db->table('users')->insert(['id' => 1]);
    $db->table('scheduled_conferences')->insert(['id' => 1]);
    check((int) $db->selectOne('PRAGMA foreign_keys')->foreign_keys === 1, 'Foreign keys must be enabled');

    foreach ([true, false] as $cacheEnabled) {
        $app['config']->set('laravel-model-caching.enabled', $cacheEnabled);
        $app['cache']->flush();
        foreach ([1, 2, 3] as $id) {
            $db->table('submissions')->insert(['id' => $id]);
        }
        $first = presentationFixture(1);
        $second = presentationFixture(1);
        $unrelated = presentationFixture(2);

        // Prime cached child queries and give the parent a stale, empty loaded relation.
        foreach ([PresentationLike::class, PresentationView::class, PresentationComment::class] as $model) {
            $model::where('presentation_id', $first['id'])->get();
        }
        $submission = Submission::findOrFail(1);
        $submission->setRelation('presentations', (new Presentation)->newCollection());
        check($submission->delete() === true, 'Deleting a submission must succeed');
        check(! $db->table('submissions')->where('id', 1)->exists(), 'Submission must be deleted');
        checkPresentation($first, false);
        checkPresentation($second, false);
        checkPresentation($unrelated, true);
        foreach ([PresentationLike::class, PresentationView::class, PresentationComment::class] as $model) {
            check($model::where('presentation_id', $first['id'])->get()->isEmpty(), 'Child query cache must be invalidated');
        }
        echo '[PASS] Submission with presentations, nested comments, metadata and files (cache '.($cacheEnabled ? 'on' : 'off').")\n";

        $sibling = presentationFixture(2);
        check(Presentation::findOrFail($unrelated['id'])->delete() === true, 'Direct presentation deletion must succeed');
        checkPresentation($unrelated, false);
        checkPresentation($sibling, true);
        check($db->table('submissions')->where('id', 2)->exists(), 'Direct presentation deletion must preserve its submission');
        echo "[PASS] Direct presentation deletion preserves its submission and sibling presentation\n";

        check(Submission::findOrFail(3)->delete() === true, 'Deleting a submission without presentations must succeed');
        echo "[PASS] Submission without presentations\n";
        Submission::findOrFail(2)->delete();
        check($db->table('media')->count() === 0, 'No orphaned media records');
        check($db->table('meta')->count() === 0, 'No orphaned presentation/comment metadata');
    }
    echo "All 6 deletion scenarios passed.\n";
} finally {
    // This path was generated above and is never taken from configuration or user input.
    if (is_dir($mediaRoot)) {
        (new Filesystem)->deleteDirectory($mediaRoot);
    }
}
