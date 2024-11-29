<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Discussion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Upgrade120 extends UpgradeBase
{
	public function run(): void {
		Discussion::query()
			->whereHas('media', fn($query) => $query->where('collection_name','discussion-attachment')->where('disk', 'local'))
			->with(['media' => fn($query) => $query->where('collection_name','discussion-attachment')->where('disk', 'local')])
			->lazy()
			->each(function($discussion){
				// Move media to new private-files disk
				$discussion->media->each(fn($media) => $media->move($discussion, 'discussion-attachment', 'private-files'));
			});
	}
}
