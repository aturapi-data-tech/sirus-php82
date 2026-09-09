#!/usr/bin/env python3
"""
Pembangkit app/Support/Keuangan/JurnalCabang.php dari DDL view TKVIEW_ACCOUNTS.

    python3 database/sql/tools/gen-jurnal-cabang.py      (jalankan dari root repo)

Jalankan ulang setiap kali DDL view (database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql)
berubah, lalu commit hasilnya. Katalog ini dipakai App\Support\Keuangan\Jurnal (Buku Besar)
dan SaldoKas (Cek Saldo Kas) untuk membaca jurnal LANGSUNG dari tabel transaksi tanpa view.
"""
import re, json, sys, os
os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", ".."))
src = open("database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql", encoding="utf-8").read()
# ambil badan setelah "AS ("
body = src[src.index("AS\n(")+4:]
# buang komentar -- (di luar string literal)
def strip_comments(t):
    out=[]; i=0; instr=False
    while i < len(t):
        c=t[i]
        if c=="'" : instr = not instr; out.append(c); i+=1; continue
        if not instr and t.startswith("--", i):
            j=t.find("\n", i); i = len(t) if j<0 else j; continue
        out.append(c); i+=1
    return "".join(out)
body = strip_comments(body)
# buang kurung penutup terakhir
body = body.rstrip().rstrip(";").rstrip()
assert body.endswith(")"), body[-50:]
body = body[:-1]
# pecah per union all di depth 0
def split_depth0(t, sep_re):
    parts=[]; depth=0; instr=False; last=0; i=0
    while i < len(t):
        c=t[i]
        if c=="'": instr = not instr
        elif not instr:
            if c=="(": depth+=1
            elif c==")": depth-=1
            elif depth==0:
                m = sep_re.match(t, i)
                if m:
                    parts.append(t[last:i]); last=m.end(); i=m.end(); continue
        i+=1
    parts.append(t[last:]); return parts
branches = [b.strip() for b in split_depth0(body, re.compile(r"union\s+all", re.I)) if b.strip()]
print("cabang:", len(branches), file=sys.stderr)
rows=[]
for n,b in enumerate(branches):
    assert re.match(r"select\s", b, re.I), b[:100]
    # cari FROM di depth 0
    depth=0; instr=False; pos=None
    for i,c in enumerate(b):
        if c=="'": instr = not instr
        elif not instr:
            if c=="(": depth+=1
            elif c==")": depth-=1
            elif depth==0 and re.match(r"\bfrom\b", b[i:], re.I) and not b[i-1].isalnum():
                pos=i; break
    assert pos, b[:200]
    sel, rest = b[6:pos], b[pos+4:]
    cols = [c.strip() for c in split_depth0(sel, re.compile(r","))]
    assert len(cols)==7, (n, len(cols), b[:300])
    # from vs where di depth 0
    mw = None; depth=0; instr=False
    for i,c in enumerate(rest):
        if c=="'": instr = not instr
        elif not instr:
            if c=="(": depth+=1
            elif c==")": depth-=1
            elif depth==0 and re.match(r"\bwhere\b", rest[i:], re.I) and (i==0 or not rest[i-1].isalnum()):
                mw=i; break
    frm = rest if mw is None else rest[:mw]; whr = "" if mw is None else re.sub(r"^\s*where\s+", "", rest[mw:], flags=re.I)
    # alias inline di ujung ekspresi (")akun", ")totalRCV", "nvl(x,0)total_ppn") dibuang —
    # nama kolom hasil ditentukan Jurnal, bukan alias asal di view
    cols = [re.sub(r"\)\s*[A-Za-z_]\w*\s*$", ")", c) for c in cols]
    def conf(c):
        mm = re.fullmatch(r"\(\s*select\s+(?:z\.)?acc_id\s+from\s+TKACC_CONFACCTXNS(?:\s+z)?\s+where\s+(?:z\.)?conf_id\s*=\s*'([A-Z0-9]+)'\s*\)", c.strip(), re.I)
        return ("conf:"+mm.group(1)) if mm else c.strip()
    rows.append(dict(no=n, name=" ".join(cols[0].split()), acc=conf(cols[1]), accK=conf(cols[2]), shift=" ".join(cols[3].split()), date=" ".join(cols[4].split()), d=" ".join(cols[5].split()), k=" ".join(cols[6].split()), frm=" ".join(frm.split()), whr=" ".join(whr.split())))

def q(s): return "'"+s.replace("\\","\\\\").replace("'","\\'")+"'"
out=[]
out.append("<?php\n\nnamespace App\\Support\\Keuangan;\n\n/**\n * KATALOG CABANG JURNAL — DIBANGKITKAN OTOMATIS, JANGAN DIEDIT MANUAL.\n *\n * Sumber : database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql (definisi TKVIEW_ACCOUNTS).\n * Pembangkit: database/sql/tools/gen-jurnal-cabang.py  (jalankan ulang setiap DDL view berubah).\n *\n * Tiap entri = satu cabang UNION ALL view, kolom persis urutan view:\n *   name  : ekspresi TXN_NAME      acc : TXN_ACC      accK : TXN_ACC_K\n *   shift : ekspresi SHIFT         date: TXN_DATE     d/k  : TXN_D / TXN_K\n *   from  : klausa FROM            where: klausa WHERE (boleh kosong)\n * Ekspresi akun 'conf:XXX' = akun konfigurasi tkacc_confacctxns.conf_id = XXX;\n * selain itu kolom akun tabel sumber (a.acc_id, acc_id_kas, ...).\n */\nfinal class JurnalCabang\n{\n    public static function semua(): array\n    {\n        return [")
for r in rows:
    out.append(f"            // #{r['no']}\n            ['name' => {q(r['name'])}, 'acc' => {q(r['acc'])}, 'accK' => {q(r['accK'])}, 'shift' => {q(r['shift'])}, 'date' => {q(r['date'])}, 'd' => {q(r['d'])}, 'k' => {q(r['k'])}, 'from' => {q(r['frm'])}, 'where' => {q(r['whr'])}],")
out.append("        ];\n    }\n}\n")
open("app/Support/Keuangan/JurnalCabang.php","w",encoding="utf-8").write("\n".join(out))
print("tulis app/Support/Keuangan/JurnalCabang.php:", len(rows), "cabang")
accs=set(); 
for r in rows: accs.add(r["acc"]); accs.add(r["accK"])
print("ekspresi akun distinct:"); [print("  ",a) for a in sorted(accs)]
print("ekspresi shift distinct:", sorted(set(r["shift"] for r in rows)))
print("ekspresi date distinct:", sorted(set(r["date"] for r in rows)))
