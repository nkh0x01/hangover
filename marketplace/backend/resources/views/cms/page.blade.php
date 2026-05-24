<x-layouts.app>
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-12">
        <h1 class="section-title gold-rule">{{ $page->title_ka }}</h1>
        <div class="mt-8 prose prose-sm max-w-none text-brand-ink/80">
            {!! nl2br(e($page->body_ka)) !!}
        </div>
    </div>
</x-layouts.app>
