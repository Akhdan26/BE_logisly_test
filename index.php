<?php

require_once 'vendor/autoload.php';

$dateParser = new \App\Parsers\DateParser();
$cargoParser = new \App\Parsers\CargoParser($dateParser);
$service = new \App\MessageParserService($dateParser, $cargoParser);

$rawMessage = "
*Dear Team Transporter*
*Remind Order*
*Planning Loading*
*Rabu, 23 Oktober 2024*
*Origin KCS Karawang*
Csa Cikupa + Rajeg 45 Cbm 1 Unit (Gudang Bayur)
*Pastikan Driver memakai...*
";

$dto = $service->parse($rawMessage);
echo $dto->toJson();
