@if(count($toc) > 1 || $blocks !== [])
    <div class="row g-4 mb-4">
        @if(count($toc) > 1)
            <aside class="col-lg-4">
                <div class="pp-toc card border-0 shadow-sm" style="border-radius:20px;">
                    <div class="card-body p-4">
                        <div class="pp-toc-title"><i class="bi bi-list-ul"></i> On this page</div>
                        <ol class="pp-toc-list mb-0">
                            @foreach($toc as $t)
                                <li><a href="#{{ $t['id'] }}"><span class="pp-toc-num">{{ $t['num'] }}</span>{{ $t['title'] }}</a></li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </aside>
        @endif

        <div class="{{ count($toc) > 1 ? 'col-lg-8' : 'col-12' }}">
            <div class="card border-0 shadow-sm h-100" style="border-radius:20px;">
                <div class="card-body p-4 p-md-5 pp-doc">
                    @foreach($blocks as $block)
                        @switch($block['type'])
                            @case('title')
                                <div class="pp-doc-title">{{ $block['text'] }}</div>
                                @break
                            @case('meta')
                                <div class="pp-doc-meta"><i class="bi bi-calendar3"></i> Last updated{{ $block['text'] !== '' ? ': ' . $block['text'] : '' }}</div>
                                @break
                            @case('heading')
                                <h3 class="pp-heading" id="{{ $block['id'] }}">
                                    <span class="pp-heading-num">{{ $block['num'] }}</span>
                                    <span>{{ $block['title'] }}</span>
                                </h3>
                                @isset($block['images'])
                                    @foreach($block['images'] as $image)
                                        <figure class="pp-figure">
                                            <img src="{{ asset('images/help/' . rawurlencode($image)) }}"
                                                 alt="{{ $block['title'] }}"
                                                 loading="lazy"
                                                 decoding="async">
                                            <figcaption>{{ $block['title'] }}</figcaption>
                                        </figure>
                                    @endforeach
                                @endisset
                                @break
                            @case('subheading')
                                <h4 class="pp-subheading">{{ $block['text'] }}</h4>
                                @break
                            @case('list')
                                <ul class="pp-list">
                                    @foreach($block['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                                @break
                            @default
                                <p class="pp-p">{{ $block['text'] }}</p>
                        @endswitch
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
