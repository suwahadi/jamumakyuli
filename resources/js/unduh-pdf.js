const PX_PER_MM = 96 / 25.4
const PT_PER_PX = 72 / 96
const MAKS_PIKSEL = 16_000_000
const teks = new TextEncoder()

const angka = (n) => Number(n.toFixed(3)).toString()

async function gayaHalaman() {
    const tautan = [...document.querySelectorAll('link[rel="stylesheet"]')]
    const isi = await Promise.all(tautan.map((l) => fetch(l.href, { headers: { Accept: 'text/css' } }).then((r) => r.text())))

    return [...isi, ...[...document.querySelectorAll('style')].map((s) => s.textContent)].join('\n')
}

async function keDataUrl(url) {
    const berkas = await (await fetch(url)).blob()

    return new Promise((selesai, gagal) => {
        const pembaca = new FileReader()
        pembaca.onload = () => selesai(pembaca.result)
        pembaca.onerror = gagal
        pembaca.readAsDataURL(berkas)
    })
}

function titikAman(akar) {
    const doc = akar.ownerDocument
    const atas = akar.getBoundingClientRect().top
    const kotak = []
    const tambah = (r) => {
        if (r.height > 0) kotak.push([r.top - atas, r.bottom - atas])
    }
    const jalan = doc.createTreeWalker(akar, NodeFilter.SHOW_TEXT)
    const rentang = doc.createRange()

    while (jalan.nextNode()) {
        if (jalan.currentNode.textContent.trim() === '') continue
        rentang.selectNodeContents(jalan.currentNode)
        Array.from(rentang.getClientRects()).forEach(tambah)
    }
    akar.querySelectorAll('tr, img').forEach((el) => tambah(el.getBoundingClientRect()))

    return [...new Set(kotak.map(([, bawah]) => bawah))]
        .filter((y) => !kotak.some(([t, b]) => t < y - 0.5 && y + 0.5 < b))
        .sort((a, b) => a - b)
}

async function susunUlang(sumber, lebar, css) {
    const bingkai = document.createElement('iframe')
    bingkai.setAttribute('aria-hidden', 'true')
    bingkai.tabIndex = -1
    bingkai.style.cssText = `position:fixed;left:-10000px;top:0;width:${lebar}px;height:10px;border:0;visibility:hidden`
    document.body.append(bingkai)

    try {
        const doc = bingkai.contentDocument
        doc.open()
        doc.write('<!DOCTYPE html><html><head></head><body></body></html>')
        doc.close()

        const gaya = doc.createElement('style')
        gaya.textContent = css
        doc.head.append(gaya)

        const pembungkus = doc.createElement('div')
        pembungkus.className = document.body.className
        pembungkus.style.fontFamily = bingkai.contentWindow.getComputedStyle(doc.documentElement).fontFamily

        const salinan = doc.importNode(sumber, true)
        Object.assign(salinan.style, { margin: '0', border: '0', boxShadow: 'none', maxWidth: 'none', width: `${lebar}px` })
        pembungkus.append(salinan)
        doc.body.append(pembungkus)

        const asal = [...sumber.querySelectorAll('img')]
        await Promise.all([...salinan.querySelectorAll('img')].map(async (img, i) => {
            img.removeAttribute('srcset')
            img.src = await keDataUrl(asal[i].currentSrc || asal[i].src)
            await img.decode().catch(() => {})
        }))

        return {
            xhtml: new XMLSerializer().serializeToString(pembungkus),
            tinggi: salinan.getBoundingClientRect().height,
            aman: titikAman(salinan),
        }
    } finally {
        bingkai.remove()
    }
}

async function muatSvg(xhtml, css, lebar, tinggi) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${lebar}" height="${tinggi}"><style><![CDATA[${css}]]></style>`
        + `<foreignObject x="0" y="0" width="100%" height="100%">${xhtml}</foreignObject></svg>`

    const img = new Image()
    await new Promise((selesai, gagal) => {
        img.onload = selesai
        img.onerror = gagal
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg)
    })

    return img
}

async function gambarHalaman(img, [awal, akhir], atas, lebar, tinggiHalaman) {
    const skala = Math.min(3, Math.sqrt(MAKS_PIKSEL / (lebar * tinggiHalaman)))
    const kanvas = document.createElement('canvas')
    kanvas.width = Math.round(lebar * skala)
    kanvas.height = Math.round(tinggiHalaman * skala)
    const ctx = kanvas.getContext('2d')
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, kanvas.width, kanvas.height)
    ctx.drawImage(img, 0, awal, lebar, akhir - awal, 0, atas * skala, kanvas.width, (akhir - awal) * skala)

    return { lebar: kanvas.width, tinggi: kanvas.height, isi: await rgbTerkompresi(kanvas) }
}

function potongHalaman(tinggi, aman, tinggiHalaman, margin) {
    const halaman = []
    let awal = 0

    while (awal < tinggi - 1) {
        const muat = tinggiHalaman - (halaman.length ? margin : 0)
        const akhir = awal + muat + 1 >= tinggi
            ? tinggi
            : aman.filter((y) => y > awal + 1 && y <= awal + muat).pop() ?? awal + muat

        halaman.push([awal, akhir])
        awal = akhir
    }

    return halaman
}

async function rgbTerkompresi(kanvas) {
    const { data } = kanvas.getContext('2d').getImageData(0, 0, kanvas.width, kanvas.height)
    const rgb = new Uint8Array((data.length / 4) * 3)

    for (let i = 0, j = 0; i < data.length; i += 4) {
        rgb[j++] = data[i]
        rgb[j++] = data[i + 1]
        rgb[j++] = data[i + 2]
    }

    const aliran = new Blob([rgb]).stream().pipeThrough(new CompressionStream('deflate'))

    return new Uint8Array(await new Response(aliran).arrayBuffer())
}

function susunPdf(gambarTiapHalaman, { lebar, tinggiHalaman }) {
    const bagian = []
    const posisi = []
    let panjang = 0

    const tulis = (isi) => {
        const bita = typeof isi === 'string' ? teks.encode(isi) : isi
        bagian.push(bita)
        panjang += bita.length
    }

    const objek = (nomor, kamus, aliran = null) => {
        posisi[nomor] = panjang
        tulis(`${nomor} 0 obj\n${kamus}\n`)
        if (aliran) {
            tulis('stream\n')
            tulis(aliran)
            tulis('\nendstream\n')
        }
        tulis('endobj\n')
    }

    const lebarPt = angka(lebar * PT_PER_PX)
    const tinggiPt = angka(tinggiHalaman * PT_PER_PX)
    const isiHalaman = `q ${lebarPt} 0 0 ${tinggiPt} 0 0 cm /Im0 Do Q`

    tulis('%PDF-1.4\n')
    objek(1, '<< /Type /Catalog /Pages 2 0 R >>')
    objek(2, `<< /Type /Pages /Kids [${gambarTiapHalaman.map((_, i) => `${3 + i * 3} 0 R`).join(' ')}] /Count ${gambarTiapHalaman.length} >>`)

    gambarTiapHalaman.forEach((g, i) => {
        objek(3 + i * 3, `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${lebarPt} ${tinggiPt}] /Resources << /XObject << /Im0 ${5 + i * 3} 0 R >> >> /Contents ${4 + i * 3} 0 R >>`)
        objek(4 + i * 3, `<< /Length ${isiHalaman.length} >>`, teks.encode(isiHalaman))
        objek(5 + i * 3, `<< /Type /XObject /Subtype /Image /Width ${g.lebar} /Height ${g.tinggi} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length ${g.isi.length} >>`, g.isi)
    })

    const jumlahObjek = 3 + gambarTiapHalaman.length * 3
    const awalXref = panjang
    tulis(`xref\n0 ${jumlahObjek}\n0000000000 65535 f \n`)
    for (let n = 1; n < jumlahObjek; n++) {
        tulis(`${String(posisi[n]).padStart(10, '0')} 00000 n \n`)
    }
    tulis(`trailer\n<< /Size ${jumlahObjek} /Root 1 0 R >>\nstartxref\n${awalXref}\n%%EOF\n`)

    return new Blob(bagian, { type: 'application/pdf' })
}

document.querySelectorAll('[data-unduh-pdf]').forEach((tombol) => {
    const status = document.querySelector('[data-status-unduh]')
    const kabari = (pesan, galat = false) => {
        status.textContent = pesan
        status.classList.toggle('text-red-700', galat)
        status.classList.toggle('text-zinc-700', !galat)
    }

    tombol.addEventListener('click', async () => {
        const [lebarMm, tinggiMm] = tombol.dataset.ukuranMm.split('x').map(Number)
        const lebar = lebarMm * PX_PER_MM
        const tinggiHalaman = tinggiMm * PX_PER_MM
        const margin = Number(tombol.dataset.marginMm ?? 0) * PX_PER_MM

        tombol.disabled = true
        kabari(tombol.dataset.teksProses ?? 'Menyiapkan PDF…')

        try {
            const css = await gayaHalaman()
            const isi = await susunUlang(document.querySelector('main'), lebar, css)
            const img = await muatSvg(isi.xhtml, css, lebar, isi.tinggi)
            const halaman = []
            for (const [i, rentang] of potongHalaman(isi.tinggi, isi.aman, tinggiHalaman, margin).entries()) {
                halaman.push(await gambarHalaman(img, rentang, i === 0 ? 0 : margin, lebar, tinggiHalaman))
            }
            const pdf = susunPdf(halaman, { lebar, tinggiHalaman })

            const unduhan = document.createElement('a')
            unduhan.href = URL.createObjectURL(pdf)
            unduhan.download = tombol.dataset.unduhPdf
            unduhan.click()
            setTimeout(() => URL.revokeObjectURL(unduhan.href), 1000)
            kabari('')
        } catch {
            kabari(tombol.dataset.teksGagal ?? 'PDF gagal dibuat di browser ini. Tekan Cetak, lalu pilih Simpan sebagai PDF.', true)
        } finally {
            tombol.disabled = false
        }
    })
})
