<?php

namespace App\Managers;

use App\Models\Metric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MetricManager
{
	public function log(string $eventName, Model | null $model = null)
	{
		app()->terminating(function () use ($eventName, $model) {
			$request = request();

			$data =  [
				'time' => now()->toDateTimeString(),
				'event' => $eventName,
				'url' => $request->url(),
				'userAgent' => $request->userAgent(),
				'ip' => hash('sha256', $request->ip()),
				'model_type' => $model?->getMorphClass(),
				'model_id' => $model?->getKey(),
				'conference' => app()->getCurrentConference()?->getKey(),
				'scheduledConference' => app()->getCurrentScheduledConference()?->getKey(),
				'version' => app()->getCodeVersion(),
			];

			Storage::disk('private')->append($this->getLogFile(), json_encode($data));
		});
	}

	protected function getLogFile(): string
	{
		return $this->getMetricFolder('logs' . DIRECTORY_SEPARATOR . now()->format('Y_m_d') . '.log');
	}

	protected function getMetricFolder(string $path): string
	{
		return 'metrics' . DIRECTORY_SEPARATOR . $path;
	}

	public function moveLogToQueues()
	{
		$files = $this->getDisk()->files($this->getMetricFolder('logs'));
		foreach ($files as $file) {

			if ($file === $this->getLogFile()) {
				// Skip the current log file
				continue;
			}

			$this->getDisk()->copy($file, $this->getMetricFolder('queues' . DIRECTORY_SEPARATOR . basename($file)));
			$this->getDisk()->delete($file);
		}
	}

	public function getFiles($path): array
	{
		return $this->getDisk()->files($path);
	}

	public function getDisk(): \Illuminate\Contracts\Filesystem\Filesystem
	{
		return Storage::disk('private');
	}

	public function processQueues()
	{
		$files = $this->getFiles($this->getMetricFolder('queues'));

		foreach ($files as $file) {
			$this->processQueue($file);
		}
	}

	public function processQueue(string $file)
	{
		$fullPath = $this->getDisk()->path($file);

		$source = basename($file);

		Metric::purgeBySource($source);

		$uniqueMetrics = collect();

		$newMetrics = collect();

		// Read line by line
		$handle = fopen($fullPath, 'r');
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				if (json_validate($line) === false) {
					continue;
				}
				$data = collect(json_decode($line, true));

				$date = Carbon::parse($data->get('time'));

				$data->forget('time');

				$uniqueMetric = md5($data->toJson());

				if ($uniqueMetrics->contains($uniqueMetric)) {
					continue;
				}

				$uniqueMetrics->push($uniqueMetric);

				$metric = $newMetrics->first(function (Metric $metric) use ($data) {
					if (
						$metric->event === $data->get('event')
						&& $metric->model_type === $data->get('model_type')
						&& $metric->model_id === $data->get('model_id')
					) {
						return true;
					}
				}, new Metric([
					'source' => basename($file),
					'event' => $data->get('event'),
					'model_type' => $data->get('model_type'),
					'model_id' => $data->get('model_id'),
					'conference_id' => $data->get('conference'),
					'scheduled_conference_id' => $data->get('scheduledConference'),
					'log_at' => $date,
				]));

				$metric->metric += 1;

				$newMetrics->push($metric);
			}
			fclose($handle);
		}

		// Batch Save with chunk
		$newMetrics->chunk(100)->each(function ($metrics) {
			Metric::insert($metrics->toArray());
		});

		$this->moveFileToArchive($file);
	}

	public function moveFileToArchive(string $file)
	{
		$this->getDisk()->copy($file, $this->getMetricFolder('archives' . DIRECTORY_SEPARATOR . basename($file)));
		$this->getDisk()->delete($file);
	}
}
