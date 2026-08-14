---
name: rujukan-kompetensi
description: Model integrasi & FAQ Rujukan Berbasis Kompetensi (SRBK/SISRUTE) — arsitektur RJ via vclaim-sisrute-rest vs RI/IGD langsung FHIR SATUSEHAT, urutan endpoint, aturan payload (kriteria tepat satu, linkId dinamis, ICD-10 4-karakter), dan katalog error→penanganan. WAJIB dibaca sebelum menulis/mengubah kode rujukan kompetensi (SisruteTrait, komponen EMR rujukan) atau saat call SISRUTE ditolak/error.
---

# Rujukan Berbasis Kompetensi (SRBK)

Rujukan lengkap + katalog error penuh: **`docs/rujukan-kompetensi.md`** (hasil studi 13.6k baris
chat grup resmi BPJS×Kemkes Apr–Agu 2026). Skill ini = model integrasi + FAQ tersering.

## 1. Model integrasi (JANGAN tertukar jalur)

| Jalur pasien | Mekanisme | Yang kirim ke SATUSEHAT |
|---|---|---|
| **RJ (rawat jalan)** | BPJS `vclaim-sisrute-rest` | **BPJS** (kita TIDAK kirim bundle FHIR) |
| **RI & UGD** | Langsung SATUSEHAT FHIR: Task → CarePlan → ServiceRequest | **Kita** (tanpa rujukan VClaim sama sekali) |
| Alih rawat RJ→UGD/RI | `kdStatusPulang:"4"` + `rujukLanjut.khusus` | BUKAN `kodeSubSpesialis:"IGD"` (ditolak) |
| Rujukan khusus (HD, thalasemia, dll) | Alur VClaim lama | Bukan SRBK |

Urutan endpoint RJ: `GetKriteriaRujukan` → `GetFaskesRujukan` → `Rujukan/Insert`
(+ `Rujukan/Delete` pakai HTTP method **DELETE**, `GetSpesialistik` untuk master).

**Dua sisi, jangan tertukar.** Sisi PERUJUK (kirim) ada di panel EMR RI/UGD/RJ; sisi
FASKES TUJUAN (kotak masuk) ada di layar tersendiri `/rujukan/persetujuan`:

| Peran | Endpoint | Ada di |
|---|---|---|
| Perujuk kirim tugas | POST Bundle Task+CarePlan `referral-approval` | `rujukanBundleApproval()` |
| **Tujuan baca kotak masuk** | `GET Task?owner=<org kita>&code=referral-approval-request&_include=Task:based-on` | `rujukanTaskMasuk()` |
| **Tujuan menjawab** | `PATCH Task/<id>` json-patch: status `completed` + output `accepted`/`rejected` | `rujukanTaskRespon()` |
| Perujuk baca keputusan | `GET Task?code=referral-approval-request&requester=<org perujuk>` (+`&encounter=` sah sbg filter) | `rujukanTaskByRequester()` |

Env: `SISRUTE_URL/CONS_ID/SECRET_KEY/USER_KEY/KDPPK` (dev: CID 8334, faskes 0184R006
MADINAH JST). Signature/header = pola VClaim persis; cons-id SISRUTE terdaftar TERPISAH
dari cons-id vclaim biasa. Semua call wajib `timeout(8)->connectTimeout(3)` + try/catch
(lihat prinsip di docs §5): outage upstream adalah kondisi normal.

## 2. Aturan payload yang paling sering bikin salah

1. **Kriteria rujukan: isi TEPAT SATU** dari Terapi / Tindakan Medis / Upaya Diagnosis
   (validasi ketat sejak Jul 2026). Teks harus persis; Tindakan Medis = `valueString`
   berisi ICD-9-CM valid & sesuai diagnosa (menentukan kandidat!).
2. **`linkId` kriteria DINAMIS per ICD-10** — selalu fetch ulang dari GetKriteriaRujukan,
   jangan hardcode/cache lintas diagnosa. Bisa berbentuk gabungan koma ("51947,69587").
3. **ICD-10 wajib kode rinci 4-karakter** (`A02.0`); kode induk (`A02`) ditolak →
   LOV diagnosa harus memaksa kode anak. Awas 288 icdx kembar (skill diagnosa-flow).
4. **Dua format tanggal dalam satu alur**: `estimasiRujuk` = `dd-mm-yyyy`;
   `tglRujukan`/`tglRencanaKunjungan` = `yyyy-mm-dd`. Estimasi boleh hari ini.
5. `kodeFaskesSatuSehat` = kode numerik 9-digit **production** (bukan UUID, bukan staging),
   konsisten dengan cons-id.
6. **`ppkDirujuk` (BPJS) ↔ `kdppkSatuSehatTujuanRujukan` (SATUSEHAT) wajib RS yang sama** —
   ambil pasangan `bpjs-code`+`kemkes-code` dari extension `providerAtribute` kandidat.
   `bpjs-code` bisa string `"null"` (RS non-BPJS) → jangan tawarkan untuk rujukan BPJS.
7. **Insert TIDAK memvalidasi tujuan ∈ kandidat** — UI wajib mengunci pilihan ke hasil
   GetFaskesRujukan.
8. **Sukses = ada identifier `referral-number-satusehat`** di response; tidak ada = GAGAL
   walau resource terbentuk. Nomor SATUSEHAT + BPJS **wajib tersimpan di DB** (syarat UAT).
9. Jalur FHIR langsung (RI/UGD): `Task.identifier.value` **unik SETIAP POST termasuk retry**
   (reuse = response tanpa `contained`/`output`, menyesatkan); `Task.owner` = Organization
   **tujuan**; jangan meng-echo extension `providerAtribute` ke Task yang dikirim;
   jejaring wilayah pakai `valueCoding` (bukan `valueString`); kode wilayah tanpa titik.
10. Simpan **payload + response mentah** tiap call di node JSON — bukti wajib saat lapor
    Issue Tracker, sekaligus audit.
11. **`CarePlan.category` menentukan LAYANAN yang diminta**, dan itu satu-satunya beda bundle
    ranap vs gawat darurat: ranap `736353004` *Inpatient care plan* (SNOMED), IGD `TK000068`
    *Emergency care plan* (terminology.kemkes). Salah kategori = permintaan masuk ke antrean
    yang keliru di RS tujuan. Nama pasien, keterangan klinis, dan layanan **hanya ada di
    CarePlan**, bukan di Task — makanya kotak masuk wajib `_include=Task:based-on`.
12. Nama RS perujuk tidak ikut di `Task.requester` → `GET Organization/<id>` + cache;
    **jangan cache kegagalan** (gangguan sesaat bisa mengosongkan kolom seharian).

## 3. FAQ / katalog error tersering

| Gejala | Penyebab sebenarnya | Aksi |
|---|---|---|
| `Unauthorized! You are not registered for this service!` | Cons-id belum didaftarkan service SISRUTE / expired | Ajukan aktivasi ke BPJS (bukan bug kode) |
| `Index was out of range...` (500) | Mapping faskes BPJS↔SATUSEHAT belum ada, atau `kodeFaskesSatuSehat` salah | Verifikasi kode 9-digit; lapor minta mapping |
| `Response API Satu Sehat tidak mengandung Kriteria/Faskes Rujukan` (500) | ICD-10 induk / wilayah belum termapping / org belum terdaftar / gangguan upstream | Cek ICD-10 4-char dulu → retry; kalau massal = upstream |
| Response kandidat tanpa `output` | Memang tidak ada kandidat (diagnosa dinilai mampu ditangani sendiri) | Tampilkan info, bukan error |
| `linkId ... tidak valid, linkId valid: ...` | Kriteria basi/hardcode | Re-fetch GetKriteriaRujukan |
| `hanya boleh mengisi salah satu dari...` | >1 kriteria terisi | UI paksa tepat satu |
| `PPK ... tidak ditemukan di pemetaan` / `Tujuan Rujukan tidak sesuai dengan PPK` | Pasangan kode BPJS↔SATUSEHAT beda RS / belum termapping | Pakai pasangan dari kandidat |
| `Gagal mendapatkan nomor Rujukan Satu Sehat` (400) | Upstream gagal menerbitkan nomor (kambuhan Jul–Agu 2026) | Simpan bukti, retry nanti; tak ada workaround klien |
| `Value ... too large or too small for a Decimal` (500) | Bug sisi pusat (gel. 11/08/26) | Tunggu perbaikan |
| `Found duplicate: Task (20002)` | identifier di-reuse | UUID baru tiap POST |
| 429 `Rate limit quota violation` | Kuota staging habis | Hemat panggilan; lapor |
| **Error identik di ≥2 endpoint** | Hampir pasti gangguan jaringan SATUSEHAT | Tampilkan hint "gangguan pusat"; JANGAN debug payload |
| `dokter tidak valid` saat `postKunjungan` | Bukan `kdDokterSatuSehat` — **kode dokter BPJS** di faskes itu belum ada | Lengkapi pemetaan dokter BPJS, bukan IHS-nya |

Sumber lampiran (Postman V30062026, Playbook, Skenario UAT, sample JSON):
`~/Downloads/Chat WhatsApp dengan SATUSEHAT Rujukan X PCare X VClaim/`
— export terbaru (s/d 14/08/26) ada di folder bersuffix `(1)`.
