<?php

namespace App\DataTransferObjects;

class ClassificationResult
{
    public function __construct(
        public readonly string $category,
        public readonly int $severity,
        public readonly ?array $severityFactors,
        public readonly int $confidence,
        public readonly array $entities,
        public readonly ?string $country,
        public readonly ?string $region,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly string $summary,
        public readonly bool $relevant = true,
        public readonly ?string $conflictContext = null,
        public readonly ?string $subcategory = null,
        public readonly ?string $titleEn = null,
        public readonly ?string $titleDe = null,
        public readonly ?string $summaryDe = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $factors = $data['severity_factors'] ?? null;
        if (is_array($factors)) {
            $factors = [
                'impact' => max(1, min(10, (int) ($factors['impact'] ?? 5))),
                'casualty' => max(1, min(10, (int) ($factors['casualty'] ?? 5))),
                'escalation' => max(1, min(10, (int) ($factors['escalation'] ?? 5))),
                'international' => max(1, min(10, (int) ($factors['international'] ?? 5))),
            ];
        }

        $relevant = $data['relevant'] ?? true;

        return new self(
            category: $data['category'] ?? 'other',
            severity: max(1, min(10, (int) ($data['severity'] ?? 5))),
            severityFactors: $factors,
            confidence: max(1, min(10, (int) ($data['confidence'] ?? 1))),
            entities: $data['entities'] ?? [],
            country: $data['country'] ?? null,
            region: $data['region'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            summary: $data['summary'] ?? '',
            relevant: (bool) $relevant,
            conflictContext: $data['conflict_context'] ?? null,
            subcategory: $data['subcategory'] ?? null,
            titleEn: $data['title_en'] ?? null,
            titleDe: $data['title_de'] ?? null,
            summaryDe: $data['summary_de'] ?? null,
        );
    }
}
