<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManagedServices;

use App\Enums\ProjectStatus;
use App\Filament\Actions\DemoteFromManagedAction;
use App\Filament\Resources\ManagedServices\Pages\CreateManagedService;
use App\Filament\Resources\Projects\RelationManagers\EpicsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TicketsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TicketStatusesRelationManager;
use App\Filament\Resources\ManagedServices\Pages\EditManagedService;
use App\Filament\Resources\ManagedServices\Pages\ListManagedServices;
use App\Filament\Resources\ManagedServices\Pages\ViewManagedService;
use App\Models\Project;
use App\Policies\ManagedServicePolicy;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManagedServiceResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $slug = 'managed-services';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Managed Services';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Services';

    protected static ?string $modelLabel = 'Managed Service';

    protected static ?string $pluralModelLabel = 'Managed Services';

    /**
     * Override Filament's authorization resolution so that this resource
     * uses ManagedServicePolicy instead of the auto-discovered ProjectPolicy.
     *
     * The Project model is shared with ProjectResource, which relies on
     * ProjectPolicy. Registering a policy globally (Gate::policy) would
     * conflict, so we route authorization through ManagedServicePolicy
     * only here.
     */
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (static::shouldSkipAuthorization()) {
            return Response::allow();
        }

        $user = auth()->user();
        $policy = app(ManagedServicePolicy::class);

        if (! method_exists($policy, $action)) {
            // Default-allow for unknown actions to mirror Filament's lenient
            // behaviour when no policy method exists.
            return Response::allow();
        }

        $arguments = [$user];
        if ($record !== null) {
            $arguments[] = $record;
        }

        $result = $policy->{$action}(...$arguments);

        if ($result instanceof Response) {
            return $result;
        }

        return $result === true ? Response::allow() : Response::deny();
    }

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
                    ->helperText('Main responsible person for this managed service'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Hidden::make('project_status')
                    ->default(ProjectStatus::Managed->value),
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
                    ->visible(fn ($livewire) => $livewire instanceof CreateManagedService),

                Toggle::make('is_pinned')
                    ->label('Pin Project')
                    ->helperText('Pinned projects will appear in the dashboard timeline')
                    ->live()
                    ->afterStateUpdated(function ($state, $set): void {
                        if ($state) {
                            $set('pinned_date', now());
                        } else {
                            $set('pinned_date', null);
                        }
                    })
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $get): void {
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
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('members')
                ->withCount(['tickets as open_tickets_count' => fn ($q) => $q->whereHas('status', fn ($s) => $s->where('is_completed', false))])
            )
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pic.name')
                    ->label('PIC')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ticket_prefix')
                    ->label('Ticket Prefix')
                    ->searchable(),
                TextColumn::make('members_count')
                    ->label('Members')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('open_tickets_count')
                    ->label('Open Tickets')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('project_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ProjectStatus
                        ? ($state->getLabel() ?? 'Managed Services')
                        : (ProjectStatus::Managed->getLabel() ?? 'Managed Services'))
                    ->color(fn ($state): string|array|null => $state instanceof ProjectStatus
                        ? $state->getColor()
                        : ProjectStatus::Managed->getColor()),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->label('Customer')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('manage_managed_service') ?? false),
                DemoteFromManagedAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('project_status', ProjectStatus::Managed);

        $user = auth()->user();
        if ($user && method_exists($user, 'hasRole') && ! $user->hasRole(['super_admin', 'admin'])) {
            $query->whereHas('members', fn (Builder $q) => $q->where('user_id', $user->id));
        }

        return $query;
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
            'index' => ListManagedServices::route('/'),
            'create' => CreateManagedService::route('/create'),
            'edit' => EditManagedService::route('/{record}/edit'),
            'view' => ViewManagedService::route('/{record}'),
        ];
    }
}
