# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com),
and this project adheres to [Semantic Versioning](https://semver.org).

## - 2026-02-05
### Added
- Core asynchronous engine based on **Swoole**.
- Coroutine-based worker pool for task processing.
- Real-time task pipeline visualization (Vanilla JS + Tailwind CSS 4).
- Shared memory `ConnectionPool` using `Swoole\Table`.
- Pre-allocated semaphore system via `Swoole\Coroutine\Channel`.
- PSR-3 compliant logging system.
- Custom WebSocket protocol with support for ping/pong and channel subscriptions.
- Event-driven broadcasting compatible with standard WebSocket clients.

## - 2026-02-05

### Added
- Horizontal jitter algorithm for task markers to prevent overlapping in the pipeline zones.
- Real-time system metrics broadcasting (CPU load, Memory usage, Active connections).
- Server-side periodic timer for health monitoring.
- Integration with `vlucas/phpdotenv` for centralized environment management.
- Dedicated `Config` service for type-safe access to application settings.
- Periodic metrics broadcasting (CPU, Memory, Connections) via Swoole Timer.
- Real-time "In-Flight" task counter using `Swoole\Atomic` for cross-worker synchronization.
- Extended system metrics with live pipeline occupancy tracking.
- **SharedResourceProvider**: New layer for safe Master-process memory allocation.
- **Inter-Process Communication**: Added `onPipeMessage` handler to allow all workers to broadcast to their respective clients.
- **Lazy DI Container**: Services are now instantiated only inside workers, ensuring fresh coroutine channels and timers.
- **Accurate Monitoring**: Implemented `getrusage`-based CPU tracking (independent of system load) and `memory_get_usage` tracking.

### Fixed
- **WebSocket Session Isolation**: Fixed issue where tasks finishing in one worker couldn't notify clients connected to another.
- **Memory Corruption**: Fixed `Swoole\Table` re-initialization on worker restart by removing `create()` calls from constructors.
- **Ghost Connections**: Implemented `exists()` and `isEstablished()` checks in `MessageHub` with auto-cleanup of dead FDs.

### Changed
- Enhanced task visualization with a professional color palette (Tailwind-based).
- Implemented unique geometric primitives (circles, diamonds, polygons) to improve cognitive task differentiation.

### Optimized
- Server memory footprint stabilized at ~3.5MB (idle) and ~7.5MB (high load: 500+ tasks).

### Refactored
- Decoupled `server.php` by extracting event logic into `App\Server\EventHandler`.
- Encapsulated worker lifecycle and coroutine pool management into `TaskService::startWorker`.
- Reorganized Manual Dependency Injection flow for better maintainability and boot order.
- Implemented PHP 8.1 first-class callable syntax for all server event handlers.
- Decoupled `server.php` logic into `App\Server\EventHandler` class.
- Encapsulated worker lifecycle and task processing into `TaskService::startWorker`.
- Implemented PHP 8.1 first-class callable syntax for all server event handlers.
- Reorganized manual dependency injection flow for stable boot order.

### Improved
- Refined task distribution algorithm (jitter) for better pipeline clarity.