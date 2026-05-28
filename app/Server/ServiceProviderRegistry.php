<?php

declare(strict_types=1);

namespace App\Server;

/**
 * @implements \IteratorAggregate<int, class-string>
 */
final class ServiceProviderRegistry implements \IteratorAggregate
{
    /** @var array<int, class-string> */
    private array $providers = [];

    private function __construct(private readonly Options $options)
    {
    }

    public static function create(Options $options): self
    {
        return new self($options);
    }

    /**
     * @param class-string $providerClass
     */
    public function add(string $providerClass): self
    {
        $this->providers[] = $providerClass;
        return $this;
    }

    /** @param callable(Options): ?string $selector */
    public function addMatch(callable $selector): self
    {
        /**
         * @var class-string|null $providerClass
         */
        $providerClass = $selector($this->options);

        if ($providerClass !== null) {
            $this->providers[] = $providerClass;
        }
        return $this;
    }

    /**
     * @return \Traversable<int, class-string>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->providers);
    }

    /** @return array<string> */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
