# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com),
and this project adheres to [Semantic Versioning](https://semver.org).

## [Unreleased]

### Added
- **Reactive State Management:** Integrated [Alpine.js](https://alpinejs.dev) to manage the application state.
- **Modular JS Architecture:** Decoupled logic into `state.js` (reactive store) and `config.js` (static constants).
- **SCSS Support:** Migrated styles to SCSS with [Native Nesting](https://developer.mozilla.org) and Tailwind 4 integration.
- **Adaptive Canvas LOD:** High-performance "Star Dust" mode for 500+ active tasks.
- **Smart UI Indicators:** Dynamic color-coded metrics (Ping/CPU) and auto-reset to `--` on disconnect.
- **Reactor HUD:** Reactive shape legend with real-time highlighting based on `max_concurrent` limit.

### Changed
- **Build Pipeline:** Switched to a unified PostCSS + [esbuild](https://esbuild.github.io) workflow for lightning-fast bundling.
- **DOM Logic:** Removed legacy `document.getElementById` calls in favor of Alpine directives.
- **Injection Controls:** Buttons now automatically enter a `LOCKED` state when `queue_capacity` is reached.
- **Toasts:** Refactored notification system into a more compact, vertically optimized design.

### Fixed
- **Canvas Scaling:** Resolved coordinate drift issues using `ResizeObserver`.
- **Z-Index Hierarchy:** Fixed background zones overlapping the task particles.

## [v1.1.3] - 2026-02-11
### Added
- Git hooks for `pre-commit` (PHPStan, Tests) and `commit-msg` (Conventional Commits).
- Auto-configuration for Git hooks in `composer.json`.

## - 2026-02-11
### Added
- **Welcome Handshake**: Introduced WelcomeMessage DTO to transmit static system topology (CPU cores, worker count, queue capacity) once per connection.
- **Flat Metrics Protocol**: Unified SystemStats and TaskService data into a single flattened Metrics DTO, significantly reducing real-time frame overhead.
- **HUD Zero-State**: Implemented a complete UI reset on WebSocket disconnect, including state.tasks.clear(), innerHTML clearing for the worker heatmap, and data-default restoration for all metrics.
- **Stealth Mode (Headless)**: Integrated a Visual Engine toggle in the HUD. Disabling Canvas rendering allows the UI to monitor extreme server loads (100k+ RPS) without browser-side bottlenecks.
- **Dynamic Versioning**: System now automatically extracts the latest Git tag and bakes it into version.php during the build process.
- **Handshake Synchronization**: The engine version is now part of the initial WebSocket welcome event, ensuring the HUD always reflects the current server build.
- **Headless Versioning**: Version metadata is now available in the /health API response for automated monitoring.

### Changed
 - **DTO Architecture**: Reorganized all WebSocket-related DTOs into a dedicated App\DTO\WebSockets\Messages namespace for better domain isolation.
- **Naming Standardization**: Unified variable naming across the engine (PHP) and HUD (JS): workers -> workerNum, activeTasks -> taskNum.
- **SystemMonitor Refactoring**: Decoupled SystemMonitor from business logic; it now acts as a pure hardware/OS metrics provider.
- **TaskService Optimization**: Added getTaskNum() for high-speed atomic counter access during metrics broadcasting.
- **CI/CD Pipeline**: Updated deploy.yml to generate the version manifest before pushing to the VPS.
- **Zero-Runtime Overhead**: Replaced live git calls with a static PHP require for version retrieval, ensuring maximum performance and security.

#### Infrastructure & CI/CD
- **Docker Build-Args**: Migrated versioning to `ARG APP_VERSION`. The `version.php` manifest is now generated on-the-fly during the Docker build stage, eliminating the need for Git history or manual file copying inside the container.
- **Stateless Versioning**: Decoupled version generation from the GitHub Actions runner. Metadata is now "baked" directly into the PHP runtime as a static string.

### UI/UX & Performance
- **Payload Optimization**: Reduced the size of the high-frequency JSON stream by ~40% by offloading static data to the welcome event.
- **Browser Resource Recovery**: Fixed a memory leak where the HUD continued rendering "ghost" tasks after server shutdown.
- **UI Consistency**: Added data-default attributes to HUD elements for accurate "offline" state visualization.
- **Dark Neon UI**: Complete interface overhaul. Deep black (#0a0a0a) background paired with high-contrast toxic green accents for a cyberpunk aesthetic.
- **Injection Panel**: Re-engineered the control deck with neon sliders and status-coded batch buttons (1k/5k).
- **System Terminal**: Redesigned the log panel as a monospace console with a pulsing "Reactor Status" indicator.
- **Session Auto-Reset**: Implemented a full UI state reset (Canvas, Heatmap, Metrics) upon WebSocket disconnection to prevent stale data visualization.

## - 2026-02-10
### Added
- **Type-Safe Options DTO**: Introduced a readonly Options class to encapsulate all .env settings into a strictly typed structure.
- **Hardware Core Detection**: Integrated fidry/cpu-core-counter for reliable CPU thread detection, replacing risky shell_exec calls.
- **Neon HUD Metrics**: Added a neon-cyan Cores indicator to the metrics bar for real-time hardware visibility.
- **Typed Config Getters**: Added getInt() and getFloat() to the Config service to eliminate manual casting.

### Changed
- **Kernel & DI Isolation**: Migrated all container registrations to static fn to prevent implicit $this binding and memory leaks.
- **Instance Isolation**: Implemented a full reset of cached singletons in WorkerStart to ensure total isolation between worker processes.
- **Graceful Termination**: Replaced blocking usleep with coroutine-aware Co::sleep in WorkerStop for safer process shutdown.
- **Kernel Refactoring**: Decoupled Kernel from raw Environment state; it now only operates on the validated Options DTO.

### Fixed
- **Canvas Memory Leak**: Resolved a critical issue where the HUD continued to render "ghost" tasks after a server disconnect. The state.tasks Map is now explicitly cleared on connection loss, freeing up browser resources.

### UI/UX & Performance
- **Static Metadata Offloading**: Planned the migration of cpuCores and workerNum from the real-time stream to a one-time init handshake packet.
- **DI Performance**: Optimized container factory closures for better memory footprint during high-concurrency worker spawning.

## - 2026-02-09
### Added
- **New Branding**: Engine rebranded to **FAST.AF** (Fast Atomic Flow).
- **Interactive HUD Legend**: Implemented a Canvas overlay showing shape-to-concurrency mapping.
- **Boot Console Art**: Added high-impact ASCII branding and system status on reactor startup.
- **Engine Rules Logic**: Added technical documentation directly into the UI explaining Starvation prevention and Shared-memory limits.
- **Visual Throttling**: Added a greyed-out visual state to the log panel via `.terminal-overloaded` class to indicate high-load conditions.

### Changed
- **UI Refactoring**: Moved legend from sidebar to Pipeline HUD for optimal layout and terminal visibility.
- **Log Management**: Unified terminal behavior using `TASKS_LOG_THRESHOLD`. New log entries are now suppressed and the panel is dimmed when active tasks exceed this limit.
- **UI Synchronization**: Refactored the terminal state update to trigger within the `render` loop, ensuring the log panel is re-enabled immediately after tasks are deleted from memory.

### UI/UX & Performance
- **Terminal Load Balancing**: Introduced `TASKS_LOG_THRESHOLD` to manage terminal performance under high pressure.
- **Visual Throttling**: Added a greyed-out visual state to the log panel, synchronized with the `render` loop to ensure accurate UI feedback after task cleanup.

## - 2026-02-08
### Added
- **Infrastructure**: Introduced `Arrayable` contract in `App\Contracts\Support` to unify DTO-to-array transformations.
- **Protocol**: Implemented `WsMessage` DTO to handle standardized WebSocket communication using `event` and `data` schema.
- **Monitoring**: Added `SystemStats` DTO for low-level resource tracking and `Metrics` aggregate for high-level telemetry.
- **Observability**: Real-time RTT (Ping/Pong) tracking to measure network latency between client and VPS.
- **Architecture**: Fully migrated to **Swoole Task Workers**, enabling true process-level load balancing for CPU-bound tasks.
- **Resilience**: Implemented a retry mechanism for semaphore locks with configurable `TASK_MAX_RETRIES` and `TASK_RETRY_DELAY_SEC`.
- **Dynamic Monitoring**: Introduced a real-time **Worker Heatmap** in the HUD that automatically adapts to the number of active processes.
- **Visual Feedback**: Added distinct GPU-accelerated CSS flash animations (Green for success, Red for failure/lock-timeout).
- **Hybrid Task Engine**: Combined Swoole Task Workers with Coroutine-based retry logic for maximum throughput and dynamic concurrency control.
- **Smart Backpressure**: Implemented `TASK_MAX_RETRIES` and `TASK_RETRY_DELAY_SEC` to prevent worker starvation under heavy lock contention.

### Changed
- **Architecture**: Decoupled `SystemMonitor` from `TaskService`. Metrics composition is now handled at the `EventHandler` level.
- **Namespaces**: Reorganized all DTOs into a domain-driven structure for better maintainability and PSR compliance.
- **Kernel**: Updated `Kernel` and `WsEventBroadcaster` to support the new polymorphic DTO architecture.
- **Task Scheduling**: Optimized `DemoDelayStrategy` to provide zero-latency execution for the initial task in any batch (iteration 0).
- **UX**: Enhanced real-time feedback when creating single tasks, removing unnecessary wait times for manual injections.
- **Coding Style**: Updated PHP-CS-Fixer and Rector configurations to support **Swoole** specific syntax (e.g., preserving `Co\Channel` instead of automatic importing).
- **Rector**:
  - Disabled automatic return type declarations for **arrow functions** (`fn()`) to maintain brevity.
  - Disabled automatic name importing (`withImportNames: false`) to keep FQCN/Swoole aliases intact.
  - Added `TYPE_DECLARATION` rule set for better automated type coverage in classes and methods.
- **PHP-CS-Fixer**:
  - Forced `void` return types for methods without a return statement.
  - Disabled strict FQCN shortening to prevent breaking Swoole short-name aliases.
- **Messaging**: Refactored `TaskService` to be stateless, passing context-specific `workerId` through the pipeline.
- **Telemetry**: Switched queue usage reporting from local `Co\Channel` stats to global Swoole engine `tasking_num` metrics.
- **Lifecycle**: Optimized `WorkerStart` logic to ensure clean service initialization across Master, Manager, and Task processes.
- **Stability**: Refactored Container initialization to ensure process-level isolation in Swoole's multi-worker environment.

### Fixed
- **Type Safety**: Added strict `instanceof` guarding and JSON validation for incoming Swoole WebSocket frames.
- **Standardization**: Refactored protocol events from legacy `type` to consistent `event` naming.
- **Worker Affinity**: Added solating process-level container instances.
- **UI Stability**: Implemented fixed-width metric slots and data-attribute event delegation to prevent layout shifts.

## - 2026-02-07
### Added
- **Canvas Rendering**: Introduced a dedicated 2D Canvas engine for the task pipeline, enabling smooth 60 FPS visualization for 10,000+ concurrent tasks.
- **Physics & Animation**: Added linear interpolation (Lerp) and time-based delta normalization to ensure consistent animation speed regardless of frame rate.
- **High-Density HUD**: Implemented an automated Level of Detail (LOD) system that switches to pixel-perfect "Star Dust" mode during high-load bursts.
- **Native PHP 8.4 Support**: Fully integrated strict typing, asymmetric visibility, and `readonly` properties.
- **Rector Integration**: Automated architectural consistency and modern standards enforcement.
- **Property Hooks**: Initial implementation for cleaner DTO state management (PHP 8.4 feature).
- **Static Analysis**: Integrated **PHPStan (Level 5)** for rigorous type-safety and architectural integrity.
- **Automated Quality Control**: Added custom Composer scripts (`analyze`, `refactor`, `check-all`) for consistent development workflow.
- **Asynchronous Safety**: Implemented double-check locking patterns with static analysis suppressions for Swoole-specific coroutine race conditions.
- **Automated Quality Gate**: Integrated **GitHub Actions** CI pipeline for continuous code validation.
- **Continuous Deployment (CD)**: Automated image builds and delivery to **GHCR (GitHub Container Registry)**.
- **Remote Orchestration**: Fully automated VPS deployment via GitHub Actions and **Docker Compose**, including atomic container swaps.
- **Reliability Engineering**: Configured extended `stop_grace_period` (60s) to ensure zero-data-loss during asynchronous Swoole worker shutdowns.
- **Linting**: Introduced `lint-dry` command for code style verification without modifying files.
- **Workflow**: Added `check-all` composer script to run static analysis, linting, and rector dry-runs in a single pass.
- **Monitoring Service**: Introduced `SystemMonitor` for high-frequency resource usage tracking (CPU, Memory, Connections).
- **Data Transfer**: Implemented `Metrics` DTO for structured, type-safe monitoring updates.
- **Developer Experience**: Added `make check` command in Makefile for comprehensive project validation.
- **Resilience**: Implemented automatic WebSocket reconnection with linear backoff (3s interval).
- **UX**: Added a real-time **Reactor Status** indicator with visual pulsing for disconnected states.
- **Notifications**: Introduced a "Success Popup" for immediate visual confirmation of batch task injections.
- **Local Quality Gate**: Added **Git Hooks** support to enforce static analysis and unit testing before every commit.

### Changed
- **Frontend Performance**: Drastically reduced CPU and GPU overhead by eliminating thousands of DOM nodes.
- **UI Scalability**: Refined task jitter and zone positioning to provide better visual density across the pipeline.
- **Optimized Task Identification**: Refactored Task ID generation to a compact `bin2hex(random_bytes(4)) . time()` format.
  - *Impact*: Reduced memory footprint per task object and improved log density.
  - *Security*: Maintained high entropy (2^32 combinations per second) to prevent collisions in concurrent worker environments.
- **Core Architecture Refactoring**:
  - `Kernel` and `MessageHub` updated with **Typed Constants** for zero-overhead configuration at the Zend Engine level.
  - Hardened service isolation via `readonly` constraints on DI-injected core dependencies.
- **Naming Conventions**: Unified service naming (e.g., `hub` -> `messageHub`) to improve self-documentation and IDE discoverability.
- **Strict Type Enforcement**: Global pass of scalar and return type declarations across the entire **Fast-Atomic-Flow** engine.
- **Task Progress Reporting**: Refactored progress tracking from discrete steps to percentage-based metrics (0-100%).
  - *UX Improvement*: Enhanced readability for real-time monitoring and Canvas visualization.
- **Test Suite Refactoring**: Removed legacy format assertions to align with the new high-entropy ID generation logic.
- **Standardization**: Refactored all configuration files (`ci`, `deploy`) to use the official `.yaml` extension for improved consistency and standard compliance.
- **Unified CI/CD Pipeline**: Consolidated **PHPStan** (Level 5) and **PHPUnit** testing into a single **GitHub Actions** workflow (`ci.yaml`).
- **Task Scheduling Optimization**: Increased the jitter window in `DemoDelayStrategy` from 300ms to 3000ms.
  - *Rationale*: Improved temporal distribution of tasks to mitigate CPU spikes and enhance semaphore efficiency during massive batch processing.
- **Task Scheduling**: Refactored `DemoDelayStrategy` to use pure high-entropy jitter.
  - *Improvement*: Eliminated linear "task grouping" effect by removing iteration-based staggering.
  - *Visuals*: Enhanced Canvas visualization with more organic, non-linear task distribution.
- **Code Style**: Migrated to a stricter PHP-CS-Fixer configuration based on PSR-12.
- **Strictness**: Enforced `declare_strict_types`, `strict_comparison`, and `strict_param` across the entire codebase to prevent runtime type-related bugs.
- **Dashboard**: Redesigned the main header to include a centralized **Reactor Status** and **Queue Load** (usage vs. capacity).
- **Frontend**: Refactored the metrics handler to support the new DTO-driven WebSocket payload.
- **Terminal**: Removed legacy connection indicator from the log panel in favor of the new header status.
- **Clean Code**: Decoupled system telemetry collection from the WebSocket `EventHandler`.
- **Frontend Logic**: Optimized task creation UI by implementing event delegation via `data-count` attributes, reducing DOM selector overhead.
- **Code Reliability**: Applied explicit decimal parsing (`radix 10`) in Canvas rendering logic to ensure consistent behavior across different JS engines.
- **Stability**: Enhanced WebSocket lifecycle management by handling `onerror` and `onclose` events gracefully.
- **UI State**: Centralized reconnection attempts and popup timeouts within the global application state.
- **UI/UX Stability**: Fixed dashboard layout shifting by implementing `min-width` containers for all real-time metrics.
- **Header Refactoring**: Repositioned status and diagnostic elements to the right-hand anchor for improved visual balance.

### Security
- **Memory Safety**: Prevented accidental state mutation in long-running Swoole workers by enforcing `readonly` on shared services.
- **Type Integrity**: Leveraged PHP 8.4 native types to eliminate "Type Juggling" vulnerabilities in task processing pipelines.
- **DX**: Standardized formatting rules (single quotes, ordered imports, method spacing) to ensure clean and readable Git diffs.
- **Maintenance**: Updated file discovery rules to ignore `vendor`, `storage`, and `cache` directories.

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

