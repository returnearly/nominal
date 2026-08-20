<div
    hidden
    aria-hidden="true"
    data-nm-favicon="{{ $this->faviconHref }}"
    x-data="{
        href: $wire.entangle('faviconHref'),
        defaultHref: {{ \Illuminate\Support\Js::from(asset('favicon.svg')) }},
        iconLink() {
            return document.querySelector('link[rel=\'icon\']')
        },
        apply(href) {
            const link = this.iconLink()

            if (! link || ! href) {
                return
            }

            link.href = href
        },
        init() {
            const link = this.iconLink()

            if (link && ! link.dataset.nmDefault) {
                link.dataset.nmDefault = link.getAttribute('href') || this.defaultHref
            }

            this.apply(this.href)
        },
        destroy() {
            this.apply(this.iconLink()?.dataset.nmDefault || this.defaultHref)
        },
    }"
    x-effect="apply(href)"
></div>
