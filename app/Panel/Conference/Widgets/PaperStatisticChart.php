<?php

namespace App\Panel\Conference\Widgets;

use App\Models\Metric;
use App\Models\Proceeding;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionGalley;
use App\Providers\PanelProvider;
use Coderflex\Laravisit\Models\Visit;
use Filament\Forms\Components\DatePicker;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Facades\Blade;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

class PaperStatisticChart extends ApexChartWidget
{
    protected static ?string $pollingInterval = null;
    /**
     * Chart Id
     *
     * @var string
     */
    protected static ?string $chartId = 'article-statistic-chart';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = '12';

    public $statistic = [];

    #[On('statistic-updated')]
    public function updateStatistic($data)
    {
        $this->statistic = $data;
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $dateStart = Carbon::parse(data_get($this->statistic, 'date_start'));
        $dateEnd = Carbon::parse(data_get($this->statistic, 'date_end'));
        $range = $dateStart->diffInDays($dateEnd);
        $proceedingIds = data_get($this->statistic, 'proceeding_ids') ?: Proceeding::pluck('id')->toArray();


        $abstractViewMetric = Trend::query(
            Metric::query()
                ->where('model_type', Submission::class)
                ->where('event', 'abstract_view')
                ->whereIn(
                    'model_id',
                    fn($query) => $query->select('id')->from('submissions')->whereIn('proceeding_id', $proceedingIds)
                )
        )
            ->between(
                start: $dateStart,
                end: $dateEnd,
            )
            ->dateColumn('log_at');

        $abstractViewMetric = match (true) {
            $range > 89 => $abstractViewMetric->perMonth()->sum('metric'),
            default => $abstractViewMetric->perDay()->sum('metric'),
        };

        $galleyViewMetric = Trend::query(
            Metric::query()
                ->where('model_type', SubmissionGalley::class)
                ->where('event', 'galley_view')
                ->whereIn(
                    'model_id',
                    fn($query) => $query->select('id')->from('submission_galleys')->whereIn('submission_id', fn($query) => $query->select('id')->from('submissions')->whereIn('proceeding_id', $proceedingIds))
                )
        )
            ->between(
                start: $dateStart,
                end: $dateEnd,
            )
            ->dateColumn('log_at');

        $galleyViewMetric = match (true) {
            $range > 89 => $galleyViewMetric->perMonth()->sum('metric'),
            default => $galleyViewMetric->perDay()->sum('metric'),
        };

        // dd($galleyViewMetric)

        return [
            'stroke' => [
                'curve' => 'smooth',
                'width' => 3,
            ],
            // 'markers' => [
            //     'size' => 0.3,
            // ],
            'chart' => [
                'type' => 'line',
                'height' => 400,
                'zoom' => [
                    'enabled' => false,
                ],
                'toolbar' => [
                    'tools' => [
                        'download' => false,
                    ]
                ]
            ],
            'series' => [
                [
                    'name' => 'Abstract View',
                    'data' => $abstractViewMetric->map(fn(TrendValue $value) => $value->aggregate),
                ],
                [
                    'name' => 'Galley View',
                    'data' => $galleyViewMetric->map(fn(TrendValue $value) => $value->aggregate),
                ],
            ],
            'xaxis' => [
                'type' => 'datetime',
                // 'categories' => $abstractViewMetric->map(fn(TrendValue $value) => Carbon::parse($value->date)),
                'categories' => $abstractViewMetric->map(fn(TrendValue $value) => match (true) {
                    $range > 89 => Carbon::parse($value->date)->format('M Y'),
                    default => Carbon::parse($value->date)->format('d M'),
                }),
                'labels' => [
                    // 'show' => false,
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 600,
                    ],
                    // 'hideOverlappingLabels' => true,
                    // 'trim' => true,
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'colors' => ['#3D3BF3', '#D91656'],
            'legend' => [
                'position' => 'top',
            ],
        ];
    }
}
