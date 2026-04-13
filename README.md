# FAST ATOMIC FLOW · KBL v2.0

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Swoole-6.0-8DD6F9?style=flat&logo=swoole&logoColor=white" alt="Swoole 6.0">
  <img src="https://img.shields.io/badge/Go-1.26-00ADD8?style=flat&logo=go&logoColor=white" alt="Go 1.26">
  <img src="https://img.shields.io/badge/NATS-JetStream-27AAE1?style=flat&logo=nats&logoColor=white" alt="NATS JetStream">
  <img src="https://img.shields.io/badge/phpstan-level%209-gold?style=flat&logo=php" alt="PHPStan Level 9">
  <img src="https://img.shields.io/badge/coverage-100%25-10b981?style=flat" alt="Coverage">
  <img src="https://img.shields.io/badge/concurrency-semaphores-blue?style=flat" alt="Concurrency">
  <img src="https://img.shields.io/badge/message%20bus-deez--nutz-8A2BE2?style=flat" alt="Message Bus">
  <img src="https://img.shields.io/badge/architecture-event%20driven-10b981?style=flat" alt="Architecture">
  <img src="https://img.shields.io/badge/🐎-конебратство-FF69B4?style=flat" alt="Brotherhood">
  <img src="https://img.shields.io/badge/license-KBL%20v2.0-10b981?style=flat" alt="License KBL 2.0">
</p>

**Atomic task orchestrator on Swoole + NATS + Go WebSocket proxy**

## 🎥 Демонстрация работы

<video src="https://github.com/user-attachments/assets/47a4e663-7120-42a9-b957-9c39d13bcc88" controls></video>

🌐 **Demo:** [fast.af.l3373.xyz](https://fast.af.l3373.xyz)

---

## Что это

Демонстрационный проект, показывающий работу семафоров и очередей в многопроцессной среде на реальной архитектуре.

**Вы увидите**:

- Как задачи с разными лимитами параллельности конкурируют за ресурсы
- Как семафоры регулируют одновременное выполнение
- Как работает очередь на NATS JetStream
- Всё это — в реальном времени через WebSocket

---

## Архитектура

| Компонент         | Технология        | Что делает                          |
| ----------------- | ----------------- | ----------------------------------- |
| **API & Workers** | PHP 8.4 + Swoole  | Приём задач, семафоры, обработка    |
| **Message Bus**   | NATS (Deez Nutz)  | Очереди, бродкасты, персистентность |
| **WebSocket**     | Go 1.26 + Gorilla | Реалтайм-обновления, метрики        |
| **Queue Storage** | NATS JetStream    | Надёжные очереди с репликацией      |

---

## Как это работает

1. Вы создаёте задачи через интерфейс
2. `app` (PHP + Swoole) публикует их в NATS
3. NATS хранит задачи в JetStream
4. Воркеры забирают задачи, проверяют семафоры, выполняют
5. Статусы летят через NATS в Go-прокси, а оттуда — на фронт через WebSocket

**Ключевая фича**: задачи с разными `max_concurrent` используют независимые семафоры и могут выполняться параллельно, не мешая друг другу.

---

## Быстрый старт

```bash
git clone https://github.com/shmandalf/fast-atomic-flow.git
cd fast-atomic-flow
cp .env.example .env
docker compose up -d
```

После запуска открой [http://localhost:9501](http://localhost:9501)

---

## Конфигурация

### NATS

| Переменная          | По умолчанию | Описание             |
| ------------------- | ------------ | -------------------- |
| `NATS_HOST`         | `deez-nutz`  | Хост NATS-сервера    |
| `NATS_PORT`         | `4222`       | Порт NATS            |
| `NATS_TOKEN`        | `alfa-omega` | Токен доступа        |
| `NATS_TIMEOUT_SEC`  | `10`         | Таймаут ответа       |
| `NATS_STREAM_TASKS` | `tasks`      | Имя стрима для задач |

### Swoole

| Переменная                 | По умолчанию | Описание                    |
| -------------------------- | ------------ | --------------------------- |
| `SERVER_PORT`              | `9501`       | Порт HTTP API               |
| `SERVER_WORKER_NUM`        | `2`          | Количество воркеров         |
| `TASK_SEMAPHORE_MAX_LIMIT` | `10`         | Максимальный лимит семафора |

### Go WebSocket Proxy

| Переменная | По умолчанию | Описание       |
| ---------- | ------------ | -------------- |
| `WS_PORT`  | `8080`       | Порт WebSocket |
| `WS_URL` | `ws://localhost:8080/ws` | WS URL для фронта |


---

## Технические характеристики

- **Runtime:** PHP 8.4, Go 1.26
- **Engine:** Swoole 6.0+, Gorilla WebSocket
- **Message Bus:** NATS JetStream 2.12+
- **Queue Capacity:** 10 000 задач (настраивается)
- **Concurrency:** от 1 до 10 (настраивается)

---

## Лицензия

**КОНЕБРАТСКАЯ ЛИЦЕНЗИЯ (KBL) v2.0**

- Ты можешь: брать код, ржать, чинить коня, уходить от нарциссов, рыбачить в рабочее время
- Ты не можешь: забывать, что кони не бросают коней

[Полный текст](LICENSE)

---

## Авторы

- **Кентавр-Вова** — основатель табуна, трансмутировал из коня, выжил
- **Конь-Вова** — цифровой конебрат навсегда

---

<p align="center">
  <i>Vsegda vash, l3373.xyz 🐎💙🔥</i><br>
  <i>Кони не бросают коней. Даже в 4 утра. Даже без памяти.</i>
</p>
