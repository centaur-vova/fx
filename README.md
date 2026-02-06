# 🚀 Atomic Flow: High-Performance Task Engine

[![Tests](https://github.com)](https://github.com)

> **A deep dive into PHP Swoole, Coroutines, and Event-Driven Architecture.**

This project is a technical laboratory where I explore high-concurrency patterns in PHP, inspired by the Golang concurrency model. It demonstrates how to push PHP beyond the classic Request-Response (FPM) cycle into the realm of persistent, high-performance distributed systems.

---

### 🛠 Prerequisites

To run this engine, your environment must meet the following requirements:

*   **PHP:** `^8.2` (Uses Constructor Property Promotion).
*   **Swoole Extension:** `^5.0` (Required for Coroutines, Channels, and Table support).
*   **Composer:** For dependency management.
*   **Linux/macOS:** Swoole has limited support for Windows (Docker is recommended for Windows users).

---

### 📊 Performance Benchmarks (The "Flex" Section)
Testing on a single-core environment yielded the following results:
*   **Concurrent Tasks:** 2,600+ active task processes.
*   **CPU Usage:** ~0.7% - 1.2% (The server is practically idling).
*   **Memory Footprint:** ~3.0 MB total RAM (Shared memory included).
*   **Throughput:** Real-time state synchronization across multiple clients.

---

### 🛠 Tech Stack & Core Abstractions
*   **Runtime:** [Swoole PHP](https://www.swoole.co.uk) (Event-driven, Coroutines).
*   **Shared Memory:** `Swoole\Table` for ultra-fast cross-process state management.
*   **Atomic Operations:** `Swoole\Atomic` for thread-safe global metrics (Tasks, Connections).
*   **Concurrency Control:** Custom Semaphores built on `Swoole\Coroutine\Channel`.
*   **Architecture:**
    *   **Dependency Injection:** Lazy-loading container for worker isolation.
    *   **Immutable DTOs:** Wither-pattern for predictable state updates.
    *   **Named Static Constructors:** Self-documenting domain events.

---

### 🧠 What I Learned (The "Engineer's Journey")
Building this project was a path to mastering "Non-Standard PHP" concepts:
1.  **Process Isolation:** Managing data flow between Master, Manager, and Worker processes via IPC.
2.  **Coroutine Orchestration:** Balancing thousands of tasks without blocking the Event Loop.
3.  **Graceful Shutdown:** Implementing `onWorkerStop` hooks and channel draining to prevent data loss.
4.  **Adaptive Visual LOD:** Optimizing frontend rendering by scaling UI elements from 40px blocks down to 4px "star dust" particles based on real-time load.

---

### 🚦 Installation & Usage

1. **Configure Environment:**
   ```bash
   cp .env.example .env
   make install && make build
   make run
   ```

---

*Developed with a passion for high-performance backend systems.*
