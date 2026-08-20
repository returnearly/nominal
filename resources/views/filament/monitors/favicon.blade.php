<div
    hidden
    aria-hidden="true"
    data-nm-favicon="{{ $this->faviconHref }}"
    data-nm-down="{{ $this->downCount }}"
    x-data="{
        href: $wire.entangle('faviconHref'),
        count: $wire.entangle('downCount'),
        defaultHref: {{ \Illuminate\Support\Js::from(asset('favicon.svg')) }},
        iconLink() {
            return document.querySelector('link[rel=\'icon\']')
        },
        apply(href, count) {
            const link = this.iconLink()

            if (link && href) {
                link.href = href
            }

            document.documentElement.classList.toggle('nm-monitors-down', Number(count) > 0)
        },
        init() {
            const link = this.iconLink()

            if (link && ! link.dataset.nmDefault) {
                link.dataset.nmDefault = link.getAttribute('href') || this.defaultHref
            }

            this.apply(this.href, this.count)
        },
        destroy() {
            this.apply(this.iconLink()?.dataset.nmDefault || this.defaultHref, 0)
        },
    }"
    x-effect="apply(href, count)"
></div>
