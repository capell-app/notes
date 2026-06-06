<x-filament-panels::page>
    @php($counts = $this->counts())
    @php($notes = $this->inboxNotes())

    <div data-capell-notes-inbox class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-notes::note.assigned_to_me') }}
            </div>
            <div class="mt-2 text-3xl font-semibold">
                {{ $counts->assigned }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-notes::note.mentions') }}
            </div>
            <div class="mt-2 text-3xl font-semibold">
                {{ $counts->mentions }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-notes::note.due_today') }}
            </div>
            <div class="mt-2 text-3xl font-semibold">
                {{ $counts->dueToday }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-notes::note.overdue') }}
            </div>
            <div class="mt-2 text-3xl font-semibold">
                {{ $counts->overdue }}
            </div>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            {{ __('capell-notes::note.recent_notes') }}
        </x-slot>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('capell-notes::note.filters.status') }}
            </span>

            <x-filament::button
                :color="$this->statusFilter === 'all' ? 'primary' : 'gray'"
                size="sm"
                wire:click="setStatusFilter('all')"
            >
                {{ __('capell-notes::note.filters.all') }}
            </x-filament::button>

            <x-filament::button
                :color="$this->statusFilter === 'open' ? 'primary' : 'gray'"
                size="sm"
                wire:click="setStatusFilter('open')"
            >
                {{ __('capell-notes::note.status.open') }}
            </x-filament::button>

            <x-filament::button
                :color="$this->statusFilter === 'resolved' ? 'primary' : 'gray'"
                size="sm"
                wire:click="setStatusFilter('resolved')"
            >
                {{ __('capell-notes::note.status.resolved') }}
            </x-filament::button>
        </div>

        @if ($notes->isEmpty())
            <div
                class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
            >
                {{ __('capell-notes::note.empty_inbox') }}
            </div>
        @else
            <div
                class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800"
            >
                @foreach ($notes as $note)
                    <article class="space-y-3 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                            >
                                {{ $this->statusLabel($note->status) }}
                            </span>

                            <span
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ __('capell-notes::note.labels.subject') }}:
                                {{ $this->subjectLabel($note->subject) }}
                            </span>
                        </div>

                        <p
                            class="text-sm leading-6 text-gray-950 dark:text-white"
                        >
                            {{ $this->excerpt($note) }}
                        </p>

                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <span>
                                {{ __('capell-notes::note.labels.author') }}:
                                {{ $this->userLabel($note->author) }}
                            </span>

                            @if ($note->assignments->isNotEmpty())
                                <span>
                                    {{ __('capell-notes::note.labels.assigned') }}:
                                    {{ $note->assignments->map(fn (NoteAssignment $assignment): string => $this->userLabel($assignment->assignee))->implode(', ') }}
                                </span>
                            @endif

                            @if ($note->mentions->isNotEmpty())
                                <span>
                                    {{ __('capell-notes::note.labels.mentioned') }}:
                                    {{ $note->mentions->map(fn (NoteMention $mention): string => $this->userLabel($mention->mentioned))->implode(', ') }}
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($note->status === NoteStatus::Resolved)
                                <x-filament::button
                                    color="gray"
                                    size="xs"
                                    wire:click="reopenNote({{ $note->id }})"
                                >
                                    {{ __('capell-notes::note.actions.reopen') }}
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    color="gray"
                                    size="xs"
                                    wire:click="resolveNote({{ $note->id }})"
                                >
                                    {{ __('capell-notes::note.actions.resolve') }}
                                </x-filament::button>
                            @endif

                            @if ($this->hasIncompleteAssignmentForCurrentUser($note))
                                <x-filament::button
                                    color="gray"
                                    size="xs"
                                    wire:click="completeAssignment({{ $note->id }})"
                                >
                                    {{ __('capell-notes::note.actions.complete_assignment') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
