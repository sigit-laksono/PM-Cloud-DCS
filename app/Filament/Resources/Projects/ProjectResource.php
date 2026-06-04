<?php

namespace App\Filament\Resources\Projects;

use App\Enums\ProjectStatus;
use App\Filament\Actions\PromoteToManagedAction;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\EpicsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TicketsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TicketStatusesRelationManager;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->nullable()
                    ->preload(),
                Select::make('pic_user_id')
                    ->label('PIC (Person in Charge)')
                    ->relationship('pic', 'name')
                    ->searchable()
                    ->nullable()
                    ->preload()
                    ->helperText('Main responsible person for this project'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('project_status')
                    ->options(
                        collect(ProjectStatus::cases())
                            ->reject(fn ($case) => $case === ProjectStatus::Managed)
                            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                    )
                    ->default(ProjectStatus::Running->value)
                    ->required(),
                RichEditor::make('description')
                    ->columnSpanFull()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('attachments')
                    ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'video/mp4'])
                    ->fileAttachmentsVisibility('public'),
                TextInput::make('ticket_prefix')
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('color')
                    ->label('Project Color')
                    ->helperText('Choose a color for the project card and badge')
                    ->nullable(),
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                DatePicker::make('end_date')
                    ->label('End Date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),
                Toggle::make('create_default_statuses')
                    ->label('Use Default Ticket Statuses')
                    ->helperText('Create standard Backlog, To Do, In Progress, Review, and Done statuses automatically')
                    ->default(true)
                    ->dehydrated(false)
                    ->visible(fn ($livewire) => $livewire instanceof CreateProject),

                Toggle::make('is_pinned')
                    ->label('Pin Project')
                    ->helperText('Pinned projects will appear in the dashboard timeline')
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $set('pinned_date', now());
                        } else {
                            $set('pinned_date', null);
                        }
                    })
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $get) {
                        $component->state(! is_null($get('pinned_date')));
                    }),
                DateTimePicker::make('pinned_date')
                    ->label('Pinned Date')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn ($get) => $get('is_pinned'))
                    ->dehydrated(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->width('40px')
                    ->default('#6B7280'),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('pic.name')
                    ->label('PIC')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('project_status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('ticket_prefix')
                    ->searchable(),
                TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->getStateUsing(function (Project $record): string {
                        return $record->progress_percentage.'%';
                    })
                    ->badge()
                    ->color(
                        fn (Project $record): string => $record->progress_percentage >= 100 ? 'success' :
                        ($record->progress_percentage >= 75 ? 'info' :
                            ($record->progress_percentage >= 50 ? 'warning' :
                                ($record->progress_percentage >= 25 ? 'gray' : 'danger')))
                    )
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('remaining_days')
                    ->label('Remaining Days')
                    ->getStateUsing(function (Project $record): ?string {
                        if (! $record->end_date) {
                            return null;
                        }

                        return $record->remaining_days.' days';
                    })
                    ->badge()
                    ->color(
                        fn (Project $record): string => ! $record->end_date ? 'gray' :
                        ($record->remaining_days <= 0 ? 'danger' :
                            ($record->remaining_days <= 7 ? 'warning' : 'success'))
                    ),
                ToggleColumn::make('is_pinned')
                    ->label('Pinned')
                    ->updateStateUsing(function ($record, $state) {
                        // Gunakan method pin/unpin yang sudah ada di model
                        if ($state) {
                            $record->pin();
                        } else {
                            $record->unpin();
                        }

                        return $state;
                    }),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members'),
                TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label('Tickets'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_status')
                    ->options(ProjectStatus::class)
                    ->label('Project Status')
                    ->multiple(),
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->label('Customer')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                PromoteToManagedAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TicketStatusesRelationManager::class,
            MembersRelationManager::class,
            EpicsRelationManager::class,
            TicketsRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
            // Hapus baris ini: 'gantt-chart' => Pages\ProjectGanttChart::route('/gantt-chart'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->whereIn('project_status', [
                ProjectStatus::Poc,
                ProjectStatus::Running,
                ProjectStatus::Completed,
            ]);

        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (! $userIsSuperAdmin) {
            $query->whereHas('members', function (Builder $query) {
                $query->where('user_id', auth()->id());
            });
        }

        return $query;
    }
}
