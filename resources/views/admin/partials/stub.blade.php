@extends('admin.layout')

@section('title', $title ?? 'Coming soon')
@section('subtitle', $subtitle ?? '')

@section('content')
<div class="card p-10 text-center">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-50 text-brand-600 mb-4">
        {!! $icon ?? '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>' !!}
    </div>
    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ $heading ?? 'მალე გამოჩნდება' }}</h3>
    <p class="text-sm text-slate-600 max-w-md mx-auto mb-6">{{ $body ?? 'ეს გვერდი მზადდება. ცოტა ხანში გამოჩნდება სრული ფუნქციონალი.' }}</p>
    @if(isset($links))
        <div class="flex gap-2 justify-center flex-wrap">
            @foreach($links as $href => $label)
                <a href="{{ $href }}" class="btn btn-secondary">{{ $label }}</a>
            @endforeach
        </div>
    @endif
</div>
@endsection
