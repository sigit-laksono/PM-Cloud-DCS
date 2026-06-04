<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ProjectHealthTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Project Health Overview';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Project')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Project $record): string => $record->project_status?->getLabel() ?? ''),

                TextColumn::make('pic.name')
                    ->label('PIC')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn (Project $record): string => $record->progress_percentage . '%')
                    ->badge()
                    ->color(fn (Project $record): string =>
                        $record->progress_percentage >= 75 ? 'success' :
                        ($record->progress_percentage >= 40 ? 'warning' : 'danger')
                    ),

                TextColumn::make('open_tickets_count')
                    ->label('Open')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('overdue_tickets_count')
                    ->label('Overdue')
                    ->badge()
                    ->color(fn (Project $record): string =>
                        ($record->overdue_tickets_count ?? 0) > 0 ? 'danger' : 'success'
                    ),

                TextColumn::make('remaining_days_label')
                    ->label('Days Left')
                    ->getStateUsing(function (Project $record): string {
                        if (! $record->end_date) {
                            return '—';
                        }
                        $days = $record->remaining_days;

                        return $days <= 0 ? 'Overdue' : $days . ' days';
                    })
                    ->badge()
                    ->color(fn (Project $record): string =>
                        ! $record->end_date ? 'gray' :
                        ($record->remaining_days <= 0 ? 'danger' :
                        ($record->remaining_days <= 7 ? 'warning' : 'success'))
                    ),

                TextColumn::make('health')
                    ->label('Health')
                    ->getStateUsing(function (Project $record): string {
                        $overdue = $record->overdue_tickets_count ?? 0;
                        $days    = $record->end_date ? $record->remaining_days : null;

                        if ($overdue > 0 && $days !== null && $days <= 3) {
                            return '🔴 Critical';
                        }
                        if ($overdue > 0 || ($days !== null && $days <= 7)) {
                            return '🟡 At Risk';
                        }

                        return '🟢 On Track';
                    }),
            ])
            ->defaultSort('overdue_tickets_count', 'desc')
            ->recordUrl(function (Project $record): string {
                return $record->project_status === ProjectStatus::Managed
                    ? ManagedServiceResource::getUrl('view', ['record' => $record])
                    : ProjectResource::getUrl('view', ['record' => $record]);
            })
            ->paginated([10, 25, 50])
            ->striped()
            ->emptyStateHeading('No Projects Found')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    protected function getTableQuery(): Builder
    {
        $user        = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $query = Project::query()
            ->withCount([
                'tickets as open_tickets_count' => fn ($q) => $q->whereHas(
                    'status', fn ($s) => $s->where('is_completed', false)
                ),
                'tickets as overdue_tickets_count' => fn ($q) => $q
                    ->whereHas('status', fn ($s) => $s->where('is_completed', false))
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', today()),
            ])
            ->with(['pic']);

        if (! $isSuperAdmin) {
            $query->whereHas('members', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query;
    }
}
