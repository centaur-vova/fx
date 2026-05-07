<?php

declare(strict_types=1);

namespace App\Service\Task\Processor;

use App\Contract\Task\Processor;
use Psr\Container\ContainerInterface;

final readonly class ProcessorFactory
{
    public const string MODE_OBSERVATION = 'observation';
    public const string MODE_STRESS = 'stress';

    public function __construct(private ContainerInterface $container)
    {
    }

    public function get(string $mode): Processor
    {
        $class = match($mode) {
            self::MODE_OBSERVATION => PrecisionProcessor::class,
            default => HighLoadProcessor::class,
        };

        /** @var Processor $processor */
        $processor = $this->container->get($class);

        return $processor;
    }
}
