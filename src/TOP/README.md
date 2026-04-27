# Soal No. 3 — TOP (Term of Payment) Calculator

## Additional Questions

### a) Kenapa cap POD & ePOD masing-masing 30 hari, bukan cap total penalty 30 hari?

Dua keterlambatan ini emang beda banget:

1. **Yang nanganin beda**: POD fisik urusan lapangan (tanda tangan, kurir balik ke kantor). ePOD digital bisa upload dari mana aja. Kalau total penalty di-cap 30 hari, si transporter bisa main: tunda POD 30 hari, tapi ePOD 0 — dan tetap kena max 30. Padahal dia udah bikin dua kesalahan.

2. **Nggak ada bagi-bagi kuota**: Masing-masing keterlambatan harus dipertanggungjawabkan sendiri. Kalau POD telat 30 hari DAN ePOD telat 30 hari, ya dua-duanya dihitung. Bukan berarti "oh total udah 30, yang satu gratis".

3. **Final cap 45 ngelindungin**: Setelah dijumlahkan (30+30=60), final cap 45 yang motong. Jadi tetep ada safety net, tapi tiap pihak tetep kena hitungannya.

### b) Kalau cap final 45 dihapus, apa skenario terburuknya?

Transporter dengan baseline TOP 30 hari, POD telat 30 (max), ePOD telat 30 (max):
- Total = 30 + 30 + 30 = **90 hari**. 3 bulan nunggu pembayaran.

Ini problematis banget buat transporter kecil/menengah:
- Cash flow kering — nggak bisa bayar sopir, BBM, maintenance
- Bisa keluar dari platform dan cari kerjaan lain
- Supply chain kacau kalau banyak yang cabut
- Di Indonesia juga ada aturan soal pembayaran yang wajar

Cap 45 itu safety net: worst case tetep 1.5 bulan, bukan 3 bulan.

### c) Gimana kalau caps-nya mau configurable (dari DB, nggak hardcoded)?

Di `Calculator.php` udah ada method `calculateWithConfig()` yang terima parameter. Untuk implementasi production:

**1. Simpen di database:**
```sql
CREATE TABLE top_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) UNIQUE NOT NULL,
    config_value INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO top_config VALUES
('max_pod_delay', 30),
('max_epod_delay', 30),
('max_top_result', 45);
```

**2. Service layer + cache:**
```php
class TopConfigService {
    public function getMaxPodDelay(): int { /* query DB, cache di Redis */ }
    public function getMaxEpodDelay(): int { /* ... */ }
    public function getMaxTopResult(): int { /* ... */ }
}
```

**3. Admin API buat update tanpa deploy ulang:**
```
PUT /api/admin/top-config  { "max_top_result": 60 }
```

**4. Fallback**: Kalau DB down, pake constant default biar service tetep jalan.
```php
$maxPodDelay = $configService?->getMaxPodDelay() ?? Calculator::TS_MAX_TOP_DELAY_POD;
```

**5. Audit trail**: Log tiap perubahan config buat traceability.

## Edge Cases (udah di-handle di kode)

| Case | Handling |
|------|----------|
| Negative baseline | Dianggap 0 |
| Delay gede banget (999) | Cap di 30, lalu final cap 45 |
| Baseline gede (100) | Final cap 45 |
| Semua input 0 | Output 0 |