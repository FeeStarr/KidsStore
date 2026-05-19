<li class="list-group-item d-flex justify-content-between align-items-center" style="padding-left: {{ 12 + $depth * 24 }}px;">
    <div>
        @if($depth > 0)<i class="bi bi-arrow-return-right text-muted me-1"></i>@endif
        <strong>{{ $cat->name }}</strong>
        <small class="text-muted ms-2">/{{ $cat->slug }}</small>
        @unless($cat->is_active)<span class="badge text-bg-secondary ms-2">inactive</span>@endunless
    </div>
    <div class="btn-group btn-group-sm">
        <a href="{{ route('admin.categories.edit', $cat) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
        <form action="{{ route('admin.categories.destroy', $cat) }}" method="post" class="d-inline"
              data-confirm="This category will be permanently deleted." data-confirm-title="Delete Category?"
              data-confirm-yes="Yes, delete">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</li>
@foreach($cat->children as $child)
    @include('admin.categories._row', ['cat' => $child, 'depth' => $depth + 1])
@endforeach
