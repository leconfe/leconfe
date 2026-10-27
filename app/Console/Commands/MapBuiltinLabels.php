<?php

namespace App\Console\Commands;

use App\Models\AuthorRole;
use App\Models\SubmissionFileType;
use App\Models\Track;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MapBuiltinLabels extends Command
{
    protected $signature = 'translations:map-builtins
        {--conference= : Conference ID for contributor roles}
        {--scheduled-conference= : Scheduled conference ID for file types and tracks}
        {--file-type=* : Explicit record ID:code mapping}
        {--track=* : Explicit record ID:code mapping}
        {--author-role=* : Explicit record ID:code mapping}
        {--dry-run : Validate and preview without writing}';

    protected $description = 'Map explicitly selected legacy records to built-in translation codes';

    public function handle(): int
    {
        try {
            return DB::transaction(function () {
                $records = [];
                foreach ([
                    'file-type' => [SubmissionFileType::class, 'scheduled_conference_id', 'scheduled-conference', 'name'],
                    'track' => [Track::class, 'scheduled_conference_id', 'scheduled-conference', 'title'],
                    'author-role' => [AuthorRole::class, 'conference_id', 'conference', 'name'],
                ] as $option => [$model, $scope, $scopeOption, $nameAttribute]) {
                    $scopeId = $this->option($scopeOption);
                    foreach ($this->option($option) as $mapping) {
                        if (! ctype_digit((string) $scopeId) || (int) $scopeId < 1
                            || ! preg_match('/^([1-9][0-9]*):([a-z_]+)$/D', $mapping, $match)) {
                            throw new InvalidArgumentException("Use --{$scopeOption}=ID and --{$option}=ID:code.");
                        }

                        [$input, $id, $code] = $match;
                        $defaultName = $model::builtinNames()[$code] ?? null;
                        $record = $model::withoutGlobalScopes()->where($scope, $scopeId)->lockForUpdate()->find($id);
                        if (! $record || $defaultName === null || $record->getAttribute($nameAttribute) !== $defaultName) {
                            throw new InvalidArgumentException("Invalid mapping {$option} {$input}: check scope, code and original name.");
                        }
                        if ($record->builtin_code !== null && $record->builtin_code !== $code) {
                            throw new InvalidArgumentException("{$option} {$id} already has a different code.");
                        }
                        if ($model::withoutGlobalScopes()->where($scope, $scopeId)->where('builtin_code', $code)
                            ->whereKeyNot($id)->exists()) {
                            throw new InvalidArgumentException("{$code} is already mapped in this scope.");
                        }
                        $identity = $model.':'.$scopeId.':'.$code;
                        if (isset($records[$identity]) && $records[$identity]->getKey() !== $record->getKey()) {
                            throw new InvalidArgumentException("Duplicate mapping for {$code}.");
                        }
                        $records[$identity] = $record->forceFill(['builtin_code' => $code]);
                    }
                }

                if ($records === []) {
                    throw new InvalidArgumentException('Specify at least one explicit record mapping.');
                }
                foreach ($records as $record) {
                    $this->line("{$record->getTable()} {$record->getKey()}: {$record->builtin_code}");
                    if (! $this->option('dry-run')) {
                        $record->save();
                    }
                }

                return self::SUCCESS;
            });
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
