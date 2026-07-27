<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

/**
 * A record in the remote tracker. `id` is what the API is addressed with;
 * `key` is the human label a person recognizes (ENG-42, LP-17) when the
 * provider has one, and `url` deep-links into the tool's UI.
 */
final readonly class RemoteRef
{
    public function __construct(
        public string $id,
        public ?string $key = null,
        public ?string $url = null,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromState(array $state): ?self
    {
        $id = trim((string) ($state['id'] ?? ''));

        if ($id === '') {
            return null;
        }

        $key = trim((string) ($state['key'] ?? ''));
        $url = trim((string) ($state['url'] ?? ''));

        return new self($id, $key === '' ? null : $key, $url === '' ? null : $url);
    }

    /**
     * @return array<string, string>
     */
    public function toState(): array
    {
        return array_filter([
            'id' => $this->id,
            'key' => $this->key,
            'url' => $this->url,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    public function label(): string
    {
        return $this->key ?? $this->id;
    }
}
