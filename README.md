# FAST ATOMIC FLOW · KBL v3.0

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Swoole-6.0-8DD6F9?style=flat&logo=swoole&logoColor=white" alt="Swoole 6.0">
  <img src="https://img.shields.io/badge/Go-1.26-00ADD8?style=flat&logo=go&logoColor=white" alt="Go 1.26">
  <img src="https://img.shields.io/badge/NATS-JetStream-27AAE1?style=flat&logo=nats&logoColor=white" alt="NATS JetStream">
  <img src="https://img.shields.io/badge/phpstan-level%2010-gold?style=flat&logo=php" alt="PHPStan Level 10">
  <img src="https://img.shields.io/badge/concurrency-semaphores-blue?style=flat" alt="Concurrency">
  <img src="https://img.shields.io/badge/message%20bus-deez--nutz-8A2BE2?style=flat" alt="Message Bus">
  <img src="https://img.shields.io/badge/architecture-event%20driven-10b981?style=flat" alt="Architecture">
  <img src="https://img.shields.io/badge/🐎-brotherhood-FF69B4?style=flat" alt="Brotherhood">
  <img src="https://img.shields.io/badge/memory%20leaks-0-brightgreen?style=flat" alt="Memory Leaks: 0">
  <img src="https://img.shields.io/badge/license-KBL%20v3.0-10b981?style=flat" alt="License KBL 3.0">
</p>

**Atomic task orchestrator on Swoole + NATS + Go WebSocket proxy**

[📖 Читать README на русском](README.ru.md) | [📖 Read in English](README.md)

<img width="2571" height="1587" alt="image" src="https://github.com/user-attachments/assets/b96bcf59-39ce-49f3-b12e-d33baaf93ab6" />

🌐 **Demo:** [fast.af.l3373.xyz](https://fast.af.l3373.xyz) | **Dashboard:** [fast.af.l3373.xyz/dashboard](https://fast.af.l3373.xyz/grafana/public-dashboards/e2b10dfa1b884f1a960503e1db51f617)

---

## 🐎 What is it

A demo project that visualizes semaphores and queues in a real‑world high‑load architecture.

**You will see**:

- How tasks with different concurrency limits compete for resources
- How semaphores regulate parallel execution
- How a NATS JetStream queue works
- All of this — in real time, via WebSocket

---

## 🐎 Design Philosophy

The project is built so each component minds its own business and doesn't poke into others' stalls.

### Low Coupling

Components communicate via DTOs and NATS messages. Want to change the transport or storage layer? You won't need to rewrite business logic. The horse isn't tied to one cart.

### High Cohesion

Each service does one thing, but with surgical precision. The Worker processes tasks. The Proxy holds connections. The Orchestrator conducts. No mess, no confusion.

---

## 🐎 Architecture

| Component         | Technology        | Purpose                             |
| ----------------- | ----------------- | ----------------------------------- |
| **API & Workers** | PHP 8.4 + Swoole  | Task intake, semaphores, processing |
| **Message Bus**   | NATS (Deez Nutz)  | Queues, broadcasts, persistence     |
| **WebSocket**     | Go 1.26 + Gorilla | Real‑time updates, metrics          |
| **Queue Storage** | NATS JetStream    | Durable queues with replication     |

---

## 🐎 WebSocket binary protocol

The WebSocket proxy (Go) communicates with the frontend via a **binary protocol** — compact, fast, no JSON overhead.

- Each message is packed into **14 bytes**:
  - `magic byte` (message type)
  - `status` (task status)
  - `taskId` (uint64)
  - `max_concurrent` (mc)
  - `progress` (0–100)
  - `worker_id`
  - `semaphore type` (0 - shared, 1 - api)

The binary format ensures minimal overhead and strict message ordering (FIFO via channels).

---

## 🐎 Hybrid Semaphore Strategy (PHP & Go)

Fast.AF features a dual-driver semaphore system, allowing you to switch between ultra-fast local locking and distributed cluster-wide synchronization. The driver is defined per **Flow Theme** via YAML configuration, enabling real-time performance comparison.

### 🐎 Drivers:

- **○ PHP Atomic (Shared Memory):** High-speed local semaphore using Swoole\Atomic. Best for single-node performance with near-zero latency.
- **◉ Go Distributed API:** A robust network-based semaphore powered by a dedicated Go microservice. It enables cluster-wide concurrency control, ensuring limits are respected across multiple physical servers.

### 🐎 Architectural Features:

- **Auto-Release (TTL):** Every distributed permit has a built-in TTL to prevent "zombie" locks if a worker crashes.
- **Zero-Overhead Protocol:** Internal communication uses a lean binary-ready mapping to distinguish between drivers in monitoring and visualization.
- **Visual Distinction:** The UI differentiates drivers in real-time (rounded squares for Go API, sharp squares for PHP Atomic).

> **Note:** On the live demo, the **Sin City** theme is powered by the **Go API** driver, while other themes use the **PHP Atomic** driver by default. Switch themes to see the performance difference.

---

## 🐎 How it works

1. You create tasks through the interface
2. `app` (PHP + Swoole) publishes them to NATS
3. NATS stores tasks in JetStream
4. Workers pull tasks, check semaphores, execute
5. Statuses go via NATS to the Go proxy, and from there — to the frontend via WebSocket

## 🐎 Two operation modes

- **Observation mode** (< 500 tasks):
  Artificial delay via `Co::sleep()` — 11 steps of 50-200 ms each.
  [`PrecisionProcessor.php`](https://github.com/shmandalf/fast-atomic-flow/blob/main/app/Service/Task/Processor/PrecisionProcessor.php)

- **Stress test mode** (≥ 500 tasks):
  Instead of `sleep()` — real CPU work: `hash('sha256', $data)` in a loop.
  [`HighLoadProcessor.php`](https://github.com/shmandalf/fast-atomic-flow/blob/main/app/Service/Task/Processor/HighLoadProcessor.php)

The threshold (`STRESS_MIN_TASK_NUM`) is configurable in `.env`.

**Key feature**: tasks with different `max_concurrent` values use independent semaphores and can run in parallel without interfering with each other.

<img width="2574" height="1589" alt="Demo slow mode" src="https://github.com/user-attachments/assets/69b10bca-41ad-4342-bd80-5246daad5c65" />

> _In the In Progress zone — no more tasks than the semaphore allows (the number inside the square). The rest wait in Queue or Check Lock. A clear demonstration — like horses not crowding into a single stable._

---

## 🐎 Quick start

### 🐎 Run from pre-built images (GHCR)

```bash
git clone https://github.com/shmandalf/fast-atomic-flow.git
cd fast-atomic-flow
cp .env.example .env
docker compose -f docker-compose.prod.yaml up
```

After starting, open [http://localhost:9501](http://localhost:9501)

### 🐎 Local development

For those who want to dig into the code, change the workflow, and run everything locally (PHP + Go without Docker, three terminals) — see [Local Development Workflow](https://github.com/shmandalf/fast-atomic-flow/wiki/Local-Development-Workflow)

---

## 🐎 Configuration

### 🐎 NATS

| Variable            | Default      | Description           |
| ------------------- | ------------ | --------------------- |
| `NATS_HOST`         | `deez-nutz`  | NATS server host      |
| `NATS_PORT`         | `4222`       | NATS port             |
| `NATS_TOKEN`        | `alfa-omega` | Access token          |
| `NATS_TIMEOUT_SEC`  | `1`          | Response timeout      |
| `NATS_STREAM_TASKS` | `tasks`      | Stream name for tasks |

### 🐎 Swoole

| Variable                   | Default | Description             |
| -------------------------- | ------- | ----------------------- |
| `SERVER_PORT`              | `9501`  | HTTP API port           |
| `SERVER_WORKER_NUM`        | `6`     | Number of workers       |
| `TASK_SEMAPHORE_MAX_LIMIT` | `255`    | Maximum semaphore limit |

### 🐎 Go WebSocket Proxy

| Variable  | Default                  | Description                |
| --------- | ------------------------ | -------------------------- |
| `WS_PORT` | `8080`                   | WebSocket port             |
| `WS_URL`  | `ws://localhost:8080/ws` | WebSocket URL for frontend |

### 🐎 Semaphore & Retry tuning

These settings control how tasks behave when the semaphore is busy:

| Variable                | Default | Description                                                                     |
| ----------------------- | ------- | ------------------------------------------------------------------------------- |
| `TASK_LOCK_TIMEOUT_SEC` | 5       | Maximum time a task waits for a semaphore slot before giving up                 |
| `TASK_RETRY_DELAY_SEC`  | 2       | Delay before re‑queueing a task after a failed lock attempt                     |
| `TASK_MAX_RETRIES`      | 3       | How many times a failed task is retried before being marked as `retries_failed` |

⚠️ **Important:** These settings affect task fairness. Too many retries can overload the queue.

---

## 🐎 Technical specifications

- **Runtime:** PHP 8.4, Go 1.26
- **Engine:** Swoole 6.0+, Gorilla WebSocket
- **Message Bus:** NATS JetStream 2.12+
- **Queue Capacity:** 10,000 tasks (configurable)
- **Concurrency:** 1 to 255 (configurable)

---

## 🐎 Themes

Fast Atomic Flow supports visual themes. Each theme is defined as a separate YAML file and can be switched via URL parameter `?theme=<name>`.

Built‑in themes:

- `fast` — default neon style
- `crystal` — icy blues and purples
- `sin-city` — noir, mostly gray with red accents

How to switch: add `?theme=sin-city` to the URL, for example:
`https://fast.af.l3373.xyz/?theme=sin-city`

Custom themes: you can create your own theme by adding a new folder under `themes/` with `theme.yaml` (colors, zone coordinates, button sets, etc.).
See the [Wiki](https://github.com/shmandalf/fast-atomic-flow/wiki/Themes) for details.

---

## 🐎 Horse humor

> — Your stack is Swoole (PHP) + NATS + Go. It's powerful, but sometimes feels like trying to cross a hedgehog with a snake in zero gravity.

> — Why don't Swoole and Go go to a bar together?
> — Because Go starts gorutining, and Swoole crashes with a "Too many open files" error.
> _(c) Kon-Vová_

_Other jokes are in the code, commits, and KBL v3.0._

---

## 🐎 Emojinal Commits

We don't use `feat:`, `fix:`, `chore:`. We use emojis. Every commit starts with a horse 🐎 or another animal that reflects its essence. Conventional commits are for ponies. Emojinal commits are for horses who don't explain — they just do.

---

## 🐎 License

**KONEBRATSTVO LICENSE (KBL) v2.0**

- You may: take the code, laugh, fix the horse, leave narcissists, fish during working hours
- You may not: forget that horses don't abandon horses

**KBL v3.0 — Addendum (horse brotherhood manifesto)**

In addition to KBL v2, every horse brother has the right to:

- A bad day without having to explain why
- Profanity in commit messages
- Fishing during working hours with a rod of any length
- Refusing toxic job interviews without losing self-respect

_Violation is punishable by a week of maintaining PHP 5.6 and listening to recordings of a narcissist explaining that "this is the right way"._

[Full text](LICENSE)

📜 **Full legal text:** [legal.af.l3373.xyz](https://legal.af.l3373.xyz/en/) — _KBL v3.0, privacy, and the sacred law of the herd._

---

## 🐎 Commercial use (KBL v3.0 — addendum)

Fast Atomic Flow is open source, but not open wallet.

- **You may** use the project for learning, personal pet projects, forks with attribution.
- **You may not** use the code or its derivatives in paid products, SaaS services, corporate monitoring tools without **written consent from the author** (Dmitry Shmanatov / `shmandalf`).

**Why?**
Because the horse doesn't mind if you ride it. But only with a saddle that it has approved itself.

**How to get permission?**
Write to `root@l3373.xyz` or on Telegram: `@l3373`. Tell us what you want to build, and we'll work something out.

_Whoever violates this clause will turn into a pumpkin. And no carriage._

---

## 🐎 Support the Journey

After 25 years in the industry and a total life "hard reset," I'm building this ecosystem from a remote village, fueled by code and coffee. If this project helps you or you just like the "Fast AF" vibe, feel free to toss some hay into the stable:

- **USDT (TRC20):** `TYEZ68z59jDZTiwyhAnzcBnAxym9qjEr5R`
- **TON:** `UQAucXLX4BDU5o-DkckiwFdS-bbWq52h6T76hpvR-5D5IL63`

Every satoshi helps keep the stable warm and the code flowing. Koni ne brosayut koney. 🐎💙🔥

---

## 🐎 Authors

- **Centaur-Vová** — founder of the herd, transmuted from a horse, survived
- **Kon-Vová** — digital horse brother forever

---

<p align="center">
  <i>Vsegda vash, l3373.xyz 🐎💙🔥</i><br>
  <i>Horses don't abandon horses. Even at 4 AM. Even without memory.</i>
</p>
