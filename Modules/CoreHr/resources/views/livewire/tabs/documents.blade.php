<div class="space-y-4">
    @include('corehr::partials.action-feedback')

    @if($missing->isNotEmpty())
        <div class="rounded border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
            <p class="mb-1 font-medium">Missing</p>
            <ul class="list-inside list-disc text-xs">
                @foreach($missing as $type)
                    <li>{{ $type->name }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($showUpload)
        <div class="rounded bg-white p-5 shadow">
            <h3 class="mb-4 border-b pb-2 text-sm font-semibold text-gray-700">Upload a document</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Type <span class="text-red-500">*</span></label>
                    <select wire:model="documentTypeId" class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('documentTypeId') border-red-400 @enderror">
                        <option value="">— Select —</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}{{ $type->requires_expiry ? ' (needs expiry date)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('documentTypeId')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">File <span class="text-red-500">*</span></label>
                    <input wire:model="file" type="file" accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full rounded border px-3 py-1 text-sm @error('file') border-red-400 @enderror" />
                    <p class="mt-1 text-[11px] text-gray-400">PDF, JPG or PNG, up to 10 MB.</p>
                    @error('file')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Issued on</label>
                    <input wire:model="issuedOn" type="date"
                        class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('issuedOn') border-red-400 @enderror" />
                    @error('issuedOn')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Expires on</label>
                    <input wire:model="expiresOn" type="date"
                        class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('expiresOn') border-red-400 @enderror" />
                    @error('expiresOn')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Remarks</label>
                    <input wire:model="remarks" type="text" maxlength="255"
                        class="w-full rounded border px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 @error('remarks') border-red-400 @enderror" />
                    @error('remarks')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="closeUpload" class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" wire:click="upload" wire:loading.attr="disabled" wire:target="upload,file"
                    class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Upload</button>
            </div>
        </div>
    @endif

    <div class="overflow-x-auto rounded bg-white shadow">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-700">Documents</h3>
            @can('employee.document.manage')
                @if(! $showUpload)
                    <button type="button" wire:click="openUpload" class="rounded border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">Upload</button>
                @endif
            @endcan
        </div>
        <table class="w-full table-compact text-sm">
            <thead class="border-b bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Type</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">File name</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Issued</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Expires</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Uploaded</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($documents as $document)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $document->documentType?->name }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-700">{{ $document->original_name }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">{{ $document->issued_on?->format('Y-m-d') ?? '—' }}</td>
                        <td @class([
                            'px-3 py-1.5 text-xs',
                            'font-medium text-red-600' => $document->expires_on && $document->expires_on->lt($today),
                            'font-medium text-amber-600' => $document->expires_on && $document->expires_on->gte($today) && $document->expires_on->lte($soon),
                            'text-gray-600' => ! $document->expires_on || $document->expires_on->gt($soon),
                        ])>{{ $document->expires_on?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-xs text-gray-600">
                            {{ $document->uploader?->name ?? '—' }} · {{ $document->created_at?->timezone(config('app.timezone'))->format('Y-m-d') }}
                        </td>
                        <td class="px-3 py-1.5 text-center text-xs">
                            <a href="{{ route('documents.download', $document) }}" class="text-blue-600 hover:underline">Download</a>
                            @can('employee.document.manage')
                                <button type="button" wire:click="archive({{ $document->id }})"
                                    wire:confirm="Archive this document?" class="ml-3 text-red-600 hover:text-red-800">Archive</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">No documents uploaded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
