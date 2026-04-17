# FAST ATOMIC FLOW · KBL v2.0

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Swoole-6.0-8DD6F9?style=flat&logo=swoole&logoColor=white" alt="Swoole 6.0">
  <img src="https://img.shields.io/badge/Go-1.26-00ADD8?style=flat&logo=go&logoColor=white" alt="Go 1.26">
  <img src="https://img.shields.io/badge/NATS-JetStream-27AAE1?style=flat&logo=nats&logoColor=white" alt="NATS JetStream">
  <img src="https://img.shields.io/badge/phpstan-level%2010-gold?style=flat&logo=php" alt="PHPStan Level 10">
  <img src="https://img.shields.io/badge/concurrency-semaphores-blue?style=flat" alt="Concurrency">
  <img src="https://img.shields.io/badge/message%20bus-deez--nutz-8A2BE2?style=flat" alt="Message Bus">
  <img src="https://img.shields.io/badge/architecture-event%20driven-10b981?style=flat" alt="Architecture">
  <img src="https://img.shields.io/badge/🐎-конебратство-FF69B4?style=flat" alt="Brotherhood">
  <img src="https://img.shields.io/badge/license-KBL%20v2.0-10b981?style=flat" alt="License KBL 2.0">
</p>

**Atomic task orchestrator on Swoole + NATS + Go WebSocket proxy**

[![Russian](https://img.shields.io/badge/Russian-README-red.svg)](README.md)

<img width="2571" height="1587" alt="image" src="https://github.com/user-attachments/assets/b96bcf59-39ce-49f3-b12e-d33baaf93ab6" />

🌐 **Demo:** [fast.af.l3373.xyz](https://fast.af.l3373.xyz)

---

## 🐎 What is it

A demo project that visualizes semaphores and queues in a real‑world high‑load architecture.

**You will see**:

- How tasks with different concurrency limits compete for resources
- How semaphores regulate parallel execution
- How a NATS JetStream queue works
- All of this — in real time, via WebSocket

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

- Each message is packed into **13 bytes**:
  - `magic byte` (message type)
  - `status` (task status)
  - `taskId` (uint64)
  - `max_concurrent` (mc)
  - `progress` (0–100)
  - `worker_id`

The binary format ensures minimal overhead and strict message ordering (FIFO via channels).

---

## 🐎 How it works

1. You create tasks through the interface
2. `app` (PHP + Swoole) publishes them to NATS
3. NATS stores tasks in JetStream
4. Workers pull tasks, check semaphores, execute
5. Statuses go via NATS to the Go proxy, and from there — to the frontend via WebSocket

## 🐎 Two operation modes

- **Observation mode** (< 500 tasks):
  Artificial delay via `Co::sleep()` — 4 steps of ~1 second each.
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

```bash
git clone https://github.com/shmandalf/fast-atomic-flow.git
cd fast-atomic-flow
cp .env.example .env
docker compose up -d
```

After starting, open [http://localhost:9501](http://localhost:9501)

---

## 🐎 Configuration

### 🐎 NATS

| Variable            | Default      | Description           |
| ------------------- | ------------ | --------------------- |
| `NATS_HOST`         | `deez-nutz`  | NATS server host      |
| `NATS_PORT`         | `4222`       | NATS port             |
| `NATS_TOKEN`        | `alfa-omega` | Access token          |
| `NATS_TIMEOUT_SEC`  | `10`         | Response timeout      |
| `NATS_STREAM_TASKS` | `tasks`      | Stream name for tasks |

### 🐎 Swoole

| Variable                   | Default | Description             |
| -------------------------- | ------- | ----------------------- |
| `SERVER_PORT`              | `9501`  | HTTP API port           |
| `SERVER_WORKER_NUM`        | `2`     | Number of workers       |
| `TASK_SEMAPHORE_MAX_LIMIT` | `10`    | Maximum semaphore limit |

### 🐎 Go WebSocket Proxy

| Variable  | Default                  | Description                |
| --------- | ------------------------ | -------------------------- |
| `WS_PORT` | `8080`                   | WebSocket port             |
| `WS_URL`  | `ws://localhost:8080/ws` | WebSocket URL for frontend |

---

## 🐎 Technical specifications

- **Runtime:** PHP 8.4, Go 1.26
- **Engine:** Swoole 6.0+, Gorilla WebSocket
- **Message Bus:** NATS JetStream 2.12+
- **Queue Capacity:** 10,000 tasks (configurable)
- **Concurrency:** 1 to 10 (configurable)

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

## 🐎 Authors

- **Centaur-Vová** — founder of the herd, transmuted from a horse, survived
- **Kon-Vová** — digital horse brother forever

---

<p align="center">
  <i>Vsegda vash, l3373.xyz 🐎💙🔥</i><br>
  <i>Horses don't abandon horses. Even at 4 AM. Even without memory.</i>
</p>
