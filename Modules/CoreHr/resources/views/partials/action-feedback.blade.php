@if($errorMessage !== '')
    <div class="mb-3 rounded bg-red-100 px-3 py-2 text-sm text-red-700">{{ $errorMessage }}</div>
@endif
@if($message !== '')
    <div class="mb-3 rounded bg-green-100 px-3 py-2 text-sm text-green-700">{{ $message }}</div>
@endif
