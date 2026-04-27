<?php

namespace App\Parsers;

class DateParser
{
  // Mapping bulan Indonesia ke angka
  private static $monthsMap = [
    'januari' => '01',
    'jan' => '01',
    'februari' => '02',
    'feb' => '02',
    'maret' => '03',
    'mar' => '03',
    'april' => '04',
    'apr' => '04',
    'mei' => '05',
    'juni' => '06',
    'jun' => '06',
    'juli' => '07',
    'jul' => '07',
    'agustus' => '08',
    'agu' => '08',
    'agt' => '08',
    'september' => '09',
    'sep' => '09',
    'oktober' => '10',
    'okt' => '10',
    'november' => '11',
    'nov' => '11',
    'desember' => '12',
    'des' => '12'
  ];

  // Change format tanggal Indonesia ke yyyy-mm-dd
  public static function parse($dateString): ?string
  {
    // Clean formatting markers (* or _)
    $cleanDate = trim(str_replace(['*', '_'], '', $dateString));

    // Remove nama hari 
    $cleanDate = preg_replace('/^[a-zA-Z]+,?\s+/', '', $cleanDate);

    // Gunakan Regex untuk pecah d-m-y
    // Support pemisah spasi, strip, atau slash
    if (preg_match('/(\d{1,2})[\s\-\/]([a-zA-Z0-9]+)[\s\-\/](\d{2,4})/', $cleanDate, $matches)) {
      $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
      $monthInput = strtolower($matches[2]);
      $year = $matches[3];

      // Handle year 2 digit
      if (strlen($year) === 2) {
        $year = '20' . $year;
      }

      // Handle month (numeric vs text) 
      $month = is_numeric($monthInput)
        ? str_pad($monthInput, 2, '0', STR_PAD_LEFT)
        : (self::$monthsMap[$monthInput] ?? '01');

      return "{$year}-{$month}-{$day}";
    }

    return null;
  }
}
