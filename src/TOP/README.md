# Soal No. 3 — Term of Payment (TOP) Calculation

## Additional Questions & Answers

### a) Why cap POD and ePOD delays individually at 30 days before summing them, rather than capping the total penalty at 30 days?

Mencap tiap jenis delay secara individu (bukan total penalty) bertujuan untuk **fairness dan akuntabilitas** antara dua jenis keterlambatan yang berbeda:

1. **Separate accountability**: POD fisik dan ePOD digital adalah dua proses berbeda yang dihandle oleh pihak berbeda. POD melibatkan dokumen fisik yang sering terkendala logistik lapangan (tanda tangan, kurir balik). ePOD melibatkan upload digital yang bisa dilakukan dari mana saja. Mencap individual memastikan *masing-masing pihak bertanggung jawab maksimal 30 hari*, bukan berbagi kuota 30 hari dengan pihak lain.

2. **Prevents gaming**: Jika total penalty di-cap 30 hari, transporter bisa sengaja menunda POD sampai 30 hari dan berharap ePOD 0 hari, atau sebaliknya. Dengan individual cap, keterlambatan di satu sisi tidak mengurangi konsekuensi di sisi lain. Keduanya harus dipenuhi tepat waktu.

3. **Worst-case protection**: Dua keterlambatan berbeda *seharusnya* bisa diakumulasi karena mereka merepresentasikan dua kegagalan terpisah. Cap 45 di akhir mencegah abuse total, tapi cap individual memungkinkan akumulasi wajar (30+30=60) sebelum final cap.

### b) What would happen if we removed the final 45-day cap? Provide a scenario where this could be problematic.

Tanpa final cap 45 hari, TOP bisa mencapai nilai ekstrem:

**Scenario problematis:**
- Transporter dengan baseline TOP 30 hari
- POD telat 30 hari (max individual cap)
- ePOD telat 30 hari (max individual cap)
- **Total TOP = 30 + 30 + 30 = 90 hari**

Ini artinya transporter baru dibayar **3 bulan** setelah invoice. Dampaknya:

1. **Cash flow crisis**: Transporter kecil/menengah tidak bisa bertahan tanpa pembayaran selama 3 bulan.
2. **Operational disruption**: Transporter tidak bisa bayar sopir, BBM, maintenance truk.
3. **Supply chain collapse**: Jika banyak transporter keluar, rantai logistik terganggu.
4. **Legal/compliance risk**: Di Indonesia, UU No. 2/2017 tentang Jasa Konstruksi (jika relevan) dan praktik bisnis wajar mensyaratkan pembayaran dalam waktu yang reasonable.
5. **Vendor relationship damage**: Transporter akan pindah ke platform lain yang menawarkan TOP lebih pendek.

Cap 45 hari adalah **safety net bisnis** yang membatasi exposure maksimal: bahkan dengan pelanggaran maksimal di kedua sisi, transporter tetap dibayar dalam 45 hari.

### c) How would you handle a requirement to make these caps configurable (stored in database/config) instead of hardcoded constants?

Beberapa pendekatan (diimplementasikan di method `calculateWithConfig()` pada `Calculator.php`):

**1. Database table untuk thresholds:**
```sql
CREATE TABLE top_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) UNIQUE NOT NULL,
    config_value INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO top_config VALUES
(1, 'max_pod_delay', 30),
(2, 'max_epod_delay', 30),
(3, 'max_top_result', 45),
(4, 'min_delay', 0);
```

**2. Service layer dengan caching:**
```php
class TopConfigService {
    public function getMaxPodDelay(): int { /* query DB dengan Redis cache */ }
    public function getMaxEpodDelay(): int { /* ... */ }
    public function getMaxTopResult(): int { /* ... */ }
}
```

**3. Admin panel / API endpoint** untuk mengubah thresholds tanpa deploy ulang:
```
PUT /api/admin/top-config
{ "max_top_result": 60 }
```

**4. Fallback mechanism**: Jika database unreachable, fallback ke constant default untuk mencegah service outage:
```php
$maxPodDelay = $configService?->getMaxPodDelay() ?? Calculator::TS_MAX_TOP_DELAY_POD;
```

**5. Audit trail**: Setiap perubahan config di-log ke tabel `top_config_audit` untuk traceability dan compliance.

Method `calculateWithConfig()` di `Calculator.php` sudah siap menerima parameter configurable untuk skenario ini, tanpa mengubah core logic.

## Edge Cases Not Covered by Spec (already handled in implementation)

| Edge Case | Handling |
|-----------|----------|
| Negative baseline | Treated as 0 (`max(0, baseline)`) |
| Very large delays (e.g., 999) | Capped individually at 30, then final at 45 |
| Very large baseline (e.g., 100) | Capped at 45 |
| Zero across all inputs | Returns 0 |