<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MyActionItems extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'My Action Items';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('urgency')
                    ->label('Urgency')
                    ->getStateUsing(function (Ticket $record): string {
                        if (! $record->due_date) {
                            return '⚪ No Due Date';
                        }
                        $due  = Carbon::parse($record->due_date);
                        $days = (int) Carbon::today()->diffInDays($due, false);

                        if ($days < 0) {
                            return '🔴 Overdue ' . abs($days) . 'd';
                        }
                        if ($days === 0) {
                            return '🔴 Due Today';
                        }
                        if ($days <= 2) {
                            return '🔴 Due in ' . $days . 'd';
                        }
                        if ($days <= 7) {
                            return '🟡 Due in ' . $days . 'd';
                        }

                        return '🟢 Due in ' . $days . 'd';
                    }),

                TextColumn::make('name')
                    ->label('Ticket')
                    ->limit(55)
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

                TextColumn::make('priority.name')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (Ticket $record): string => match (strtolower($record->priority?->name ?? '')) {
                        'high', 'urgent', 'critical' => 'danger',
                        'medium', 'normal'           => 'warning',
                        'low'                        => 'gray',
                        default                      => 'primary',
                    })
                    ->placeholder('—'),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d/m/Y')
                    ->color(fn (Ticket $record): string =>
                        $record->due_date && Carbon::parse($record->due_date)->isPast()
                            ? 'danger'
                            : 'gray'
                    )
                    ->placeholder('Not set'),
            ])
            ->defaultSort('due_date', 'asc')
            ->recordUrl(fn (Ticket $record): string =>
                route('filament.admin.resources.tickets.view', $record)
            )
            ->paginated([5, 10, 25])
            ->striped()
            ->emptyStateHeading('All Clear! 🎉')
            ->emptyStateDescription('No overdue or upcoming tickets assigned to you.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        return Ticket::query()
            ->with(['project', 'status', 'priority'])
            ->whereHas('assignees', fn ($q) => $q->where('user_id', $user->id))
            ->where(fn ($q) =>
                $q->whereHas('status', fn ($s) => $s->where('is_completed', false))
                  ->orWhereNull('ticket_status_id')
            )
            ->where(fn ($q) =>
                $q->where('due_date', '<', today())                          // overdue
                  ->orWhereBetween('due_date', [today(), today()->addDays(7)]) // due this week
            );
    }
}
