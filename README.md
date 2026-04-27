# BE Logisly Test - Soal No. 1

Message parser untuk parsing pesan teks logistik (origin, tanggal, cargo items) ke format JSON.

## Requirements

| Dependency  | Version |
|-------------|---------|
| PHP         | ≥ 8.1   |
| Composer    | ≥ 2.0   |

> **Dikembangkan & di-test dengan:** PHP 8.2.29, Composer 2.7.7, PHPUnit 11.5.55

## Setup

```bash
git clone git@github.com:Akhdan26/BE_logisly_test.git
cd BE_logisly_test
composer install
```

## Run (Soal No. 1)

```bash
php index.php
```

Output yang dihasilkan berupa JSON hasil parsing pesan logistik.

## Run Tests

```bash
vendor/bin/phpunit tests/
```

Atau dengan output verbose:

```bash
vendor/bin/phpunit tests/ --testdox
```

## Struktur Project

```
├── composer.json          # PSR-4 autoloading (App\ → src/, Tests\ → tests/)
├── index.php              # Entry point soal no. 1
├── src/
│   ├── MessageParserService.php  # Service utama — orchestrator parsing
│   ├── Parsers/
│   │   ├── DateParser.php        # Parser tanggal Indonesia (dd MMM yyyy / dd-mm-yy)
│   │   └── CargoParser.php       # Parser baris cargo (destinasi + volume + unit + PO date)
│   └── DTO/
│       ├── CargoItemDTO.php      # DTO per item cargo
│       └── ParsedMessageDTO.php  # DTO hasil parsing keseluruhan
└── tests/
    └── MessageParserTest.php     # PHPUnit test (4 test cases)