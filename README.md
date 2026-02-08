# FAST-ATOMIC-FLOW

<p align="center">
<a href="https://github.com/shmandalf/atomic-flow/actions"><img src="https://github.com/shmandalf/atomic-flow/actions/workflows/ci.yaml/badge.svg" alt="Tests"></a><a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
</p>

---

🌐 **Live Demo:** **[https://fast.af.l3373.xyz](https://fast.af.l3373.xyz/)**

A high-performance, real-time task orchestration engine powered by PHP 8.4 and Swoole 6.0. This system is designed to handle massive parallel workloads with sub-millisecond telemetry and strict memory management.

## Technical Specifications

- Runtime: PHP 8.4 (ZTS optional but not required)
- Engine: Swoole 6.0.0+ (Coroutine support enabled)
- Concurrency: Multi-process Task Worker Pool
- Communication: Unix Socket IPC / WebSockets (JSON)
- State Management: Swoole Atomic / Shared Memory Tables
- Memory Footprint: ~3.8MB idle baseline

## Core Architecture

### Hybrid Execution Model
The engine leverages a dual-layer processing strategy to maximize throughput:
1. **Worker Layer (L1)**: Handles incoming HTTP/WebSocket traffic and manages connection persistence.
2. **Task Layer (L2)**: A dedicated pool of Task Workers for CPU-bound logic, utilizing coroutine-based internal retries to prevent process starvation.

### Stateless Service Design
All core services, including `TaskService` and `SystemMonitor`, are designed to be stateless. Process affinity is managed via dynamic context injection, allowing the engine to scale horizontally across CPU cores without shared-state bottlenecks.

### Shared Memory Semaphores
Concurrency control is implemented using custom semaphores backed by Swoole Atomic primitives. This ensures that `max_concurrent` limits are enforced across the entire process pool with zero race conditions.

## Installation

### Prerequisites
- PHP 8.4 or higher
- Swoole Extension 6.0.0+
- Composer 2.x

### Standard Setup
1. Clone the repository and enter the directory.
2. Execute dependency installation:
   ```bash
   cp .env.example .env
   make install && make build
   make run
   ```

## Environment Configuration

The system is configured via environment variables. Below is the comprehensive list of parameters governing the reactor's behavior.

### Server Infrastructure
| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| SERVER_HOST | string | 0.0.0.0 | Bind address. 0.0.0.0 is recommended for Docker environments. |
| SERVER_PORT | int | 9501 | Port for HTTP and WebSocket traffic. |
| SERVER_WORKER_NUM | int | 6 | Total number of Swoole worker processes. |
| SERVER_DISPATCH_MODE| int | 2 | 2 (Fixed/FD-based) is enforced for WebSocket connection stability. |
| SOCKET_BUFFER_SIZE_MB| int | 64 | TCP/UDP socket buffer allocation in Megabytes. |

### Logging
| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| LOG_LEVEL | string | warning | Internal PSR-3 logger threshold (debug, info, warning, error). |

### Shared Memory & Queues
| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| WS_TABLE_SIZE | int | 1024 | Size of the `Swoole\Table` for connection tracking (must be power of 2). |
| QUEUE_CAPACITY | int | 10000 | Global capacity of the task queue governed by the Atomic counter. |

### Task Engine Concurrency
| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| WORKER_CONCURRENCY | int | 10 | Number of concurrent coroutines per task worker. |
| TASK_SEMAPHORE_MAX_LIMIT| int| 10 | Pre-allocated shared memory slots for concurrency limits. |
| TASK_LOCK_TIMEOUT_SEC | float | 4.0 | Maximum duration to wait for a semaphore lock. |
| TASK_RETRY_DELAY_SEC | float | 5.0 | Delay (Co::sleep) before retrying task after a lock failure. |
| TASK_MAX_RETRIES | int | 3 | Maximum number of rescheduling attempts before task failure. |

### Real-time & Monitoring
| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| METRICS_UPDATE_INTERVAL_MS| int | 1000 | Frequency of WebSocket system telemetry broadcasts. |
| GRACEFUL_SHUTDOWN_TIMEOUT_SEC| int | 5 | Max time to wait for active tasks to drain during SIGTERM. |

## System Lifecycle

### Graceful Shutdown Logic
The reactor implements a strict multi-stage shutdown protocol to prevent data loss during deployments or scaling:

1. **Interruption Signal**: Upon receiving `SIGTERM` or `SIGINT`, the Manager process instructs all workers to stop accepting incoming task injections.
2. **Consumer Drain**: Task Workers continue processing their current coroutine stack. New tasks are no longer popped from the global system queue.
3. **Atomic Validation**: The system polls the `TaskCounter` (Swoole Atomic) until it reaches zero.
4. **Process Exit**: Once the counter is cleared or the `GRACEFUL_SHUTDOWN_TIMEOUT_SEC` is reached, processes terminate cleanly.

### IPC & Message Hub
Inter-process communication is handled via `pipeMessage`. When a task update or system metric is generated in a specific worker, the `MessageHub` broadcasts the payload across the entire worker pool to ensure all connected WebSocket clients receive real-time updates regardless of their process affinity.

## Worker Scoped Containers

To maintain strict process isolation and prevent "zombie" state inheritance from the Master/Manager processes, the engine utilizes a Worker-Scoped Container pattern:

1. **Pre-fork Boot**: Global infrastructure (Config, Atomic Primitives, Shared Tables) is initialized in the Master process.
2. **Post-fork Initialization**: Within the `onWorkerStart` event, the DI Container resets its internal instance cache.
3. **Live Server Injection**: The active `Swoole\Server` instance for the specific process is injected into the container.
4. **Stateless Services**: High-level services (e.g., `TaskService`, `EventHandler`) are re-instantiated within each worker. This ensures that `$server->worker_id` and other process-level telemetry remain 100% accurate without cross-process leakage.

## Monitoring & Quality Gate

### Real-Time HUD
- **Worker Heatmap**: GPU-accelerated visualization of load distribution across the task worker pool.
- **Failure Visualization**: Distinct visual alerts for lock timeouts and exhausted retry attempts.

### Quality Assurance
The project enforces strict code quality through a pre-defined toolchain:
- **PHPStan**: Static analysis (Level 5+) for asynchronous logic validation.
- **Rector**: Automated refactoring and PHP 8.4 compatibility checks.
- **PHP-CS-Fixer**: Enforcement of project-wide coding standards.

Run `make check` to execute the full quality gate or `composer fix-all` to apply automatic fixes.

---

*Developed with a passion for high-performance backend systems.*
