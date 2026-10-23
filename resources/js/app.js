import "./bootstrap";
import collapse from "@alpinejs/collapse";
import mask from "@alpinejs/mask";
import toastr from "toastr";
import "toastr/build/toastr.min.css";

window.toastr = toastr;

// TinyMCE — LAZY-LOADED (dynamic import) supaya bundle JS awal ringan.
// Editor (core + plugin + skin) baru di-fetch saat editor PERTAMA dibuka,
// lalu di-cache di window.tinymce. Self-hosted (npm), GPL, no API key.
let _tinymcePromise = null;
function loadTinymce() {
    if (window.tinymce) return Promise.resolve(window.tinymce);
    if (_tinymcePromise) return _tinymcePromise;
    _tinymcePromise = (async () => {
        const tinymce = (await import("tinymce")).default;
        // Plugin/tema/skin (side-effect import) — daftar ke singleton tinymce.
        await Promise.all([
            import("tinymce/icons/default"),
            import("tinymce/themes/silver"),
            import("tinymce/models/dom"),
            import("tinymce/plugins/lists"),
            import("tinymce/plugins/link"),
            import("tinymce/plugins/table"),
            import("tinymce/plugins/autolink"),
            import("tinymce/plugins/code"),
            import("tinymce/plugins/charmap"),
            import("tinymce/plugins/fullscreen"),
            import("tinymce/skins/ui/oxide/skin.min.css"),
        ]);
        window.__tinymceContentCss = (await import("tinymce/skins/content/default/content.min.css?inline")).default;
        window.__tinymceContentUiCss = (await import("tinymce/skins/ui/oxide/content.min.css?inline")).default;
        window.tinymce = tinymce;
        return tinymce;
    })();
    return _tinymcePromise;
}

// Alpine.data factory untuk <x-tinymce-editor>
document.addEventListener("alpine:init", () => {
    window.Alpine.data(
        "tinymceEditor",
        ({
            propName,
            placeholder = "Tulis di sini…",
            modalEvent = null,
            flushEvent = null,
            reloadEvent = null,
            height = 480,
            // CSS tambahan utk content editor (WYSIWYG) — di-append ke content_style.
            // Dipakai mis. supaya tampilan editor mirror tema cetak (font/border/warna).
            contentStyle = "",
        }) => ({
            editor: null,
            init() {
                if (modalEvent) {
                    window.addEventListener("open-modal", (e) => {
                        if (e.detail?.name === modalEvent) {
                            setTimeout(() => this.bootEditor(), 120);
                        }
                    });
                    window.addEventListener("close-modal", (e) => {
                        if (e.detail?.name === modalEvent) {
                            this.cleanupEditor();
                        }
                    });
                } else {
                    this.$nextTick(() => this.bootEditor());
                }
                if (flushEvent) {
                    window.addEventListener(flushEvent, () => this.flush());
                }
                if (reloadEvent) {
                    window.addEventListener(reloadEvent, () => this.reload());
                }
            },
            reload() {
                if (!this.editor) return;
                const fresh = this.$wire.get(propName) || "";
                this.editor.setContent(fresh);
            },
            cleanupEditor() {
                const tinymce = window.tinymce;
                if (!tinymce) return; // belum pernah dimuat → tak ada yang dibersihkan
                // tinymce.remove() = canonical cleanup di TinyMCE 8 (destroy + unbind global).
                if (this.editor) {
                    try { tinymce.remove(this.editor); } catch (e) {
                        console.warn("[tinymceEditor] remove failed:", e);
                    }
                    this.editor = null;
                }
                // Defensive: hapus orphan instance yang masih nempel ke host
                const host = this.$refs?.host;
                if (host && host.id) {
                    try {
                        const orphan = tinymce.get(host.id);
                        if (orphan) tinymce.remove(orphan);
                    } catch (e) {}
                }
            },
            async bootEditor() {
                const tinymce = await loadTinymce();
                this.cleanupEditor();
                const host = this.$refs.host;
                if (!host) {
                    console.warn("[tinymceEditor] x-ref host not found");
                    return;
                }

                // Workaround bug TinyMCE 8: `purgeDestroyedEditor` crash kalau ada
                // null entry di `tinymce.editors` global registry (dari destroy/remove
                // sebelumnya yang tidak bersih). Force-filter null sebelum init.
                try {
                    if (Array.isArray(tinymce.editors)) {
                        tinymce.editors = tinymce.editors.filter((e) => e != null);
                    }
                } catch (e) {
                    console.warn("[tinymceEditor] could not purge null editors:", e);
                }

                // Reset textarea state — sapu bersih artifact dari destroy sebelumnya
                host.style.cssText = "";
                host.className = "";
                host.value = "";

                // Assign unique ID + pakai selector (lebih reliable daripada target)
                host.id = "tinymce-host-" + Math.random().toString(36).slice(2);

                const initial = this.$wire.get(propName) || "";
                console.log("[tinymceEditor] init start", {
                    propName,
                    hostId: host.id,
                    initialLen: initial.length,
                });
                tinymce
                    .init({
                        selector: "#" + host.id,
                        // TinyMCE 6.8+ / 7+ / 8+ wajib explicit license key.
                        // 'gpl' = pakai versi open-source (GPLv2), tidak butuh API key Tiny Cloud.
                        license_key: "gpl",
                        height: height,
                        menubar: false,
                        branding: false,
                        promotion: false,
                        plugins: "lists link table autolink code charmap fullscreen",
                        toolbar:
                            "undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | " +
                            "alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | " +
                            "table | link charmap | removeformat code | fullscreen",
                        placeholder: placeholder,
                        skin: false,
                        content_css: false,
                        content_style:
                            window.__tinymceContentCss +
                            "\n" +
                            window.__tinymceContentUiCss +
                            "\nbody { font-size: 14px; }" +
                            (contentStyle ? "\n" + contentStyle : ""),
                        setup: (ed) => {
                            this.editor = ed;
                            ed.on("init", () => {
                                console.log("[tinymceEditor] editor init complete");
                                if (initial) ed.setContent(initial);
                            });
                            ed.on(
                                "input change keyup blur SetContent",
                                () => this.flush()
                            );
                        },
                    })
                    .then((editors) => {
                        console.log("[tinymceEditor] init resolved", editors?.length);
                    })
                    .catch((err) => {
                        console.error("[tinymceEditor] init failed:", err);
                    });
            },
            flush() {
                if (!this.editor) return;
                const html = this.editor.getContent();
                this.$wire.set(propName, html, false);
            },
        })
    );
});

// ✅ Plugin didaftarkan via event, Alpine-nya dari Livewire
document.addEventListener("alpine:init", () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(mask);

    // x-enter-chain: Enter di input/select → fokus field berikutnya (entry cepat).
    // Textarea/checkbox/radio/disabled/hidden dilewati. Pasang di wrapper form (mis. body modal).
    window.Alpine.directive("enter-chain", (el) => {
        const SEL =
            'input:not([type=checkbox]):not([type=radio]):not([type=hidden]), select';
        el.addEventListener("keydown", (e) => {
            if (e.key !== "Enter") return;
            const t = e.target;
            if (!t.matches || !t.matches(SEL)) return; // textarea/tombol → biarkan
            e.preventDefault();
            const els = [...el.querySelectorAll(SEL + ", textarea")].filter(
                (x) => !x.disabled && !x.readOnly && x.offsetParent !== null
            );
            const i = els.indexOf(t);
            if (i > -1 && i < els.length - 1) els[i + 1].focus();
        });
    });

    // x-melayang="<elemen patokan>": pendamping x-anchor.fixed untuk dropdown yang melayang
    // (LOV, menu titik-3). x-anchor mengurus POSISI & arah buka (flip ke atas bila ruang bawah
    // kurang); directive ini mengurus UKURAN:
    //   - tinggi maks = ruang terlega di atas/bawah patokan (min 120px), tak melebihi max-h
    //     kelas aslinya (max-h-72/96 dst.) — sisanya di-scroll di dalam daftar;
    //   - .lebar   → lebar dropdown = lebar patokan (LOV selebar input);
    //   - .daftar  → tinggi maks dipasang di <ul> pertama (daftar LOV), bukan di elemen ini.
    // Diukur ulang saat elemen tampil/berubah ukuran (ResizeObserver), resize & scroll halaman.
    // Elemennya WAJIB wire:ignore.self di komponen Livewire — tanpa itu morph menghapus style.
    window.Alpine.directive("melayang", (el, { expression, modifiers }, { evaluateLater, cleanup }) => {
        const ambilPatokan = evaluateLater(expression || "$el.parentElement");
        // <ul> dicari ULANG tiap ukur: di x-combobox daftarnya baru lahir saat dibuka (x-if).
        // max-h kelas aslinya dibaca sekali per elemen sebelum ditimpa style inline.
        const batasAsli = new WeakMap();
        const ambilTarget = () => {
            const target = modifiers.includes("daftar") ? el.querySelector("ul") || el : el;
            if (!batasAsli.has(target)) {
                batasAsli.set(target, parseFloat(getComputedStyle(target).maxHeight) || Infinity);
                if (target === el) el.style.overflowY = "auto";
            }
            return target;
        };

        const ukur = () => {
            ambilPatokan((patokan) => {
                if (!patokan || !patokan.getBoundingClientRect) return;
                const target = ambilTarget();
                const r = patokan.getBoundingClientRect();
                const ruang = Math.max(window.innerHeight - r.bottom, r.top) - 24;
                if (modifiers.includes("lebar")) el.style.width = r.width + "px";
                target.style.maxHeight = Math.max(120, Math.min(batasAsli.get(target), ruang)) + "px";
            });
        };

        const pengamat = new ResizeObserver(() => requestAnimationFrame(ukur));
        pengamat.observe(el);
        window.addEventListener("resize", ukur);
        window.addEventListener("scroll", ukur, true);
        ukur();

        cleanup(() => {
            pengamat.disconnect();
            window.removeEventListener("resize", ukur);
            window.removeEventListener("scroll", ukur, true);
        });
    });
});

// pdf.js — LAZY-LOADED seperti TinyMCE: bundle baru diunduh saat <x-pratinjau-pdf> pertama dibuka.
// Build legacy dipilih supaya tetap jalan di browser PC lama di unit pelayanan.
let _pdfjsPromise = null;
function loadPdfjs() {
    if (!_pdfjsPromise) {
        _pdfjsPromise = (async () => {
            const pdfjs = await import("pdfjs-dist/legacy/build/pdf.mjs");
            // ?worker: Vite membundel worker jadi .js — berkas .mjs bisa ditolak browser
            // bila web server belum memetakan MIME .mjs ke JavaScript.
            const { default: PdfjsWorker } = await import(
                "pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker"
            );
            pdfjs.GlobalWorkerOptions.workerPort = new PdfjsWorker();
            // Satu PDFWorker bersama, dikirim eksplisit ke getDocument({ worker }): dengan begitu
            // loadingTask.destroy() hanya membuang dokumennya, worker-nya tetap hidup. Tanpa ini
            // destroy ikut mencabut worker dari cache, dan PDF yang dibuka sesaat sesudah menutup
            // yang lain bisa gagal "worker is being destroyed".
            const worker = new pdfjs.PDFWorker({ port: pdfjs.GlobalWorkerOptions.workerPort });
            return { pdfjs, worker };
        })();
    }
    return _pdfjsPromise;
}

// Alpine.data factory untuk <x-pratinjau-pdf> — zoom, rotasi, cetak, unduh.
// src: data URI base64 (modal EMR) atau URL route files.show (Riwayat Upload).
document.addEventListener("alpine:init", () => {
    window.Alpine.data("pratinjauPdf", ({ src, namaFile = "hasil-penunjang.pdf" }) => {
        // Di luar state reaktif: proxy Alpine merusak objek pdf.js (field privat).
        let dokumen = null;
        // pdf.js 6: destroy() ada di loading task (hasil getDocument), BUKAN di PDFDocumentProxy —
        // memanggil dokumen.destroy() melempar "destroy is not a function" saat modal ditutup.
        let tugasMuat = null;
        let isiPdf = null;
        let urutanRender = 0;
        let tugasRender = [];

        return {
            zoom: 1,
            rotasi: 0,
            jumlahHalaman: 0,
            memuat: true,
            galat: "",

            async init() {
                try {
                    isiPdf = await this.ambilIsi();
                    const { pdfjs, worker } = await loadPdfjs();
                    // slice(): buffer dipindah ke worker; salinan asli tetap untuk cetak/unduh.
                    tugasMuat = pdfjs.getDocument({ data: isiPdf.slice(), worker });
                    dokumen = await tugasMuat.promise;
                    this.jumlahHalaman = dokumen.numPages;
                    await this.render();
                } catch (e) {
                    this.galat = "File PDF tidak dapat ditampilkan.";
                    console.error("[pratinjauPdf]", e);
                } finally {
                    this.memuat = false;
                }
            },

            destroy() {
                tugasRender.forEach((tugas) => tugas.cancel());
                tugasMuat?.destroy().catch(() => {});
                tugasMuat = null;
                dokumen = null;
            },

            async ambilIsi() {
                if (src.startsWith("data:")) {
                    const biner = atob(src.slice(src.indexOf(",") + 1));
                    const hasil = new Uint8Array(biner.length);
                    for (let i = 0; i < biner.length; i++) hasil[i] = biner.charCodeAt(i);
                    return hasil;
                }
                const respon = await fetch(src, { credentials: "same-origin" });
                if (!respon.ok) throw new Error("HTTP " + respon.status);
                return new Uint8Array(await respon.arrayBuffer());
            },

            async render() {
                if (!dokumen) return;
                const urutan = ++urutanRender;
                tugasRender.forEach((tugas) => tugas.cancel());
                tugasRender = [];

                const wadah = this.$refs.halaman;
                const lebarArea = Math.max(this.$refs.area.clientWidth - 32, 200);
                const rasioPiksel = window.devicePixelRatio || 1;
                const kanvasList = [];

                for (let nomor = 1; nomor <= dokumen.numPages; nomor++) {
                    const halaman = await dokumen.getPage(nomor);
                    if (urutan !== urutanRender) return;

                    const ukuranAsli = halaman.getViewport({ scale: 1, rotation: this.rotasi });
                    const skala = (lebarArea / ukuranAsli.width) * this.zoom;
                    const viewport = halaman.getViewport({ scale: skala, rotation: this.rotasi });

                    const kanvas = document.createElement("canvas");
                    kanvas.width = Math.floor(viewport.width * rasioPiksel);
                    kanvas.height = Math.floor(viewport.height * rasioPiksel);
                    kanvas.style.width = Math.floor(viewport.width) + "px";
                    kanvas.style.height = Math.floor(viewport.height) + "px";
                    kanvas.style.display = "block";
                    kanvas.style.margin = "0 auto 16px";
                    kanvas.style.background = "#fff";
                    kanvas.style.boxShadow = "0 1px 3px rgba(0,0,0,.25)";
                    kanvasList.push(kanvas);

                    const tugas = halaman.render({
                        canvasContext: kanvas.getContext("2d"),
                        viewport,
                        transform: rasioPiksel !== 1 ? [rasioPiksel, 0, 0, rasioPiksel, 0, 0] : null,
                    });
                    tugasRender.push(tugas);
                    // Halaman pertama tampil secepatnya; sisanya menyusul.
                    if (nomor === 1) wadah.replaceChildren(kanvas);
                    else wadah.appendChild(kanvas);
                    try {
                        await tugas.promise;
                    } catch (e) {
                        if (e?.name === "RenderingCancelledException") return;
                        throw e;
                    }
                }
            },

            perbesar() {
                this.zoom = Math.min(+(this.zoom + 0.25).toFixed(2), 5);
                this.render();
            },
            perkecil() {
                this.zoom = Math.max(+(this.zoom - 0.25).toFixed(2), 0.25);
                this.render();
            },
            putar(derajat) {
                this.rotasi = (this.rotasi + derajat + 360) % 360;
                this.render();
            },
            pasLebar() {
                this.zoom = 1;
                this.render();
            },

            urlBlob() {
                return URL.createObjectURL(new Blob([isiPdf], { type: "application/pdf" }));
            },
            cetak() {
                if (!isiPdf) return;
                const url = this.urlBlob();
                const bingkai = document.createElement("iframe");
                bingkai.style.cssText = "position:fixed;width:0;height:0;border:0;right:0;bottom:0";
                bingkai.src = url;
                bingkai.onload = () => {
                    bingkai.contentWindow.focus();
                    bingkai.contentWindow.print();
                };
                document.body.appendChild(bingkai);
                setTimeout(() => {
                    bingkai.remove();
                    URL.revokeObjectURL(url);
                }, 60000);
            },
            unduh() {
                if (!isiPdf) return;
                const url = this.urlBlob();
                const tautan = document.createElement("a");
                tautan.href = url;
                tautan.download = namaFile;
                tautan.click();
                setTimeout(() => URL.revokeObjectURL(url), 10000);
            },
        };
    });
});
