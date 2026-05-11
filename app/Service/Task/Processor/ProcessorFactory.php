<?php

declare(strict_types=1);

namespace App\Service\Task\Processor;

use App\Contract\Task\Processor;
use App\Contract\Task\TaskMode;
use Psr\Container\ContainerInterface;

final readonly class ProcessorFactory
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function get(TaskMode $mode): Processor
    {
        $class = match($mode) {
            TaskMode::OBSERVATION => PrecisionProcessor::class,
            TaskMode::STRESS => HighLoadProcessor::class,
        };

        /** @var Processor $processor */
        $processor = $this->container->get($class);

        return $processor;
    }
}
