<?php

declare(strict_types=1);

namespace App\Server\Http\Controller;

use App\Exception\Http\BadRequestException;
use App\Exception\Http\InternalServerErrorException;
use App\Server\Http\Attribute\RateLimit;
use App\Server\Http\Attribute\Route;
use App\Server\Http\Request\ToggleInstance;
use App\Server\Http\Response\ApiResponse;
use App\Service\Api\BalancerApi;
use Psr\Log\LoggerInterface;

class ApiController
{
    public function __construct(
        private readonly BalancerApi $balancerApi,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Mark a specific instance as alive/unalived
     */
    #[Route(method: 'POST', path: '/api/instance/toggle')]
    #[RateLimit(limiterName: 'toggle-instance')]
    public function toggleInstance(ToggleInstance $dto): ApiResponse
    {
        if (empty($dto->hash)) {
            throw new BadRequestException('Instance hash required');
        }

        try {
            if ($dto->alive) {
                if ($this->balancerApi->reviveInstance($dto->hash)) {
                    return ApiResponse::ok('API Instance successfully revived');
                }
            } else {
                if ($this->balancerApi->forceUnaliveInstance($dto->hash)) {
                    return ApiResponse::ok('API Instance successfully unalived');
                }
            }

            // Shouldn't get here
            throw new InternalServerErrorException();
        } catch (\Throwable $e) {
            $this->logger->error('Kill instance failed', ['hash' => $dto->hash, 'error' => $e->getMessage()]);
            throw new InternalServerErrorException('Internal error');
        }
    }
}
