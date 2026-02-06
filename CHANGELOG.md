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
- **Terminal UI**: Fixed-height scrollable log panel with auto-scroll and line limiting (last 40 entries).
- **Responsive Layout**: Aligned control panel and terminal log using CSS Grid and `items-stretch`.
- **Dynamic Styling**: Integrated status-based color coding in terminal logs (INFO/LOCK/PROC).
- **Session Isolation**: Tasks are now visually isolated per WebSocket client, while still sharing global server resources (CPU, Memory, Semaphores).
- **Graceful Shutdown**: Implemented `onWorkerStop` lifecycle hook with active task draining.
- **Configurable Timeout**: Added `GRACEFUL_SHUTDOWN_TIMEOUT_SEC` to prevent zombie processes.
- **Semaphore Lifecycle**: Added `close()` method to `TaskSemaphore` interface to unblock waiting coroutines.
- **DTO Layer**: Introduced `TaskStatusUpdate` DTO with static factories and immutable state to standardize backend-to-frontend communication.
- **Event Constants**: Centralized WebSocket event names within DTOs to eliminate "magic strings".
- **Global State Sync**: Frontend now synchronizes task positions based on server-side `mc` values, enabling consistent views across multiple clients.
- **Resource Guarding (Backend)**: Implemented `QUEUE_CAPACITY` checks and `QueueFullException` to protect VPS resources from task overflow.
- **Queue Monitoring Logic**: Added `getQueueStats()` and `QueueStatsDTO` to the service layer for future observability.
- **Log Level Filtering**: Implemented PSR-3 level-based filtering in `StdoutLogger`. Supports all standard levels (debug, info, warning, error, etc.).
- **Environment Configuration**: Added `LOG_LEVEL` to `.env` to control logging verbosity without code changes.
- **7-Stage Lifecycle Visualization**: Implemented full support for task states (`QUEUED`, `CHECK_LOCK`, `LOCK_ACQUIRED`, `LOCK_FAILED`, `PROCESSING`, `PROGRESS`, `COMPLETED`) with unique color coding in the terminal log.
- **Resource Guarding UI**: Added a "Queue Load" metric to the dashboard to monitor `Swoole\Channel` saturation in real-time.
- **Visual LOD (Level of Detail)**: Tasks now scale from 40px blocks to 4px particles based on system load to ensure 60FPS UI performance.
- **DTO Architecture Refactoring**:
    - Standardized naming conventions across the `App\DTO` namespace.
    - Renamed `QueueStatsDTO` to `QueueStats` to eliminate redundant suffixes (Clean Code approach).
    - Synchronized file names with PSR-4 namespaces for full Linux/Production compatibility.
- **Improved Logging**:
    - Optimized `addLog` performance to handle high-frequency updates (2500+ events/sec).
    - Added "Pulse" animation for critical failures (Lock Collisions).
    - Reduced visual noise by truncating UUIDs and cleaning up status formatting.

### Fixed
- **WebSocket Session Isolation**: Fixed issue where tasks finishing in one worker couldn't notify clients connected to another.
- **Memory Corruption**: Fixed `Swoole\Table` re-initialization on worker restart by removing `create()` calls from constructors.
- **Ghost Connections**: Implemented `exists()` and `isEstablished()` checks in `MessageHub` with auto-cleanup of dead FDs.
- **UI Layout Instability**: Prevented terminal log from expanding infinitely and breaking the page flow.
- **Log Performance**: Implemented DOM node recycling to prevent browser lag during high-frequency broadcasting.
- **Task Counter Leak**: Wrapped task processing in `try-finally` blocks to ensure atomic counter decrements even on failure.
- **Coroutine Deadlocks**: Optimized shutdown sequence to close all internal channels (Main Queue and Semaphores).
- **Clean Console**: Eliminated debug noise in production mode.
- **DI Container**: Refactored `StdoutLogger` instantiation to decouple it from the `Config` object, improving architecture.
- **UI Desync**: Resolved the "ghost tasks" issue by strictly following server-side MC (Max Concurrency) metadata in the frontend state.
- **Z-Index Conflicts**: Fixed visibility of zone labels and background grids in Dark Mode.

### Changed
- Enhanced task visualization with a professional color palette (Tailwind-based).
- Implemented unique geometric primitives (circles, diamonds, polygons) to improve cognitive task differentiation.
- **Messaging Architecture**: Migrated from loose arrays to a structured `TaskStatusUpdate` DTO.
- **UI Logic**: Updated `app.js` to prioritize server-sent `mc` values, fixing the visualization bug between different browser windows.

### Optimized
- Server memory footprint stabilized at ~3.5MB (idle) and ~7.5MB (high load: 500+ tasks).
- **Adaptive Level of Detail (LOD)**: Implemented dynamic UI scaling (24px to 4px) based on total active tasks to maintain 60FPS.
- **Smart DOM Recycling**: Automated cleanup of completed tasks with a 5-second TTL to prevent DOM pollution and memory leaks.
- **High-Load Visualizer**: Confirmed stability with 2,600+ concurrent processes at <1% CPU and ~2.7MB RAM.
- **Massive Concurrency**: Successfully tested with **5,000+** concurrent tasks on a single node.
- **Resource Efficiency**: Maintained stable operation at **~50MB RAM** and **<7% CPU** under extreme stress.
- **Backpressure Implementation**: Added `QUEUE_CAPACITY` guardrails and real-time queue depth monitoring.
- **Visual Stability**: Frontend LOD (Level of Detail) scaling confirmed to handle thousands of particles without browser lag.

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
- **Broadcast Logic**: Refactored `WsEventBroadcaster` to accept DTO objects, ensuring data integrity before transmission.
- **TaskService**: Integrated `try-finally` blocks and `isShuttingDown` checks for bulletproof graceful exits.
- **Environment Configuration**: Decoupled semaphore limits and lock timeouts into `.env` for production tuning.

### Removed
- Static "Connecting..." status label to simplify UI.

## - 2026-02-06
### Added
- **Kernel-based Architecture**: Centralized application lifecycle management via `App\Server\Kernel`.
- **PHP Version Requirement**: Bumped minimum version to **8.4+** to support modern engine features.
- **Package Identity**: Renamed project to `shmandalf/atomic-flow`.
- **Dependency Alignment**: Updated `composer.json` to resolve conflicts with PHPUnit 12 on PHP 8.4.
- **Dockerization**: Multi-stage `Dockerfile` based on PHP 8.4 Alpine for optimized image size.
- **Swoole Support**: Automatic installation and configuration of Swoole extension in the container.
- **Build Pipeline**: Integrated Composer and NPM build steps into the container assembly.
- **Orchestration**: `docker-compose.yaml` for simplified service management and networking.
- **Optimization**: `.dockerignore` file to prevent heavy local directories from bloating the build context.
- **UI/UX**: Custom SVG/ICO favicon with "AF" branding (green on black terminal style).
- **Branding**: Updated application title to `FAST.Atomic.Flow`.
- **Global Concurrency**: Switched to `GlobalSharedSemaphore` powered by `Swoole\Atomic`. Concurrency limits are now strictly enforced across all worker processes.
- **Race Condition Fix**: Implemented a non-blocking spin-lock with `cmpset` (CAS) to prevent task overflows during high-frequency bursts.
- **Shared Infrastructure**: Added a pool of pre-allocated atomic counters in shared memory, managed via the `shared.semaphores.atomics` container key.
- **Network**: New configuration parameter `SOCKET_BUFFER_SIZE_MB` in `.env` to control Swoole reactor output buffers.

### Changed
- Refactored monolithic `server.php` into a modular, decoupled architecture.
- **PHP Requirement**: Bumped to **8.4+**.
- **Rebranding**: Package renamed to `shmandalf/atomic-flow`.
- **Configuration**: Switched to a system environment-first configuration model. The application now prioritizes environment variables provided by the container runtime over local `.env` files.
- **Docker**: Optimized `Dockerfile` by removing the redundant `.env` file creation step, ensuring image immutability.
- **Infrastructure**: Added `env_file` support in `docker-compose.yaml` for seamless local development.

### Fixed
- **Configuration**: Standardized environment variables to use `SERVER_HOST` and `SERVER_PORT`.
- **WebSocket**: Replaced hardcoded connection strings with `window.location.host` for seamless switching between local dev (port 9501) and production (Nginx proxy).
- **Swoole Infrastructure**: Refined `enable_static_handler` logic to prevent conflicts with API routes.
- **Routing**: Fixed a bug where the Router would intercept WebSocket (`/ws`) handshakes and static files with JSON 404 responses.
- **Performance**: Switched static file delivery to use Swoole's native `sendfile` via `document_root`.
- **Config**: Added a safety check for `.env` file existence and readability, preventing crashes in environments where the file is not present (e.g., production containers).
- **Metrics**: Corrected `queue.usage` reporting in `metrics.update` event to show global task count instead of local worker stats.
- **Validation**: Fixed task batch creation logic to accurately check global capacity using atomic counters, preventing race conditions and overflow.
- **Network**: Resolved `socket output buffer overflow` (ERRNO 1009) by increasing `socket_buffer_size` in the Swoole reactor configuration.
- **Infrastructure**: Eliminated hardcoded network limits in the Kernel, enabling environment-specific performance tuning.

### Improved
- **Unit Test Suite**: Reached stable coverage for core business logic, DTOs, and infrastructure components.
- **Testing Infrastructure**: Added `phpunit.xml` and automated test scripts via Composer.
- **Mocking Strategy**: Implemented clean Unit tests using Stubs and Mocks for high-concurrency services.
- **Code Navigation**: Integrated `{@see}` PHPDoc annotations to link infrastructure resource allocation with service implementations.
- **Architecture**: Refined the PSR-container structure to clearly distinguish between local and shared (IPC) resources.

## - 2026-02-07
### Added
- **Canvas Rendering**: Introduced a dedicated 2D Canvas engine for the task pipeline, enabling smooth 60 FPS visualization for 10,000+ concurrent tasks.
- **Physics & Animation**: Added linear interpolation (Lerp) and time-based delta normalization to ensure consistent animation speed regardless of frame rate.
- **High-Density HUD**: Implemented an automated Level of Detail (LOD) system that switches to pixel-perfect "Star Dust" mode during high-load bursts.

### Improved
- **Frontend Performance**: Drastically reduced CPU and GPU overhead by eliminating thousands of DOM nodes.
- **UI Scalability**: Refined task jitter and zone positioning to provide better visual density across the pipeline.