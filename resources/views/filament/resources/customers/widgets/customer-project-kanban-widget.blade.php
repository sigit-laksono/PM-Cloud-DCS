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
            class="overflow-x-auto pb-4"
        >
            <div class="inline-flex gap-4 min-w-full">
                @foreach ($this->getProjectsByStatus() as $status => $data)
                    <div
                        class="status-column rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50 dark:bg-gray-900 w-64 min-h-[250px]"
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
                            class="px-4 py-3 rounded-t-xl border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
                            style="background-color: {{ $headerBg }};"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-white text-sm" style="text-shadow: 0px 0px 1px rgba(0,0,0,0.3);">
                                    {{ $data['label'] }}
                                </span>
                                <span class="text-xs text-white/80 bg-white/20 rounded-full px-2 py-0.5">
                                    {{ $data['projects']->count() }}
                                </span>
                            </div>
                        </div>

                        {{-- Column Body --}}
                        <div class="p-2 flex-1 space-y-2">
                            @forelse ($data['projects'] as $project)
                                <div
                                    class="project-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 shadow-sm cursor-grab active:cursor-grabbing hover:shadow-md transition-shadow"
                                    data-project-id="{{ $project->id }}"
                                >
                                    <div class="flex items-center gap-2">
                                        @if ($project->color)
                                            <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $project->color }}"></span>
                                        @endif
                                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ $project->name }}
                                        </span>
                                    </div>
                                    @if ($project->ticket_prefix)
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">
                                            {{ $project->ticket_prefix }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <div class="text-xs text-gray-400 dark:text-gray-500 italic py-4 text-center border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                    No projects
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
