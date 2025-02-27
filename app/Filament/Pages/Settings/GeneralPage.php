<?php

namespace App\Filament\Pages\Settings;

use App\Rules\Cron;
use App\Settings\GeneralSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Squire\Models\Timezone;

class GeneralPage extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'General';

    protected static ?string $navigationLabel = 'General';

    protected static string $settings = GeneralSettings::class;

    public function mount(): void
    {
        parent::mount();

        abort_unless(auth()->user()->is_admin, 403);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->is_admin;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make([
                    'default' => 1,
                ])
                    ->schema([
                        Forms\Components\Section::make('Site Settings')
                            ->schema([
                                Forms\Components\TextInput::make('site_name')
                                    ->maxLength(50)
                                    ->required()
                                    ->columnSpan(['md' => 2]),
                                Forms\Components\Select::make('timezone')
                                    ->label('Display time zone')
                                    ->helperText(new HtmlString('Display time zone only changes the offset in views and <span class="underline">does not</span> effect the scheduler.'))
                                    ->options(Timezone::all()->pluck('code', 'code'))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('time_format')
                                    ->hint(new HtmlString('&#x1f517;<a href="https://www.php.net/manual/en/datetime.format.php" target="_blank" rel="nofollow">DateTime Format</a>'))
                                    ->placeholder('M j, Y G:i:s')
                                    ->maxLength(25)
                                    ->required(),
                            ])
                            ->compact()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ]),

                        Forms\Components\Section::make('Speedtest Settings')
                            ->schema([
                                Forms\Components\TextInput::make('speedtest_schedule')
                                    ->rules([new Cron()])
                                    ->helperText('Leave empty to disable scheduled tests.')
                                    ->hint(new HtmlString('&#x1f517;<a href="https://crontab.cronhub.io/" target="_blank" rel="nofollow">Cron Generator</a>'))
                                    ->nullable()
                                    ->columnSpan(1),
                                Forms\Components\Select::make('speedtest_server')
                                    ->label('Speedtest servers')
                                    ->helperText('Leave empty to let the system pick the best server.')
                                    ->maxItems(10)
                                    ->multiple()
                                    ->nullable()
                                    ->preload(false)
                                    ->searchable()
                                    ->options(function (): array {
                                        $response = Http::get(
                                            url: 'https://www.speedtest.net/api/js/servers',
                                            query: [
                                                'engine' => 'js',
                                                'https_functional' => true,
                                                'limit' => 20,
                                            ]
                                        );

                                        if ($response->failed()) {
                                            return [
                                                '' => 'There was an error retrieving Speedtest servers',
                                            ];
                                        }

                                        return $response->collect()->mapWithKeys(function (array $item, int $key) {
                                            return [$item['id'] => $item['id'].': '.$item['name'].' ('.$item['sponsor'].')'];
                                        })->toArray();
                                    })
                                    ->getSearchResultsUsing(fn (string $search): array => $this->getServerSearchOptions($search))
                                    ->getOptionLabelsUsing(fn (array $values): array => $this->getServerLabels($values))
                                    ->columnSpan('full'),
                            ])
                            ->compact()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ]),

                        Forms\Components\Section::make('Public Dashboard Settings')
                            ->schema([
                                Forms\Components\Toggle::make('public_dashboard_enabled')
                                    ->label('Enable')
                                    ->columnSpan(2),
                            ])
                            ->compact()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ]),
                    ])
                    ->columnSpan('full'),
            ]);
    }

    protected function getServerLabels(array $values): array
    {
        if (count($values) && is_null($values[0])) {
            return [];
        }

        return collect($values)->mapWithKeys(function (string $item, int $key) {
            return [$item => $item];
        })->toArray();
    }

    protected function getServerSearchOptions(string $search): array
    {
        $response = Http::get(
            url: 'https://www.speedtest.net/api/js/servers',
            query: [
                'engine' => 'js',
                'search' => $search,
                'https_functional' => true,
                'limit' => 20,
            ]
        );

        if ($response->failed()) {
            return [
                '' => 'There was an error retrieving Speedtest servers',
            ];
        }

        if (! $response->collect()->count() && is_numeric($search)) {
            return collect([
                [
                    'id' => $search,
                    'name' => $search.' (Manually entered server)',
                ],
            ])->pluck('name', 'id')->toArray();
        }

        return $response->collect()->mapWithKeys(function (array $item, int $key) {
            return [$item['id'] => $item['id'].': '.$item['name'].' ('.$item['sponsor'].')'];
        })->toArray();
    }
}
