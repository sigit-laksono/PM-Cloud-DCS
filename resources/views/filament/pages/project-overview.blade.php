<x-filament-panels::page>
    {{-- Header Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Projects</div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalProjects }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success-100 dark:bg-success-900/30 text-success-600 dark:text-success-300">
                    <x-filament::icon icon="heroicon-o-play-circle" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Running</div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalRunning }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-info-100 dark:bg-info-900/30 text-info-600 dark:text-info-300">
                    <x-filament::icon icon="heroicon-o-beaker" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">PoC</div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalPoc }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-check-badge" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Completed</div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalCompleted }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-warning-100 dark:bg-warning-900/30 text-warning-600 dark:text-warning-300">
                    <x-filament::icon icon="heroicon-o-ticket" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Open Tickets</div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalOpenTickets }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-info-100 dark:bg-info-900/30 text-info-600 dark:text-info-300">
                    <x-filament::icon icon="heroicon-o-building-office-2" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Customers</div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totalCustomers }}</div>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Per-customer cards --}}
    @if($customers->isEmpty())
        <x-filament::section>
            <div class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-inbox" class="w-12 h-12 mb-3 text-gray-400 dark:text-gray-500" />
                <h3 class="text-base font-medium text-gray-900 dark:text-white mb-1">No Projects Yet</h3>
                <p class="text-sm">There are no customers with active projects to display.</p>
            </div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($customers as $customer)
                <button
                    type="button"
                    wire:click="navigateToCustomer({{ $customer['customer_id'] }})"
                    wire:key="pm-overview-card-{{ $customer['customer_id'] }}"
                    class="text-left p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:shadow-md hover:border-primary-400 dark:hover:border-primary-500 transition-all"
                >
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="font-semibold text-base text-gray-900 dark:text-white truncate pr-2">
                            {{ $customer['customer_name'] }}
                        </h3>
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 shrink-0">
                            {{ $customer['project_count'] }} {{ Str::plural('project', $customer['project_count']) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Running</div>
                            <div class="text-lg font-semibold {{ $customer['running_count'] > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-400' }}">
                                {{ $customer['running_count'] }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Open Tickets</div>
                            <div class="text-lg font-semibold {{ $customer['open_tickets'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}">
                                {{ $customer['open_tickets'] }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Members</div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $customer['unique_members'] }}
                            </div>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
