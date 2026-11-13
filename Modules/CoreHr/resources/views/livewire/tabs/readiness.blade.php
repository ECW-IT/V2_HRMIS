<div class="rounded bg-white p-5 shadow">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3 border-b pb-3">
        <h3 class="text-sm font-semibold text-gray-700">Readiness</h3>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600" for="readiness-as-of">As of</label>
            <input id="readiness-as-of" type="date" wire:model.live="asOf"
                class="rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('asOf') border-red-400 @enderror" />
            @error('asOf')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
    </div>

    @if($issues->isEmpty())
        <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Ready</span>
    @else
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Code</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Issue</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Severity</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($issues as $issue)
                    <tr>
                        <td class="px-3 py-1.5 font-mono text-xs">{{ $issue->code }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $issue->title }}</td>
                        <td class="px-3 py-1.5">
                            <span @class([
                                'rounded px-2 py-0.5 text-xs font-medium',
                                'bg-red-100 text-red-700' => $issue->severity === \Modules\CoreHr\Readiness\Severity::BLOCKER,
                                'bg-yellow-100 text-yellow-700' => $issue->severity !== \Modules\CoreHr\Readiness\Severity::BLOCKER,
                            ])>{{ $issue->severity->value }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
