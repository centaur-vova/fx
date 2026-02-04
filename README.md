# 🚀 Atomic Flow

**High-performance task concurrency visualizer built with PHP 8.4 and Swoole.**

Atomic Flow is a state-of-the-art demonstration of asynchronous PHP, shared-state management, and real-time observability. By leveraging **Swoole Coroutines** and **Shared Memory**, it bypasses traditional FPM limitations to achieve sub-millisecond dispatching and zero-latency inter-process communication.

## 📌 Project Overview

The system orchestrates a high-load pipeline where tasks compete for atomic semaphores with varying concurrency limits (1-10). It demonstrates how to manage complex task lifecycles—from staggered ingestion to synchronized execution—within a single, persistent PHP process.

## ✨ Key Technical Features

- **Stateful PHP Core**: Operates on a persistent `Swoole\WebSocket\Server`, maintaining application state in memory across requests.
- **Shared Memory Connection Pool**: Utilizes `Swoole\Table` for high-speed, cross-worker management of WebSocket connections.
- **Pre-allocated Channel Semaphores**: Implements ultra-fast concurrency control using a factory of `Swoole\Coroutine\Channel` objects, eliminating external locking overhead.
- **Non-blocking Worker Pool**: A pool of persistent coroutines processing a high-capacity `mainQueue` (10,000 slots) with automatic context-switching.
- **PSR-3 Compliant Logging**: High-precision `StdoutLogger` with microsecond timestamps for debugging rapid-fire asynchronous events.
- **Pusher Protocol Simulation**: Handles internal Pusher-compatible events (`ping`, `subscribe`) for seamless integration with standard JS libraries.
- **Real-time Pipeline UI**: An interactive dashboard using Vanilla JS and Tailwind CSS, visualizing tasks as they traverse through `QUEUE`, `LOCK CHECK`, `IN PROGRESS`, and `COMPLETE` states.

## 🏗 Architecture & Internal Flow

1.  **Ingestion**: `TaskService` generates tasks and staggers their entry via `Swoole\Timer`.
2.  **Dispatching**: One of the 10 idle worker coroutines pops a task from the `mainQueue`.
3.  **Synchronization**: The task attempts to `acquire()` a permit from a specific `SwooleChannelSemaphore` based on its `maxConcurrent` limit.
4.  **Execution**: Work is simulated using non-blocking `Co::sleep()`, allowing the Swoole scheduler to rotate through other active coroutines.
5.  **Broadcast**: Every state update is instantly dispatched via the `MessageHub` to all clients stored in the `Swoole\Table` connection pool.

## 🛠 Tech Stack

- **Runtime**: PHP 8.4+, [Swoole](https://www.swoole.com) (Async Engine)
- **Architecture**: Domain-Driven Design (DDD) with Strategy, Factory, and Proxy patterns.
- **Frontend**: Vanilla ES6+ JavaScript, [Tailwind CSS 4.0](https://tailwindcss.com)
- **IPC/Storage**: Swoole Shared Memory Tables & Coroutine Channels.

## 🚀 Getting Started

1.  **Install dependencies**:
    ```bash
    composer install
    npm install
    ```
2.  **Build frontend assets**:
    ```bash
    npm run build
    ```
3.  **Launch the server**:
    ```bash
    php server.php
    ```
4.  **Access the Dashboard**:
    Open `http://localhost:9501` in your browser.

