<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\MessageParserService;
use App\Parsers\DateParser;
use App\Parsers\CargoParser;

class MessageParserTest extends TestCase
{
    private MessageParserService $service;

    protected function setUp(): void
    {
        $dateParser = new DateParser();
        $cargoParser = new CargoParser($dateParser);
        $this->service = new MessageParserService($dateParser, $cargoParser);
    }

    /** @test */
    public function it_can_parse_example_1_with_standard_format()
    {
        $message = "
            *Dear Team Transporter*
            *Remind Order*
            *Planning Loading*
            *Rabu, 23 Oktober 2024*
            *Origin KCS Karawang*
            Csa Cikupa + Rajeg 45 Cbm 1 Unit (Gudang Bayur)
            Tuj Pekalongan 46 Cbm *2 Unit*
            Csa Rajeg 47 Cbm 1 Unit
            *Pastikan Driver memakai...*
        ";

        $result = $this->service->parse($message);

        $this->assertEquals('2024-10-23', $result->date);
        $this->assertEquals('KCS Karawang', $result->origin);
        $this->assertCount(3, $result->items);
        $this->assertEquals(['Csa Cikupa', 'Rajeg'], $result->items[0]->destinations);
    }

    /** @test */
    public function it_can_parse_example_2_order_baru()
    {
        $message = "
            *Order Baru*
            *Senin, 28 Oktober 2024*
            *Origin KCS Karawang*
            Sample Koneksi Benoa 2 Cbm 1 Unit
            Sample Shopee Logos 17 Cbm 1 Unit
            TSM Purwakarta+Dlj Karawang 10 Cbm 1 Unit
            TSM Indramayu 38 Cbm 1 Unit
        ";

        $result = $this->service->parse($message);

        $this->assertEquals('2024-10-28', $result->date);
        $this->assertCount(4, $result->items);
        $this->assertEquals(['TSM Purwakarta', 'Dlj Karawang'], $result->items[2]->destinations);
    }

    /** @test */
    public function it_can_parse_example_3_with_abbreviated_month_and_many_items()
    {
        $message = "
            *Kamis, 20 Feb 2025*
            *Origin KCS Karawang*
            *Csa Cengkareng 47 Cbm *4 Unit* *Urgent*
            Tuj Yogyakarta + Udn Purwokerto 42 Cbm 1 Unit.
        ";

        $result = $this->service->parse($message);

        $this->assertEquals('2025-02-20', $result->date);
        $this->assertGreaterThan(0, count($result->items));
        $this->assertEquals(47, $result->items[0]->volumeCbm);
    }

    /** @test */
    public function it_can_parse_example_4_with_po_dates()
    {
        $message = "
            *Selasa, 08 Oktober 2024*
            *Origin KCS Karawang*
            Lotte Pasar Rebo 1 Cbm 1 Unit *PO 11 Okt 2024*
            Duta Intidaya 8 Cbm 1 Unit *PO 11 Okt 2024*
        ";

        $result = $this->service->parse($message);

        $this->assertEquals('2024-10-08', $result->date);
        $this->assertEquals('2024-10-11', $result->items[0]->poDate);
        $this->assertEquals('Lotte Pasar Rebo', $result->items[0]->destinations[0]);
    }
}