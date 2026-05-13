<x-filament-widgets::widget>
    <x-filament::section heading="Projects by Status">
        <div
            x-data="{
                draggingProject: null,

                init() {
                    this.$nextTick(() => {
                        this.attachEventListeners();
                    });

                    // Re-attach after Livewire re-renders the DOM
                    Livewire.hook('morph.updated', ({el}) => {
                        if (this.$refs.board && this.$refs.board.contains(el)) {
                            this.$nextTick(() => {
                                this.attachEventListeners();
                            });
                        }
                    });

                    Livewire.hook('commit.end', () => {
                        this.$nextTick(() => {
                            this.attachEventListeners();
                        });
                    });
                },

                moveProjectToStatus(projectId, newStatus) {
                    $wire.call('updateProjectStatus', projectId, newStatus);
                },

                attachEventListeners() {
                    const cards = this.$refs.board.querySelectorAll('.project-card');
                    cards.forEach(card => {
                        card.setAttribute('draggable', true);

                        card.addEventListener('dragstart', (e) => {
                            this.draggingProject = card.getAttribute('data-project-id');
                            card.classList.add('opacity-50');
                            e.dataTransfer.effectAllowed = 'move';
                        });

                        card.addEventListener('dragend', () => {
                            card.classList.remove('opacity-50');
                            this.draggingProject = null;
                        });
                    });

                    const columns = this.$refs.board.querySelectorAll('.status-column');
                    columns.forEach(column => {
                        column.addEventListener('dragover', (e) => {
                            e.preventDefault();
                            e.dataTransfer.dropEffect = 'move';
                            column.classList.add('bg-primary-50', 'dark:bg-primary-950');
                        });

                        column.addEventListener('dragleave', () => {
                            column.classList.remove('bg-primary-50', 'dark:bg-primary-950');
                        });

                        column.addEventListener('drop', (e) => {
                            e.preventDefault();
                            column.classList.remove('bg-primary-50', 'dark:bg-primary-950');

                            if (this.draggingProject) {
                                const newStatus = column.getAttribute('data-status-id');
                                const projectId = this.draggingProject;
                                this.draggingProject = null;
                                this.moveProjectToStatus(projectId, newStatus);
                            }
                        });
                    });
                }
            }"
            x-ref="board"
            class="overflow-x-auto pb-6 px-4"
        >
            <div class="flex gap-4 pb-2 py-2">
                @foreach ($this->getProjectsByStatus() as $status => $data)
                    <div
                        class="status-column rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50 dark:bg-gray-900 flex-1 min-w-[220px]"
                        style="min-height: 300px;"
                        data-status-id="{{ $status }}"
                    >
                        {{-- Column Header --}}
                        @php
                            $headerBg = match($data['color']) {
                                'warning' => '#f59e0b',
                                'info' => '#3b82f6',
                                'success' => '#10b981',
                                'primary' => '#8b5cf6',
                                default => '#6b7280',
                            };
                        @endphp
                        <div
                            class="rounded-t-xl border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
                            style="background-color: {{ $headerBg }}; padding: 16px 24px;"
                        >
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-extrabold text-white tracking-tight" style="text-shadow: 0px 1px 3px rgba(0,0,0,0.4);">
                                    {{ $data['label'] }}
                                </h3>
                                <span class="text-sm font-bold bg-white/20 text-white px-3 py-1 rounded-full border border-white/30 backdrop-blur-sm">
                                    {{ $data['projects']->count() }}
                                </span>
                            </div>
                        </div>

                        {{-- Column Body --}}
                        <div class="p-4 flex flex-col gap-4 flex-1 overflow-y-auto">
                            @forelse ($data['projects'] as $project)
                                <div
                                    class="project-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm cursor-grab active:cursor-grabbing hover:shadow-lg transition-all border-l-4"
                                    style="border-left-color: {{ $project->color ?? '#6B7280' }};"
                                    data-project-id="{{ $project->id }}"
                                >
                                    <div class="flex items-center gap-2 mb-3">
                                        <h4 class="text-base font-bold text-gray-900 dark:text-white line-clamp-2">
                                            {{ $project->name }}
                                        </h4>
                                    </div>
                                    @if ($project->ticket_prefix)
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-mono font-bold px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-md border border-gray-200 dark:border-gray-600">
                                                {{ $project->ticket_prefix }}
                                            </span>
                                            
                                            <a
                                                href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('view', ['record' => $project->id]) }}"
                                                target="_blank"
                                                class="text-primary-600 hover:text-primary-500"
                                                title="View Project"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-32 text-gray-500 dark:text-gray-400 text-sm italic border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl bg-gray-100/50 dark:bg-gray-800/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mb-2 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span>No projects</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
