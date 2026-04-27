<?php

namespace App\Parsers;

use App\Parsers\DateParser;

class CargoParser
{
  private DateParser $dateParser;

  public function __construct(DateParser $dateParser)
  {
    $this->dateParser = $dateParser;
  }

  public function parseLine(string $line): array
  {
    // Clean formatting markers (* or _)
    $line = trim(str_replace(['*', '_'], '', $line));

    // Pattern Regex Utama:
    // 1. Destinasi (bisa multiple dipisah +)
    // 2. Volume (Cbm)
    // 3. Unit
    // 4. Notes & PO Date (Optional)

    $pattern = '/^(?P<destinations>.+?)\s+(?P<volume>\d+)\s*Cbm\s+(?P<unit>\d+)\s*Unit(?P<remainder>.*)$/i';

    if (preg_match($pattern, $line, $matches)) {
      $data = [
        'destinations' => array_map('trim', explode('+', $matches['destinations'])),
        'volumeCbm' => (int) $matches['volume'],
        'unitCount' => (int) $matches['unit'],
        'poDate' => null,
        'notes' => null,
      ];

      $remainder = trim($matches['remainder']);

      // Ekstrak PO Date jika ada (Misal, "PO Tgl 28 Okt 24" atau "PO 11 Okt 2024")
      if (preg_match('/PO\s+(?:Tgl\s+)?(.+)/i', $remainder, $poMatches)) {
        $data['poDate'] = $this->dateParser->parse($poMatches[1]);
        // Bersihkan PO dari remainder untuk sisa catatan
        $remainder = trim(preg_replace('/PO\s+(?:Tgl\s+)?.+/i', '', $remainder));
      }

      // Sisanya dianggap notes (biasanya dalam kurung atau teks tambahan)
      if (!empty($remainder)) {
        $data['notes'] = trim($remainder, ' .()');
      }

      return $data;
    }

    return [];
  }
}
