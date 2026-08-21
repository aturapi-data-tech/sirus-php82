# Rujukan Berbasis Kompetensi (SRBK) — Catatan Lapangan & Aturan Implementasi

Rangkuman grup WA "SATUSEHAT Rujukan X PCare X VClaim" (10 Apr – 11 Agu 2026, ±13.600 baris)
+ sample payload/response di folder export chat (`~/Downloads/Chat WhatsApp dengan SATUSEHAT Rujukan X PCare X VClaim/`).

Narasumber kunci: Septian & Hantoro (BPJS), Bofandra & Tricha (SATUSEHAT Rujukan), Panggih DK & Haidar (Kemkes).

---

## 1. Arsitektur final (keputusan resmi)

| Jalur | Mekanisme | Catatan |
|---|---|---|
| **Rawat Jalan (RJ)** | `vclaim-sisrute-rest` (BPJS) | **BPJS yang meneruskan** ServiceRequest+CarePlan ke SATUSEHAT (19/06). RS TIDAK kirim bundle FHIR sendiri. |
| **Rawat Inap & IGD** | **Langsung SATUSEHAT FHIR** (Task/CarePlan/ServiceRequest) | TIDAK perlu bikin rujukan VClaim lagi (24/06 Bofandra, 27/07 Tricha). Postman **"30. Use Case - Rujukan Pasien V30062026"**. |
| Rujukan khusus (HD, thalasemia, hemofilia, HIV) | Alur lama | Bukan SRBK. |
| Alih rawat RJ→IGD/ranap (BPJS) | `kdStatusPulang: "4"` + `rujukLanjut.khusus` | BUKAN `kodeSubSpesialis: "IGD"` (ditolak "tidak valid"). |
| Appointment | **JANGAN dikirim dulu** | Masuk use case antrian online, dibahas terpisah (23/06). |

- Piloting 4 wilayah: Kota Bandung, Kota Makassar, **Kab. Tulungagung (kita)**, Kab. Muara Enim.
- Base URL dev FKRTL: `https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest`. Staging FHIR: `https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/`.
- Auth/signature/decrypt **sama persis VClaim eksisting** (X-cons-id, X-signature HMAC, X-timestamp, user_key). Tapi **cons-id harus didaftarkan TERPISAH untuk service SISRUTE** — cons-id yang jalan di vclaim biasa tetap ditolak (`Unauthorized! You are not registered for this service!`).
- Kredensial SATUSEHAT staging untuk rujukan = **client_id/secret KHUSUS dari tim SATUSEHAT Rujukan** (japri: email login platform + org-id production) — BUKAN yang tampil di dashboard platform.
- Jebakan env dev: server **dvlp** BPJS gagal jika header `Content-Type` dikirim; production justru wajib pakai.

## 2. Alur RJ via vclaim-sisrute-rest

`GetKriteriaRujukan` → `GetFaskesRujukan` → `Rujukan/Insert` (+ `Rujukan/Delete` method **DELETE**, `Rujukan/GetSpesialistik` untuk master spesialis).

### 2.1 GetKriteriaRujukan (POST — GET ditolak "405 Method Not Allowed", terkonfirmasi live 11/08/26)
Body JSON: `kodeDiagnosa` + `kodeFaskesSatuSehat` (+ `encounter.reference` bila sudah ada). Response berisi:
- `kriteriaRujukan[]`: `{linkId, text, type: boolean|text}` — **linkId DINAMIS per ICD-10** (contoh I10: Terapi=49873, Tindakan Medis=24964, Upaya Diagnosis=55; bisa juga gabungan koma "51947,69587"). **JANGAN hardcode** — selalu ambil fresh untuk diagnosa yang sama.
- `JejaringWilayah`: group Provinsi/Kabupaten dengan `answerOption[].valueCoding` (system `sys-ids.kemkes.go.id/administrative-area`) — sumber LOV wilayah.

### 2.2 GetFaskesRujukan (POST)
- Body: `kodeFaskesSatuSehat`, `kodeSubSpesialis`/`kodeSpesialis`, `kodeSarana`, `kodeDiagnosa`, `estimasiRujuk` (**dd-mm-yyyy**!), `kriteriaRujukan.item[]`, `codeJejaringWilayah`, `encounter.reference` (`Encounter/<uuid>`).
- **Validasi ketat sejak 02–03/07: isi TEPAT SATU kriteria** (dulu boleh >1). Teks wajib persis `Terapi` / `Tindakan Medis` / `Upaya Diagnosis`. Tindakan Medis = `valueString` kode **ICD-9-CM valid & sesuai diagnosa** — pilihan ICD-9 menentukan kandidat (match kompetensi RS); tampilkan kompetensi di UI.
- Response kandidat: extension `providerAtribute` per faskes → `distance` (km), `estimated-time` (menit), `strata`, **`bpjs-code` (bisa string `"null"` = non-BPJS, jangan dipakai untuk rujukan BPJS)**, `kemkes-code`; ranap juga ada **info ketersediaan tempat tidur**. Response **tanpa `output`/kandidat = memang tidak ada kandidat** (bukan selalu error).

### 2.3 Rujukan/Insert (POST)
- Wrapper `request.t_rujukan{...}` + node `satuSehatRujukan{...}` (lihat sample `insert_rujukan.json` di folder chat).
- **`ppkDirujuk` (BPJS) dan `kdppkSatuSehatTujuanRujukan` (SATUSEHAT) WAJIB RS yang sama** — ambil pasangan `bpjs-code`+`kemkes-code` dari kandidat. Mismatch → 400 `Satu Sehat Tujuan Rujukan tidak sesuai dengan PPK Dirujuk`.
- **Insert TIDAK memvalidasi tujuan ∈ hasil kandidat** — SIMRS wajib membatasi sendiri pilihan tujuan ke list kandidat.
- Tanggal `tglRujukan`/`tglRencanaKunjungan`: **yyyy-mm-dd** (beda dengan estimasiRujuk!). Estimasi boleh hari ini.
- **Sukses = ada identifier `http://sys-ids.kemkes.go.id/referral-number-satusehat`** di ServiceRequest response (plus `referral-number-pcare`/BPJS = no rujukan BPJS). Tidak ada identifier itu = GAGAL walau resource terbentuk. **Nomor wajib tersimpan di DB** (tampil di UI opsional — syarat UAT).
- 1 CarePlan = 1 ServiceRequest = 1 nomor rujukan. Jangan tembak beberapa RS; penerima punya 15 menit sebelum sistem menyarankan pindah kandidat.

### 2.4 Format field krusial
- **ICD-10 wajib kode rinci 4-karakter** (`A02.0`); kode induk 3-karakter (`A02`) DITOLAK → LOV diagnosa harus memaksa pilih kode anak; fallback `.9` boleh tapi jangan kebanyakan. (Awas: master kita punya 288 icdx kembar — lihat skill diagnosa-flow.)
- `kodeFaskesSatuSehat` = **kode numerik 9-digit production** (kita: `100027469`), bukan UUID, bukan org-id staging, dan harus konsisten dengan cons-id (faskes yang sama).
- Kode wilayah tanpa titik: `3504` bukan `35.04`.
- `authoredOn` dkk = string ISO, bukan objek.
- **`encounter.reference` beda bentuk antar endpoint — jangan "dirapikan" jadi seragam:**
  - `GetFaskesRujukan` → **dengan** prefix: `"Encounter/<uuid>"`
  - `Rujukan/Insert` (`satuSehatRujukan.encounter.reference`) → **UUID polos**, tanpa prefix

  Menulis prefix di Insert menghasilkan `error in parsing references Encounter/Encounter/<uuid>`
  — BPJS menambahkan prefiksnya sendiri. Terkonfirmasi dua kali: sample lapangan 09/07/26
  dan kasus grup 20/08/26 yang gagal berulang sampai prefiksnya dibuang.
- **Field `display` DILARANG memuat tag HTML** (teguran SATUSEHAT 20/08/26 atas kiriman
  `"display": "RS TK. IV 03.07.03 SARININGSIH<br><b>(0135R026)</b>"`). Kalau nama faskes
  dirakit untuk tampilan layar, rakit versi bersihnya untuk payload.

## 3. Alur Ranap/IGD (langsung SATUSEHAT FHIR) — modul berikutnya

1. Task `referral-pre-request` → Task `request-referral-candidate` — contained QuestionnaireResponse **Q100** (kriteria; IGD = 5 pertanyaan GAWAT DARURAT linkId 000001–000005, tanpa validasi ICD) + **Q101** (jejaring wilayah, `valueCoding` WAJIB — `valueString` menghasilkan 0 kandidat); input: primary-diagnosis ICD-10, management procedure SNOMED (IGD `385868005`, ranap `305351004`, RJ `737492002`), Kelompok Layanan (Playbook Lampiran 4).
2. GET Task → baca `output[]` kandidat (poll; status bisa lama `requested`).
3. Bundle **Task+CarePlan** `referral-approval` per kandidat terpilih — `Task.owner` = **Organization TUJUAN** (kunci agar RS tujuan melihat rujukan masuk), `basedOn` → CarePlan, `CarePlan.author` = Practitioner perujuk (mandatory). **JANGAN meng-echo extension `providerAtribute` kandidat ke Task yang dikirim** (validator menolak `km`/strata/bpjs-code).
4. RS tujuan: GET **Task by owner** (filter `code=referral-approval-request`; hanya accepted/rejected/completed yang tampil) → PATCH accept/reject; perujuk: Get Task by requester.
5. POST ServiceRequest → nomor rujukan di `identifier`.
6. **RS TUJUAN membuat kunjungan → `Encounter.basedOn` WAJIB menunjuk ServiceRequest rujukan**
   (aturan baru 19/08/26). Tanpa itu kunjungan yang kita buat tidak pernah tersambung ke
   rujukan yang kita terima. Sudah didukung `EncounterTrait::buildBaseEncounterPayload()`
   lewat parameter opsional `serviceRequestId`; ketiga pengirim Encounter (RJ/UGD/RI)
   membacanya dari `rujukanMasuk.serviceRequestId` di JSON kunjungan. **Penulis node itu
   belum ada** — langkah "Pendaftaran Kunjungan Rujukan" sisi RS tujuan belum dibangun,
   jadi untuk sekarang field ini selalu kosong dan `basedOn` tidak ikut terkirim.
- **`Task.identifier.value` WAJIB UNIK SETIAP POST (termasuk retry!)** — reuse = response tanpa `contained`/`output` yang menyesatkan, atau `Found duplicate: Task`. Ini akar kasus paling sering di grup.
- Org-id di token = org-id di resource; jangan campur token prod/staging.

### 3.1 Sisi FASKES TUJUAN — persetujuan/penolakan tugas rujukan (kotak masuk)

Sudah diimplementasikan: `/rujukan/masuk` (`pages::transaksi.rujukan.rujukan-masuk.*`),
method sisi penerima ada di `SatuSehatRujukanTrait` §7. Arah sebaliknya — memantau rujukan
yang KITA kirim sampai dijawab — ada di `/rujukan/keluar`
(`pages::transaksi.rujukan.rujukan-keluar.*`), dua tab: Ranap & Gawat Darurat dari
`rujukanTaskByRequester()`, Rawat Jalan dari DB lokal karena jalur BPJS tak membentuk Task.
Penamaan berkas memakai ARAH (`rujukan-masuk` / `rujukan-keluar`), bukan aktivitas
(persetujuan/pemantauan), supaya sisi mana yang dimaksud terbaca dari nama. Postman: *Contoh Rujukan → 02. Rawat Inap /
03. Rawat Darurat → 03. Pengiriman Tugas Rujukan → Faskes Rujukan - Persetujuan/Penolakan Tugas Rujukan*.
Rawat Jalan **tidak punya langkah ini** (BPJS yang mengorkestrasi).

```
GET   {base}/Task?owner=<org kita>&code=referral-approval-request&_include=Task:based-on
PATCH {base}/Task/<id>          Content-Type: application/json-patch+json
[ {"op":"replace","path":"/status","value":"completed"},
  {"op":"add","path":"/output","value":[{
      "type":{"coding":[{"system":"http://terminology.kemkes.go.id",
                         "code":"response-referral-task","display":"Response referral task"}],
              "text":"Respon atas Task Rujukan"},
      "valueCoding":{"system":"http://hl7.org/fhir/task-status",
                     "code":"accepted",     // atau "rejected"
                     "display":"Accepted"}}]} ]
```

- **`_include=Task:based-on` itu wajib secara praktis**: Task sendiri tidak memuat nama pasien,
  keterangan klinis, maupun layanan yang diminta — semuanya di CarePlan. Tanpa include, kotak
  masuk cuma berisi UUID.
- **Jalur (Ranap vs IGD) dibaca dari `CarePlan.category`** — itu satu-satunya beda antara bundle
  Rawat Inap dan Rawat Darurat di Postman V30062026:
  | Jalur | Coding kategori |
  |---|---|
  | Rawat Inap | `http://snomed.info/sct` · `736353004` · *Inpatient care plan* |
  | Gawat Darurat | `http://terminology.kemkes.go.id` · `TK000068` · *Emergency care plan* |
  Kalau perujuk mengirim kategori yang salah, RS tujuan akan menyortirnya ke antrean yang keliru.
- Nama RS perujuk **tidak ikut** di `Task.requester` (hanya reference) → ambil dari
  `GET Organization/<id>`, cache; jangan cache kegagalan.
- Sudah dijawab = `Task.output[].valueCoding.code` berisi `accepted`/`rejected`. Perujuk boleh
  membatalkan lebih dulu (`status: cancelled`) → jangan tawarkan tombol jawab.
- Sisi perujuk membaca keputusan lewat `GET Task?code=referral-approval-request&requester=<org perujuk>`.
  **Parameter `encounter` sah sebagai filter tambahan** (konfirmasi tim SATUSEHAT 14/08/26) —
  tidak perlu menyapu seluruh Task RS untuk memantau satu kunjungan.
- Alternatif PUT Task utuh juga ada di Postman, tapi PATCH yang dipakai: lebih pendek dan tidak
  berisiko menimpa field yang tidak kita kirim.
- Sesudah *accepted*, perujuk melanjutkan ke ServiceRequest; **pendaftaran kunjungan pasien rujukan
  di sisi RS tujuan** (Postman "04. Pengiriman Rujukan → Faskes Rujukan - Pendaftaran Kunjungan
  Rujukan") masih langkah terpisah yang belum dibangun.

## 4. Katalog error → penanganan di SIMRS

| Pesan | Arti sebenarnya | Aksi |
|---|---|---|
| `Unauthorized! You are not registered for this service!` | Cons-id belum didaftarkan untuk service SISRUTE (atau expired) | Ajukan aktivasi via BPJS (spreadsheet/KC) |
| `Index was out of range...` (500) | Mapping faskes BPJS↔SATUSEHAT belum ada, atau `kodeFaskesSatuSehat` salah (UUID/kosong) | Verifikasi kode 9-digit; lapor untuk dimapping |
| `Response API Satu Sehat tidak mengandung Kriteria/Faskes Rujukan` (500) | Multi-penyebab: gangguan upstream / ICD-10 induk / wilayah belum termapping / org belum terdaftar / linkId salah | Cek ICD-10 4-char dulu, lalu retry; kalau massal = upstream |
| Kandidat kosong tanpa error | Memang tidak ada kandidat (diagnosa dinilai bisa ditangani sendiri, mis. Z37.0) | Tampilkan "tidak ada kandidat", bukan error |
| `linkId ... tidak valid, linkId valid: ...` | Kriteria basi/hardcode | Re-fetch GetKriteriaRujukan |
| `hanya boleh mengisi salah satu dari Terapi...` | >1 kriteria terisi | UI paksa tepat 1 |
| `PPK ... tidak ditemukan di pemetaan` / `Tujuan Rujukan tidak sesuai dengan PPK` (400) | Pasangan kode BPJS↔SATUSEHAT tidak konsisten / RS belum termapping | Pakai pasangan dari kandidat; lapor mapping |
| `Gagal mendapatkan nomor Rujukan Satu Sehat` (400) | Upstream SATUSEHAT gagal menerbitkan nomor (kambuhan: Jul–Agu 2026) | Simpan payload+response mentah, retry nanti; TIDAK ada workaround klien |
| `Value was either too large or too small for a Decimal` (500) | Bug sisi BPJS/SATUSEHAT (gel. 11/08/26) | Tunggu perbaikan |
| `noSep tidak ditemukan` (400) | Sinkronisasi SEP dev | Cek SEP, coba ulang |
| `dokter tidak valid` di `postKunjungan` (13/08/26) | Bukan soal `kdDokterSatuSehat` — **kode dokter BPJS** di faskes tsb belum ada/salah | Lengkapi pemetaan dokter BPJS, bukan hanya IHS Practitioner |
| `Found duplicate: Task (20002)` | identifier di-reuse | Generate UUID baru tiap POST |
| 429 `Rate limit quota violation` | Kuota staging habis | Hemat panggilan; lapor minta perpanjang |
| `upstream connect error` / timeout / HTML | Infra BPJS down | Retry-later; jangan blokir EMR |
| **Error identik di ≥2 endpoint berbeda** | Hampir pasti gangguan jaringan SATUSEHAT | Tampilkan hint "gangguan pusat", jangan debug payload |
| `No consent available for CarePlan/<id>` di kotak masuk (HTTP **200**) | **Normal — detail klinis baru terbuka setelah permintaan DISETUJUI** (dikonfirmasi 21/08/26, §4.1). Task `cancelled` tidak pernah terbuka | Tampilkan barisnya, tandai "belum terbuka — menunggu persetujuan"; JANGAN sebut cacat platform atau menyalahkan perujuk |
| Validasi menolak elemen yang JELAS ADA di payload (`Element not found: CarePlan.description`, `Reference is mandatory: Encounter.subject`), pesannya berubah-ubah tiap kirim (20/08/26) | Migrasi infra SATUSEHAT — diakui sendiri: *"mmg di sisi kami sdg ada migrasi di sisi infra"*. Kena bundle maupun single resource, **intermittent** | Kirim ulang payload yang sama; jangan mengubah payload yang sebetulnya benar |
| `error in parsing references Encounter/Encounter/<uuid>` (400) | Prefix `Encounter/` ditulis dua kali — di `Rujukan/Insert` field `satuSehatRujukan.encounter.reference` harus **UUID polos** | Lihat §2.4; BPJS yang menambahkan prefiksnya |

### 4.1 Kotak masuk buta karena consent — SUDAH BERUBAH per 21/08/26

> **KOREKSI 21/08/26 — CarePlan kini TERBACA.** Probe ulang dengan token kita sendiri
> atas keempat rujukan masuk yang ada menunjukkan pola yang jelas:
>
> | Task | status | CarePlan |
> |---|---|---|
> | `f32d6a60` (UGD 19/08) | `completed` | **terbaca penuh** |
> | `28ca2a68` (Ranap 17/08) | `completed` | **terbaca penuh** — dulu diblokir |
> | `b9da0896` | `cancelled` | `No consent available` |
> | `6b7d4dc9` | `cancelled` | `No consent available` |
>
> Task `28ca2a68` adalah kasus yang 17/08 dipakai menyimpulkan "cacat platform" — kini
> terbaca. Isinya lengkap: `title`, `description` (alasan rujukan), `category` (jalur:
> `736353004` Inpatient / `TK000068` Emergency + `3457005` Patient referral), `encounter`,
> `author.display` (nama dokter perujuk), dan `activity[].detail.code` (layanan diminta,
> mis. "Spesialis - Jantung dan Pembuluh Darah").
>
> **Penyebabnya PERSETUJUAN, dan ini perilaku by design** — dikonfirmasi user 21/08/26,
> sejalan dengan kesaksian grup 20/08/26: *"harus di approve dulu baru careplan bisa
> terbaca di faskes tujuan"* dan *"coba accept dulu nanti saya proses rujukan, baru
> careplan bisa dilihat"*. Jadi `No consent available` **bukan cacat platform** melainkan
> keadaan normal selama permintaan belum dijawab. Task `cancelled` tidak pernah terbuka.
>
> Konsekuensi klinis yang tetap harus disadari: **keputusan menyetujui/menolak memang
> diambil tanpa data klinis.** Satu peserta grup mempersoalkan ini 20/08 (*"seharusnya RS
> yang dirujuk bisa melihat kode diagnosa dan alasan rujukan sebelum di approve"*) — jadi
> ini isu desain yang masih terbuka di sisi Kemkes, bukan sesuatu yang bisa kita akali.
>
> **Yang TIDAK berubah bahkan sesudah disetujui: nama pasien tetap tidak tersedia.** `Patient/<ihs>` masih cangkang
> (`active`, `id`, `identifier`, `meta`), `name` null, NIK `################`. `CarePlan.subject`
> pun hanya `reference` tanpa `display`. Jadi kolom nama pasien di kotak masuk memang akan
> tetap kosong — tampilkan nomor IHS, jangan biarkan melompong.
>
> Kode `rujukanParsePermintaanMasuk()` sudah memungut semua field itu sejak awal, jadi layar
> `/rujukan/masuk` akan terisi sendiri tanpa perubahan kode.

Catatan di bawah ini adalah keadaan 15–17/08/26, disimpan sebagai riwayat penyelidikan.

Rujukan masuk sering datang **tanpa data klinis**: `_include=Task:based-on` membalas
`OperationOutcome` bertuliskan `No consent available for CarePlan/<id>`, HTTP tetap 200.
Karena nama pasien, layanan yang diminta, jalur, dan keterangan klinis **hanya ada di
CarePlan** (tidak pernah di Task), kolom-kolom itu kosong berjamaah.

Yang sudah dibuktikan langsung dengan token kita sendiri (dua kasus: Task `6b7d4dc9` 15/08/26
dan Task `28ca2a68` 17/08/26, keduanya dari perujuk `Organization/100024122`):

- **Bukan soal payload, bukan soal urutan.** Hipotesis "baru terbaca setelah di-approve"
  gugur: CarePlan demo yang tak pernah kita sentuh justru terbaca penuh. Hipotesis "hanya
  owner yang boleh baca" juga gugur.
- **Tidak bisa diakali dari sumber lain.** `Patient/<ihs>` terbaca 200 tapi hanya cangkang
  (`active`, `id`, `identifier`, `meta`) — tanpa `name`/`gender`/`birthDate`, dan NIK-nya
  ikut di-mask. `Encounter` & `Condition` rujukan itu sama-sama disensor.
- **Consent-nya memang tidak ada.** Pada kasus 17/08, pasien punya **718** Consent (harus
  ikut `link.next`; `Bundle.total` per halaman = ukuran halaman, bukan jumlah sebenarnya).
  Hanya 2 bertipe CarePlan, dan keduanya menunjuk CarePlan lain, untuk organisasi lain,
  dengan `period` yang sudah habis. Nol consent untuk CarePlan yang masuk ke kita.
- Consent diterbitkan **otomatis oleh platform**, bukan oleh pengirim — tidak ada request
  Consent di koleksi Postman resmi, jadi saat kita merujuk keluar pun tak ada yang perlu
  disiapkan. Vendor lain melaporkan hal sama sejak 08/07/26.

**Implikasi desain (sudah diterapkan di `/rujukan/masuk`):** CarePlan belum terbaca → baris
tetap muncul, ditandai "belum terbuka — menunggu persetujuan" (atau "(dibatalkan perujuk)"
bila Task `cancelled`) + peringatan bahwa keputusan diambil tanpa data klinis — dengan
kalimat yang menegaskan itu perilaku normal, BUKAN gangguan dan bukan kelalaian perujuk.
Di layar rujukan KELUAR kalimatnya berbeda ("detail tidak terbaca"): di sana kita perujuknya,
CarePlan itu buatan kita sendiri, jadi "menunggu persetujuan" tidak berlaku. **Task tersensor tidak menyisakan baris sama sekali**, jadi
jumlahnya dimunculkan sebagai spanduk — tanpa itu, permintaan yang hilang tak akan pernah
diketahui siapa pun padahal perujuk menunggu. Bedakan kalimatnya dari "perujuk tidak
mengisi": tindak lanjutnya beda.

## 4.2 Cakupan UAT FKTL v1.0 vs implementasi kita

Dokumen *Skenario UAT Uji Coba SRBK (FKTL) ver 1.0* menguji **Rawat Jalan saja** — empat
kelompok endpoint: Kriteria Rujukan, Faskes Rujukan, Post Rujukan, Delete Rujukan. Jalur
Ranap/IGD FHIR langsung TIDAK diuji di dokumen ini.

| TC | Skenario | Di kita |
|---|---|---|
| TC01 | Kemampuan layanan berdasarkan diagnosa | `sisrute_get_kriteria_rujukan()` — tombol "Ambil Kriteria" |
| TC02 | Daftar faskes (diagnosa, estimasi tgl, kriteria, spesialis, wilayah) + **pencarian procedure ICD-9** bila kriteria "Tindakan Medis" | `sisrute_get_faskes_rujukan()`; ICD-9 kini lewat `lov.procedure` (dulu kotak ketik bebas) |
| TC03 | No. kartu BPJS, ID pasien SATUSEHAT & encounter reference = pasien yang SAMA | `pasienTidakCocok()` dipanggil sebelum Insert |
| TC04 | Rujukan RJ terbit No VClaim + No SATUSEHAT (tipe penuh) | `sisrute_insert_rujukan()`; `tipeRujukan` dipatok `'0'`, sukses diverifikasi lewat `noRujukanSatuSehat` |
| TC05 | Hapus kunjungan | `sisrute_delete_rujukan()` — tombol "Batalkan Rujukan" |

**TC01 tidak akan PASSED sebelum BPJS memetakan PPK `0184R006` ↔ SATUSEHAT `100027469` di
dev** (`GetKriteriaRujukan` masih 500 `Object reference not set`), dan TC02–TC05 semuanya
bergantung pada TC01.

Catatan TC03: ketiga data itu memang diturunkan dari satu kunjungan (`nomorSep()`,
`encounterUuid()`, `patientUuid()` semuanya berpangkal pada `rjNo`/`regNo` yang sama), jadi
secara struktur mustahil beda pasien. Yang dijaga `pasienTidakCocok()` adalah keadaan yang
tetap mungkin: node JSON tersalin dari kunjungan lain, atau `encounterId` basi setelah
pembetulan manual. Pemeriksaannya dua lapis — kartu BPJS dibandingkan lokal (master pasien
vs `sep.reqSep...noKartu`), lalu satu `GET Encounter/<id>` memastikan `subject` benar-benar
IHS pasien ini. **Gagal memeriksa ≠ tidak cocok**: kalau SATUSEHAT sedang gangguan,
pengiriman tetap diteruskan — menolak layanan karena pusat down bukan keputusan yang sah.

## 5. Prinsip desain untuk rebuild kita

1. **Outage = kondisi normal.** `timeout(8)->connectTimeout(3)` + try/catch semua call; pesan ramah + tombol retry; state form persist (pola JSON node) supaya retry tanpa isi ulang; JANGAN blokir simpan EMR.
2. **Simpan payload & response mentah** tiap call (node JSON) — admin selalu minta bukti untuk Issue Tracker, dan jadi audit.
3. **Kriteria selalu fresh** dari GetKriteriaRujukan (linkId dinamis); UI radio "tepat satu kriteria"; Tindakan Medis = LOV ICD-9-CM.
4. **Pilihan tujuan dikunci ke list kandidat** (Insert tidak memvalidasi); simpan pasangan bpjs-code+kemkes-code, distance, strata; filter bpjs-code `"null"`.
5. **LOV diagnosa memaksa ICD-10 4-karakter.**
6. **Nomor rujukan SATUSEHAT + BPJS wajib tersimpan di DB** (syarat UAT); verifikasi keberadaan identifier sebelum menyatakan sukses.
7. Dua format tanggal berbeda dalam satu alur (dd-mm-yyyy vs yyyy-mm-dd) — helper terpusat.
8. UAT: ajukan ke Kantor Cabang BPJS; hasil upload ke s.kemkes.go.id/UATRME-SSR; syarat = terimplementasi di sistem (backend+frontend), bukan cuma Postman.

## 6. Referensi

- Export chat terbaru (s/d **14/08/26**): `~/Downloads/Chat WhatsApp dengan SATUSEHAT Rujukan X PCare X VClaim(1)/`.
  Kemkes menjalankan *Check Point Progres Modul Rujukan RME* daring 14/08/26 + spreadsheet progres
  pengembangan per faskes — pantau undangan berikutnya di grup.
- Folder export chat lama + lampiran: `~/Downloads/Chat WhatsApp dengan SATUSEHAT Rujukan X PCare X VClaim/` — berisi **Postman collection V30062026**, **Playbook Rujukan Pasien (RJ/RI/IGD)**, **Skenario UAT SRBK FKTL ver 1.0**, sample payload/response JSON, Surat Himbauan.
- Postman publik: folder "04 Pengiriman Rujukan" (satusehat-public); playbook online: satusehat.kemkes.go.id/platform/docs/id/interoperability/rujukan/
- Terminologi: clinical-speciality & practitioner-speciality (gsheet Kemkes); Kelompok Layanan per ICD-10 (Playbook Lampiran 4).
