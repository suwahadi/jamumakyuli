document.addEventListener('alpine:init', () => {
    window.Alpine.data('wysiwyg', (model, placeholder = '') => ({
        tick: 0,

        init() {
            const el = this.$refs.editor

            if (el.dataset.ready) return
            el.dataset.ready = '1'

            try {
                document.execCommand('styleWithCSS', false, false)
            } catch (e) {
            }

            el.innerHTML = this.$wire.get(model) || ''
            this.refreshEmpty()

            const push = () => {
                this.$wire.set(model, el.innerHTML, false)
                this.refreshEmpty()
            }
            const refresh = () => this.tick++

            el.addEventListener('input', () => {
                push()
                refresh()
            })
            el.addEventListener('keyup', refresh)
            el.addEventListener('mouseup', refresh)
            document.addEventListener('selectionchange', () => {
                if (document.activeElement === el) this.tick++
            })

            this.$wire.$watch(model, (value) => {
                const incoming = value || ''
                if (incoming !== el.innerHTML && document.activeElement !== el) {
                    el.innerHTML = incoming
                    this.refreshEmpty()
                }
            })
        },

        refreshEmpty() {
            const el = this.$refs.editor
            const empty = el.textContent.trim() === '' && !el.querySelector('img')
            el.classList.toggle('is-empty', empty)
        },

        cmd(command, value = null) {
            this.$refs.editor.focus()
            document.execCommand(command, false, value)
            this.$wire.set(model, this.$refs.editor.innerHTML, false)
            this.refreshEmpty()
            this.tick++
        },

        block(tag) {
            const current = (document.queryCommandValue('formatBlock') || '').toLowerCase()
            this.cmd('formatBlock', current === tag ? '<p>' : '<' + tag + '>')
        },

        state(command) {
            this.tick
            try {
                return document.queryCommandState(command)
            } catch (e) {
                return false
            }
        },

        isBlock(tag) {
            this.tick
            try {
                return (document.queryCommandValue('formatBlock') || '').toLowerCase() === tag
            } catch (e) {
                return false
            }
        },

        setLink() {
            const url = window.prompt('Masukkan URL tautan:', 'https://')
            if (url) this.cmd('createLink', url)
        },

        setImage() {
            const url = window.prompt('Masukkan URL gambar:', 'https://')
            if (url) this.cmd('insertImage', url)
        },
    }))
})

document.addEventListener('alpine:init', () => {
    const ZOOM_KETUK = 2.5
    const LANGKAH_ZOOM = 1.5

    const clamp = (n, min, max) => Math.min(Math.max(n, min), max)
    const tengah = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 })
    const jarak = (a, b) => Math.hypot(a.x - b.x, a.y - b.y)

    window.Alpine.data('productLightbox', (images = []) => ({
        images,
        isOpen: false,
        index: 0,
        zoom: { s: 1, x: 0, y: 0 },
        animating: false,
        gesture: null,
        lastTap: 0,
        returnFocus: null,

        openBySrc(src) {
            const norm = (s) => {
                try {
                    return new URL(s, window.location.href).pathname
                } catch (e) {
                    return s
                }
            }
            const target = norm(src)
            const i = this.images.findIndex((u) => norm(u) === target)
            this.open(i >= 0 ? i : 0)
        },

        open(i = 0, images = null) {
            if (images) this.images = images
            if (!this.images.length) return
            this.go(clamp(i, 0, this.images.length - 1))
            this.returnFocus = document.activeElement
            this.isOpen = true
            document.body.style.overflow = 'hidden'
            this.$nextTick(() => this.$refs.lightboxClose.focus())
        },

        close() {
            this.isOpen = false
            document.body.style.overflow = ''
            this.returnFocus?.focus()
        },

        go(i) {
            this.index = (i + this.images.length) % this.images.length
            this.resetZoom()
        },

        next() {
            this.go(this.index + 1)
        },

        prev() {
            this.go(this.index - 1)
        },

        zoomIn() {
            this.zoomBy(LANGKAH_ZOOM)
        },

        zoomOut() {
            this.zoomBy(1 / LANGKAH_ZOOM)
        },

        zoomBy(k) {
            const s = clamp(this.zoom.s * k, 1, this.maxZoom())
            this.animating = true
            if (s < 1.05) {
                this.resetZoom()
                return
            }
            const f = s / this.zoom.s
            this.setZoom(s, this.zoom.x * f, this.zoom.y * f)
        },

        onKey(e) {
            if (e.ctrlKey || e.metaKey || e.altKey) return
            if (e.key === '+' || e.key === '=') {
                this.zoomIn()
            } else if (e.key === '-' || e.key === '_') {
                this.zoomOut()
            } else if (e.key === '0') {
                this.animating = true
                this.resetZoom()
            }
        },

        onPointerDown(e) {
            if (e.pointerType !== 'mouse' || e.button !== 0 || this.zoom.s <= 1) return
            e.preventDefault()
            e.currentTarget.setPointerCapture(e.pointerId)
            this.animating = false
            this.gesture = { type: 'drag', p: this.point(e), z: { ...this.zoom } }
        },

        onPointerMove(e) {
            const g = this.gesture
            if (g?.type !== 'drag') return
            const p = this.point(e)
            this.setZoom(g.z.s, g.z.x + p.x - g.p.x, g.z.y + p.y - g.p.y)
        },

        onPointerUp() {
            if (this.gesture?.type === 'drag') this.gesture = null
        },

        imgStyle() {
            return `transform: translate(${this.zoom.x}px, ${this.zoom.y}px) scale(${this.zoom.s})`
        },

        resetZoom() {
            this.zoom = { s: 1, x: 0, y: 0 }
            this.gesture = null
        },

        point(t) {
            const img = this.$refs.lightboxImg
            const r = img.offsetParent.getBoundingClientRect()

            return {
                x: t.clientX - (r.left + img.offsetLeft + img.offsetWidth / 2),
                y: t.clientY - (r.top + img.offsetTop + img.offsetHeight / 2),
            }
        },

        maxZoom() {
            const img = this.$refs.lightboxImg
            if (!img?.offsetWidth || !img.naturalWidth) return 4

            return Math.max(4, (img.naturalWidth / img.offsetWidth) * 2)
        },

        setZoom(s, x, y) {
            const img = this.$refs.lightboxImg
            const mx = ((s - 1) * img.offsetWidth) / 2
            const my = ((s - 1) * img.offsetHeight) / 2
            this.zoom = { s, x: clamp(x, -mx, mx), y: clamp(y, -my, my) }
        },

        startPan(t) {
            this.gesture = { type: this.zoom.s > 1 ? 'pan' : 'swipe', p: this.point(t), z: { ...this.zoom } }
        },

        onTouchStart(e) {
            if (e.target.closest('button')) return
            this.animating = false
            if (e.touches.length === 2) {
                const a = this.point(e.touches[0])
                const b = this.point(e.touches[1])
                this.gesture = { type: 'pinch', d: jarak(a, b), m: tengah(a, b), z: { ...this.zoom } }
            } else if (e.touches.length === 1) {
                this.startPan(e.touches[0])
            }
        },

        onTouchMove(e) {
            const g = this.gesture
            if (!g) return
            if (g.type === 'pinch' && e.touches.length === 2) {
                const a = this.point(e.touches[0])
                const b = this.point(e.touches[1])
                const m = tengah(a, b)
                const s = clamp((g.z.s * jarak(a, b)) / g.d, 1, this.maxZoom())
                const k = s / g.z.s
                this.setZoom(s, m.x - (g.m.x - g.z.x) * k, m.y - (g.m.y - g.z.y) * k)
            } else if (g.type === 'pan' && e.touches.length === 1) {
                const p = this.point(e.touches[0])
                this.setZoom(g.z.s, g.z.x + p.x - g.p.x, g.z.y + p.y - g.p.y)
            }
        },

        onTouchEnd(e) {
            const g = this.gesture
            if (!g) return
            if (e.touches.length === 1) {
                this.startPan(e.touches[0])
                return
            }
            this.gesture = null
            if (this.zoom.s < 1.05) this.resetZoom()
            if (g.type === 'pinch') return

            const p = this.point(e.changedTouches[0])
            const dx = p.x - g.p.x
            if (g.type === 'swipe' && Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(p.y - g.p.y)) {
                dx < 0 ? this.next() : this.prev()
            } else if (jarak(p, g.p) < 10) {
                this.tap(p)
            }
        },

        tap(p) {
            const now = Date.now()
            if (now - this.lastTap > 300) {
                this.lastTap = now
                return
            }
            this.lastTap = 0
            this.animating = true
            if (this.zoom.s > 1) {
                this.resetZoom()
            } else {
                this.setZoom(ZOOM_KETUK, p.x * (1 - ZOOM_KETUK), p.y * (1 - ZOOM_KETUK))
            }
        },
    }))
})

document.addEventListener('alpine:init', () => {
    window.Alpine.data('productRail', () => ({
        index: 0,
        max: 0,
        perView: 1,
        touchX: null,
        onResize: null,

        init() {
            this.measure()
            this.onResize = () => this.measure()
            window.addEventListener('resize', this.onResize)
        },

        items() {
            return Array.from(this.$refs.track.children)
        },

        step() {
            const first = this.items()[0]

            return first ? first.getBoundingClientRect().width + 16 : 0
        },

        measure() {
            const step = this.step()

            if (!step) return

            this.perView = Math.max(1, Math.round((this.$refs.viewport.getBoundingClientRect().width + 16) / step))
            this.max = Math.max(0, this.items().length - this.perView)

            if (this.index > this.max) this.index = this.max

            this.apply(false)
        },

        visible(i) {
            return i >= this.index && i < this.index + this.perView
        },

        apply(animate = true) {
            this.$refs.track.style.transition = animate ? 'transform .5s ease-out' : 'none'
            this.$refs.track.style.transform = `translateX(-${this.index * this.step()}px)`
        },

        go(i) {
            this.index = Math.min(Math.max(i, 0), this.max)
            this.apply()
        },

        next() {
            this.go(this.index + 1)
        },

        prev() {
            this.go(this.index - 1)
        },

        onTouchStart(e) {
            this.touchX = e.changedTouches[0].clientX
        },

        onTouchEnd(e) {
            if (this.touchX === null) return

            const dx = e.changedTouches[0].clientX - this.touchX

            if (Math.abs(dx) > 45) {
                dx < 0 ? this.next() : this.prev()
            }

            this.touchX = null
        },

        destroy() {
            window.removeEventListener('resize', this.onResize)
        },
    }))
})

let dariSalinanKembali = false

document.addEventListener('livewire:navigate', (event) => {
    dariSalinanKembali = event.detail?.cached === true
})

document.addEventListener('livewire:navigated', () => {
    if (!dariSalinanKembali) return
    dariSalinanKembali = false

    document.querySelectorAll('[data-segarkan-saat-kembali]').forEach((el) => {
        window.Livewire.find(el.getAttribute('wire:id'))?.$refresh()
    })
})
