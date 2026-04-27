<?php

namespace App;

use App\Parsers\DateParser;
use App\Parsers\CargoParser;
use App\DTO\CargoItemDTO;
use App\DTO\ParsedMessageDTO;

class MessageParserService
{
    private DateParser $dateParser;
    private CargoParser $cargoParser;

    public function __construct(DateParser $dateParser, CargoParser $cargoParser)
    {
        $this->dateParser = $dateParser;
        $this->cargoParser = $cargoParser;
    }

    public function parse(string $rawText): ParsedMessageDTO
    {
        // 1. Bersihkan formatting markers (* dan _) di seluruh teks 
        $cleanText = str_replace(['*', '_'], '', $rawText);

        // 2. Pecah menjadi baris dan hapus baris kosong
        $lines = array_filter(array_map('trim', explode("\n", $cleanText)));

        $date = '';
        $origin = '';
        $safetyNote = '';
        $cargoItems = [];

        foreach ($lines as $line) {
            // Abaikan header statis
            if (preg_match('/Dear Team Transporter|Remind Order|Order Baru|Planning Loading/i', $line)) {
                continue;
            }

            // Identifikasi Origin
            if (stripos($line, 'Origin') === 0) {
                $origin = trim(str_ireplace('Origin', '', $line));
                continue;
            }

            // Identifikasi Safety Note / Footer
            if (stripos($line, 'Pastikan Driver') === 0) {
                $safetyNote = $line;
                continue;
            }

            // Abaikan ucapan terima kasih  
            if (stripos($line, 'Terima kasih') === 0) {
                continue;
            }

            // Coba parse sebagai Cargo Line terlebih dahulu
            // (harus di atas Date check karena cargo line bisa mengandung nama bulan di PO Date)
            $itemData = $this->cargoParser->parseLine($line);
            if (!empty($itemData)) {
                $cargoItems[] = new CargoItemDTO(
                    destinations: $itemData['destinations'],
                    volumeCbm: $itemData['volumeCbm'],
                    unitCount: $itemData['unitCount'],
                    poDate: $itemData['poDate'],
                    notes: $itemData['notes']
                );
                continue;
            }

            // Coba parse sebagai tanggal (Indonesian Date)
            // Kita asumsikan baris yang mengandung nama bulan adalah tanggal
            if (preg_match('/(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember|Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)/i', $line)) {
                $parsedDate = $this->dateParser->parse($line);
                if ($parsedDate) {
                    $date = $parsedDate;
                    continue;
                }
            }
        }

        return new ParsedMessageDTO(
            date: $date,
            origin: $origin,
            items: $cargoItems,
            safetyNote: $safetyNote
        );
    }
}
