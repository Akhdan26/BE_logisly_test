<?php

namespace App\DTO;

readonly class CargoItemDTO
{
  /**
   * @param string[] $destinations 
   */
  public function __construct(
    public array $destinations,
    public int $volumeCbm,
    public int $unitCount,
    public ?string $poDate,
    public ?string $notes
  ) {}

  public function toArray(): array
  {
    return [
      'destinations' => $this->destinations,
      'volumeCbm' => $this->volumeCbm,
      'unitCount' => $this->unitCount,
      'poDate' => $this->poDate,
      'notes' => $this->notes,
    ];
  }
}
