<?php

namespace App\DTO;

use App\DTO\CargoItemDTO;

readonly class ParsedMessageDTO
{
    /**
     * @param CargoItemDTO[] $items
     */
    public function __construct(
        public string $date,       
        public string $origin,     
        public array $items,        
        public ?string $safetyNote  
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'origin' => $this->origin,
            'items' => array_map(fn(CargoItemDTO $item) => $item->toArray(), $this->items),
            'safetyNote' => $this->safetyNote,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}