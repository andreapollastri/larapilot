{{--
    A PDF read in the page: one page or two side by side, zoom, page jump,
    full screen. PDF.js draws it; where the library cannot be loaded the
    reader steps aside for the link that opens the file in its own tab.

    $src       address the PDF is read from
    $download  address that saves it
    $name      file name, for the labels
--}}
@php
    $pdfjs = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174';
@endphp

@push('styles')
<style>
    .pdf-reader { display: flex; flex-direction: column; background: var(--surface); }

    .pdf-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px 10px;
        flex-wrap: wrap;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border);
        background: var(--surface-2);
    }

    .pdf-group { display: inline-flex; align-items: center; gap: 2px; }
    .pdf-divider { width: 1px; height: 20px; background: var(--border-strong); }

    .pdf-btn {
        display: inline-grid;
        place-items: center;
        min-width: 34px;
        height: 34px;
        padding: 0 8px;
        border: 1px solid transparent;
        border-radius: var(--radius-xs);
        background: transparent;
        color: var(--text-2);
        font: inherit;
        font-size: 0.8rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        text-decoration: none;
        cursor: pointer;
    }

    .pdf-btn .icon { width: 17px; height: 17px; }
    .pdf-btn:hover { background: var(--surface-3); color: var(--text); text-decoration: none; }
    .pdf-btn.is-active { border-color: color-mix(in srgb, var(--accent) 45%, var(--border)); background: var(--accent-soft); color: var(--accent-strong); }
    .pdf-btn:disabled { opacity: 0.4; cursor: default; background: transparent; color: var(--muted); }
    .pdf-btn.pdf-zoom-label { min-width: 58px; }

    .pdf-page-info {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0 4px;
        color: var(--muted);
        font-size: 0.82rem;
        font-variant-numeric: tabular-nums;
    }

    .pdf-page-input {
        width: 3.2em;
        height: 30px;
        padding: 0 4px;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-xs);
        background: var(--surface);
        color: var(--text);
        font: inherit;
        font-size: 0.82rem;
        font-weight: 600;
        text-align: center;
    }

    .pdf-page-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
    }

    .pdf-state { padding: 56px 20px; text-align: center; color: var(--muted); }
    .pdf-state p { margin: 0 0 14px; }
    .pdf-state[hidden] { display: none; }

    .pdf-viewer {
        max-height: clamp(420px, 82vh, 1200px);
        overflow: auto;
        background: var(--surface-3);
        scrollbar-width: thin;
        overscroll-behavior: contain;
    }

    .pdf-viewer:focus-visible { outline-offset: -2px; border-radius: 0; }
    .pdf-viewer[hidden] { display: none; }
    .pdf-viewer.is-pannable { cursor: grab; }
    .pdf-viewer.is-panning { cursor: grabbing; user-select: none; }

    .pdf-pages {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        gap: 16px;
        width: max-content;
        min-width: 100%;
        padding: 18px;
    }

    .pdf-page {
        flex: none;
        background: #fff;
        box-shadow: 0 1px 3px rgba(23, 33, 43, 0.18), 0 8px 24px rgba(23, 33, 43, 0.1);
    }

    .pdf-page[hidden], .pdf-spacer[hidden] { display: none; }
    .pdf-page canvas { display: block; }
    .pdf-spacer { flex: none; }

    /* full screen: the reader leaves the card and takes the window */
    .pdf-reader.is-fullscreen {
        position: fixed;
        inset: 0;
        z-index: 100;
    }

    .pdf-reader.is-fullscreen .pdf-viewer { flex: 1; max-height: none; }
    :root.pdf-open, :root.pdf-open body { overflow: hidden; }

    @media (max-width: 640px) {
        .pdf-bar { gap: 4px 6px; padding: 6px 8px; }
        .pdf-divider { display: none; }
        .pdf-pages { padding: 10px; }
    }

    @media print {
        .pdf-bar { display: none; }
    }
</style>
@endpush

<div class="pdf-reader" id="pdf-reader" data-src="{{ $src }}" data-worker="{{ $pdfjs }}/pdf.worker.min.js">
    <div class="pdf-bar" role="toolbar" aria-label="PDF reader" data-pdf-bar hidden>
        <div class="pdf-group" role="group" aria-label="Page layout">
            <button type="button" class="pdf-btn" data-pdf="single" title="One page" aria-label="One page" aria-pressed="false">@include('larapilot::dashboard.partials.icon', ['name' => 'page-single'])</button>
            <button type="button" class="pdf-btn" data-pdf="dual" title="Two pages side by side" aria-label="Two pages side by side" aria-pressed="false">@include('larapilot::dashboard.partials.icon', ['name' => 'page-dual'])</button>
        </div>

        <span class="pdf-divider" aria-hidden="true"></span>

        <div class="pdf-group" role="group" aria-label="Zoom">
            <button type="button" class="pdf-btn" data-pdf="zoom-out" title="Zoom out (−)" aria-label="Zoom out">@include('larapilot::dashboard.partials.icon', ['name' => 'minus'])</button>
            <button type="button" class="pdf-btn pdf-zoom-label" data-pdf="zoom-reset" title="Fit the width (0)" aria-label="Fit the width">100%</button>
            <button type="button" class="pdf-btn" data-pdf="zoom-in" title="Zoom in (+)" aria-label="Zoom in">@include('larapilot::dashboard.partials.icon', ['name' => 'plus'])</button>
        </div>

        <span class="pdf-divider" aria-hidden="true"></span>

        <div class="pdf-group" role="group" aria-label="Pages">
            <button type="button" class="pdf-btn" data-pdf="prev" title="Previous page (←)" aria-label="Previous page">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron-left'])</button>
            <span class="pdf-page-info">
                <input type="text" inputmode="numeric" class="pdf-page-input" data-pdf="page" value="1" autocomplete="off" aria-label="Page number">
                <span aria-hidden="true">of</span>
                <span data-pdf="total">…</span>
            </span>
            <button type="button" class="pdf-btn" data-pdf="next" title="Next page (→)" aria-label="Next page">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</button>
        </div>

        <span class="pdf-divider" aria-hidden="true"></span>

        <div class="pdf-group">
            <button type="button" class="pdf-btn" data-pdf="fullscreen" title="Full screen (F)" aria-label="Full screen">@include('larapilot::dashboard.partials.icon', ['name' => 'expand'])</button>
            <button type="button" class="pdf-btn" data-pdf="close" title="Leave full screen (Esc)" aria-label="Leave full screen" hidden>@include('larapilot::dashboard.partials.icon', ['name' => 'close'])</button>
            <a class="pdf-btn" href="{{ $download }}" title="Download the PDF" aria-label="Download {{ $name }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])</a>
        </div>
    </div>

    <div class="pdf-state" data-pdf="loading" role="status" hidden>
        <p>Opening the PDF…</p>
    </div>

    {{-- Shown as it is without scripts, and whenever the reader cannot start. --}}
    <div class="pdf-state" data-pdf="fallback" hidden>
        <p data-pdf="fallback-text">The reader could not start here, so the PDF opens in its own tab.</p>
        <a class="btn ghost" href="{{ $src }}" target="_blank" rel="noopener noreferrer">Open the PDF</a>
    </div>

    <noscript>
        <div class="pdf-state">
            <p>A PDF opens in its own tab.</p>
            <a class="btn ghost" href="{{ $src }}" target="_blank" rel="noopener noreferrer">Open the PDF</a>
        </div>
    </noscript>

    <div class="pdf-viewer" data-pdf="viewer" tabindex="0" aria-label="Pages of {{ $name }}" hidden>
        <div class="pdf-pages" data-pdf="pages">
            <div class="pdf-spacer" data-pdf="spacer" hidden></div>
            <div class="pdf-page" data-pdf="wrap-1"><canvas data-pdf="canvas-1"></canvas></div>
            <div class="pdf-page" data-pdf="wrap-2" hidden><canvas data-pdf="canvas-2"></canvas></div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ $pdfjs }}/pdf.min.js" integrity="sha384-/1qUCSGwTur9vjf/z9lmu/eCUYbpOTgSjmpbMQZ1/CtX2v/WcAIKqRv+U1DUCG6e" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var reader = document.getElementById('pdf-reader');

        if (! reader) {
            return;
        }

        var part = function (name) {
            return reader.querySelector('[data-pdf="' + name + '"]');
        };

        var bar = reader.querySelector('[data-pdf-bar]');
        var loading = part('loading');
        var fallback = part('fallback');
        var fallbackText = part('fallback-text');
        var viewer = part('viewer');
        var pages = part('pages');
        var spacer = part('spacer');
        var wraps = [part('wrap-1'), part('wrap-2')];
        var canvases = [part('canvas-1'), part('canvas-2')];
        var singleBtn = part('single');
        var dualBtn = part('dual');
        var zoomInBtn = part('zoom-in');
        var zoomOutBtn = part('zoom-out');
        var zoomResetBtn = part('zoom-reset');
        var prevBtn = part('prev');
        var nextBtn = part('next');
        var pageInput = part('page');
        var totalEl = part('total');
        var fullscreenBtn = part('fullscreen');
        var closeBtn = part('close');

        var giveUp = function (message) {
            loading.hidden = true;
            viewer.hidden = true;
            bar.hidden = true;
            fallback.hidden = false;

            if (message) {
                fallbackText.textContent = message;
            }
        };

        loading.hidden = false;

        if (typeof window.pdfjsLib === 'undefined') {
            giveUp('The PDF reader is loaded from cdnjs and could not be reached, so the PDF opens in its own tab.');

            return;
        }

        var pdfjs = window.pdfjsLib;
        var ZOOM_STEPS = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3, 4];
        var PAGE_GAP = 16;

        pdfjs.GlobalWorkerOptions.workerSrc = reader.dataset.worker;

        var doc = null;
        var pageNum = 1;
        var zoom = 1;
        var spread = 'dual';
        var fullscreen = false;
        var renderToken = 0;
        var renderTasks = [];

        var canSpread = function () {
            return window.innerWidth >= 1024 && doc && doc.numPages > 1;
        };

        var isDual = function () {
            return canSpread() && spread === 'dual';
        };

        // The first page stands alone, like the cover of a magazine.
        var isCover = function () {
            return isDual() && pageNum === 1;
        };

        var normalize = function (number) {
            var target = Math.min(Math.max(1, number), doc.numPages);

            if (isDual() && target > 1 && target % 2 !== 0) {
                target -= 1;
            }

            return target;
        };

        var pageWidth = function () {
            var style = window.getComputedStyle(pages);
            var padding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
            var width = Math.max(240, viewer.clientWidth - padding);

            return isDual() ? (width - PAGE_GAP) / 2 : Math.min(width, 980);
        };

        var cancelRenders = function () {
            renderTasks.forEach(function (task) {
                try { task.cancel(); } catch (error) { /* already finished */ }
            });

            renderTasks = [];
        };

        var draw = function (number, slot, token) {
            return doc.getPage(number).then(function (page) {
                if (token !== renderToken) {
                    return null;
                }

                var ratio = Math.min(window.devicePixelRatio || 1, 2);
                var natural = page.getViewport({ scale: 1 });
                var viewport = page.getViewport({ scale: (pageWidth() * zoom / natural.width) * ratio });
                var canvas = canvases[slot];

                canvas.width = Math.round(viewport.width);
                canvas.height = Math.round(viewport.height);
                canvas.style.width = Math.round(viewport.width / ratio) + 'px';
                canvas.style.height = Math.round(viewport.height / ratio) + 'px';
                wraps[slot].style.width = canvas.style.width;

                var task = page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport });

                renderTasks.push(task);

                return task.promise.catch(function (error) {
                    if (! error || error.name !== 'RenderingCancelledException') {
                        throw error;
                    }
                });
            });
        };

        var paint = function () {
            var spreadable = canSpread();
            var lastVisible = isDual() && ! isCover() ? pageNum + 1 : pageNum;
            var single = ! spreadable || spread === 'single';

            pageInput.value = String(pageNum);
            prevBtn.disabled = pageNum <= 1;
            nextBtn.disabled = lastVisible >= doc.numPages;

            singleBtn.disabled = ! spreadable;
            dualBtn.disabled = ! spreadable;
            singleBtn.classList.toggle('is-active', single);
            dualBtn.classList.toggle('is-active', ! single);
            singleBtn.setAttribute('aria-pressed', String(single));
            dualBtn.setAttribute('aria-pressed', String(! single));

            zoomResetBtn.textContent = Math.round(zoom * 100) + '%';
            zoomResetBtn.disabled = zoom === 1;
            zoomOutBtn.disabled = zoom <= ZOOM_STEPS[0];
            zoomInBtn.disabled = zoom >= ZOOM_STEPS[ZOOM_STEPS.length - 1];

            viewer.classList.toggle('is-pannable', zoom > 1);
        };

        var render = function () {
            if (! doc) {
                return Promise.resolve();
            }

            cancelRenders();

            var token = ++renderToken;
            var dual = isDual();
            var cover = isCover();
            var second = dual && ! cover && pageNum + 1 <= doc.numPages;

            spacer.hidden = ! cover;
            spacer.style.width = Math.round(pageWidth() * zoom) + 'px';
            wraps[1].hidden = ! second;

            var jobs = [draw(pageNum, 0, token)];

            if (second) {
                jobs.push(draw(pageNum + 1, 1, token));
            }

            paint();

            return Promise.all(jobs).catch(function () {
                giveUp('This page of the PDF could not be drawn, so the PDF opens in its own tab.');
            });
        };

        var goTo = function (number) {
            var target = normalize(number);

            if (target === pageNum) {
                paint();

                return;
            }

            pageNum = target;

            render().then(function () {
                viewer.scrollTop = 0;
                viewer.scrollLeft = (viewer.scrollWidth - viewer.clientWidth) / 2;
            });
        };

        var step = function (direction) {
            goTo(pageNum + direction * (isDual() && ! isCover() ? 2 : 1));
        };

        var setSpread = function (mode) {
            if (! canSpread() || spread === mode) {
                return;
            }

            spread = mode;
            pageNum = normalize(pageNum);
            render();
        };

        // Keeps the point under the pointer (or the centre) still while zooming.
        var setZoom = function (value, anchor) {
            var next = Math.min(Math.max(value, ZOOM_STEPS[0]), ZOOM_STEPS[ZOOM_STEPS.length - 1]);

            if (next === zoom) {
                return;
            }

            var box = viewer.getBoundingClientRect();
            var x = anchor ? anchor.x - box.left : viewer.clientWidth / 2;
            var y = anchor ? anchor.y - box.top : viewer.clientHeight / 2;
            var ratio = next / zoom;
            var left = (viewer.scrollLeft + x) * ratio - x;
            var top = (viewer.scrollTop + y) * ratio - y;

            zoom = next;

            render().then(function () {
                viewer.scrollLeft = left;
                viewer.scrollTop = top;
            });
        };

        var zoomStep = function (direction, anchor) {
            var candidates = ZOOM_STEPS.filter(function (value) {
                return direction > 0 ? value > zoom + 0.001 : value < zoom - 0.001;
            });

            if (candidates.length) {
                setZoom(direction > 0 ? candidates[0] : candidates[candidates.length - 1], anchor);
            }
        };

        var setFullscreen = function (next) {
            if (next === fullscreen) {
                return;
            }

            fullscreen = next;
            reader.classList.toggle('is-fullscreen', next);
            document.documentElement.classList.toggle('pdf-open', next);
            fullscreenBtn.hidden = next;
            closeBtn.hidden = ! next;

            render().then(function () {
                if (next) {
                    viewer.focus();
                } else {
                    reader.scrollIntoView({ block: 'nearest' });
                    fullscreenBtn.focus();
                }
            });
        };

        singleBtn.addEventListener('click', function () { setSpread('single'); });
        dualBtn.addEventListener('click', function () { setSpread('dual'); });
        prevBtn.addEventListener('click', function () { step(-1); });
        nextBtn.addEventListener('click', function () { step(1); });
        zoomInBtn.addEventListener('click', function () { zoomStep(1); });
        zoomOutBtn.addEventListener('click', function () { zoomStep(-1); });
        zoomResetBtn.addEventListener('click', function () { setZoom(1); });
        fullscreenBtn.addEventListener('click', function () { setFullscreen(true); });
        closeBtn.addEventListener('click', function () { setFullscreen(false); });

        pageInput.addEventListener('change', function () {
            var value = parseInt(pageInput.value, 10);

            if (isNaN(value)) {
                pageInput.value = String(pageNum);

                return;
            }

            goTo(value);
        });

        pageInput.addEventListener('keydown', function (event) {
            event.stopPropagation();

            if (event.key === 'Enter') {
                pageInput.blur();
            }
        });

        viewer.addEventListener('wheel', function (event) {
            if (! event.ctrlKey && ! event.metaKey) {
                return;
            }

            event.preventDefault();
            zoomStep(event.deltaY < 0 ? 1 : -1, { x: event.clientX, y: event.clientY });
        }, { passive: false });

        reader.addEventListener('keydown', function (event) {
            if (event.target === pageInput || event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            var keys = {
                ArrowRight: function () { step(1); },
                PageDown: function () { step(1); },
                ArrowLeft: function () { step(-1); },
                PageUp: function () { step(-1); },
                '+': function () { zoomStep(1); },
                '=': function () { zoomStep(1); },
                '-': function () { zoomStep(-1); },
                '_': function () { zoomStep(-1); },
                '0': function () { setZoom(1); },
                f: function () { setFullscreen(! fullscreen); },
                F: function () { setFullscreen(! fullscreen); },
            };

            if (keys[event.key]) {
                event.preventDefault();
                keys[event.key]();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (fullscreen && event.key === 'Escape') {
                event.preventDefault();
                setFullscreen(false);
            }
        });

        // Drag to move around a page that is larger than the window.
        var pan = null;

        viewer.addEventListener('pointerdown', function (event) {
            if (event.button !== 0 || zoom <= 1 || event.pointerType === 'touch') {
                return;
            }

            pan = { id: event.pointerId, x: event.clientX, y: event.clientY, left: viewer.scrollLeft, top: viewer.scrollTop };
            viewer.setPointerCapture(event.pointerId);
            viewer.classList.add('is-panning');
        });

        viewer.addEventListener('pointermove', function (event) {
            if (! pan || pan.id !== event.pointerId) {
                return;
            }

            viewer.scrollLeft = pan.left - (event.clientX - pan.x);
            viewer.scrollTop = pan.top - (event.clientY - pan.y);
        });

        var endPan = function (event) {
            if (! pan || pan.id !== event.pointerId) {
                return;
            }

            pan = null;
            viewer.classList.remove('is-panning');

            try { viewer.releasePointerCapture(event.pointerId); } catch (error) { /* already released */ }
        };

        viewer.addEventListener('pointerup', endPan);
        viewer.addEventListener('pointercancel', endPan);

        var resizeTimer = 0;
        var lastWidth = window.innerWidth;

        window.addEventListener('resize', function () {
            if (window.innerWidth === lastWidth) {
                return;
            }

            lastWidth = window.innerWidth;
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                if (doc) {
                    pageNum = normalize(pageNum);
                    render();
                }
            }, 150);
        });

        pdfjs.getDocument({ url: reader.dataset.src, withCredentials: true }).promise.then(function (loaded) {
            doc = loaded;
            totalEl.textContent = String(loaded.numPages);
            pageInput.size = String(loaded.numPages).length + 1;
            loading.hidden = true;
            bar.hidden = false;
            viewer.hidden = false;
            render();
        }).catch(function () {
            giveUp('This file could not be read as a PDF, so it opens in its own tab.');
        });
    });
</script>
@endpush
