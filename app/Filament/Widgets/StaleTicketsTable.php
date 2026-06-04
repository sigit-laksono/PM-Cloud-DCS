<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Ticket;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class StaleTicketsTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Stuck Tickets — No Movement in 5+ Days';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 1];

    protected static ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Ticket')
                    ->limit(45)
                    ->searchable()
                    ->description(fn (Ticket $record): string => $record->project->name ?? ''),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Ticket $record): string => match (strtolower($record->status?->name ?? '')) {
                        'to do', 'backlog'      => 'gray',
                        'in progress', 'doing'  => 'warning',
                        'review', 'testing'     => 'info',
                        default                 => 'primary',
                    }),

                TextColumn::make('assignees_display')
                    ->label('Assignee')
                    ->getStateUsing(fn (Ticket $record): string =>
                        $record->assignees->isEmpty()
                            ? '—'
                            : $record->assignees->pluck('name')->implode(', ')
                    ),

                TextColumn::make('updated_at')
                    ->label('Last Update')
                    ->since()
                    ->color('danger'),
            ])
            ->defaultSort('updated_at', 'asc')
            ->recordUrl(fn (Ticket $record): string =>
                route('filament.admin.resources.tickets.view', $record)
            )
            ->paginated([5, 10, 25])
            ->striped()
            ->emptyStateHeading('No Stuck Tickets 🎉')
            ->emptyStateDescription('All open tickets have had recent activity.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function getTableQuery(): Builder
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $projectIds = $isSuperAdmin
            ? Project::pluck('id')
            : $user->projects()->pluck('projects.id');

        return Ticket::query()
            ->with(['project', 'status', 'assignees'])
            ->whereIn('project_id', $projectIds)
            ->where(fn ($q) =>
                $q->whereHas('status', fn ($s) => $s->where('is_completed', false))
                  ->orWhereNull('ticket_status_id')
            )
            ->where('updated_at', '<', now()->subDays(5));
    }
}
