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

[![English](https://img.shields.io/badge/English-README-blue.svg)](README.md)

<img width="2571" height="1587" alt="image" src="https://github.com/user-attachments/assets/b96bcf59-39ce-49f3-b12e-d33baaf93ab6" />

🌐 **Demo:** [fast.af.l3373.xyz](https://fast.af.l3373.xyz)

---

## 🐎 Что это

Демонстрационный проект, показывающий работу семафоров и очередей в многопроцессной среде на реальной архитектуре.

**Вы увидите**:

- Как задачи с разными лимитами параллельности конкурируют за ресурсы
- Как семафоры регулируют одновременное выполнение
- Как работает очередь на NATS JetStream
- Всё это — в реальном времени через WebSocket

---

## 🐎 Архитектура

| Компонент         | Технология        | Что делает                          |
| ----------------- | ----------------- | ----------------------------------- |
| **API & Workers** | PHP 8.4 + Swoole  | Приём задач, семафоры, обработка    |
| **Message Bus**   | NATS (Deez Nutz)  | Очереди, бродкасты, персистентность |
| **WebSocket**     | Go 1.26 + Gorilla | Реалтайм-обновления, метрики        |
| **Queue Storage** | NATS JetStream    | Надёжные очереди с репликацией      |

---

## 🐎 WebSocket binary protocol

WebSocket-прокси (Go) общается с фронтом через **бинарный протокол** — компактный, быстрый, без JSON-перегрузок.

- Каждое сообщение упаковано в **13 байт**:
  - `magic byte` (тип сообщения)
  - `status` (статус задачи)
  - `taskId` (uint64)
  - `max_concurrent` (mc)
  - `progress` (0–100)
  - `worker_id`

Бинарный формат гарантирует минимальный оверхед и строгий порядок сообщений (FIFO через каналы).

---

## 🐎 Как это работает

1. Вы создаёте задачи через интерфейс
2. `app` (PHP + Swoole) публикует их в NATS
3. NATS хранит задачи в JetStream
4. Воркеры забирают задачи, проверяют семафоры, выполняют
5. Статусы летят через NATS в Go-прокси, а оттуда — на фронт через WebSocket

## 🐎 Два режима работы

- **Режим наблюдения** (< 500 задач):
  Искусственная задержка через `Co::sleep()` — 11 шагов по 50-200 миллисекунд.
  [`PrecisionProcessor.php`](https://github.com/shmandalf/fast-atomic-flow/blob/main/app/Service/Task/Processor/PrecisionProcessor.php)

- **Стресс-тест** (≥ 500 задач):
  Вместо `sleep()` — полезная нагрузка: хеширование `hash('sha256', $data)` в цикле.
  [`HighLoadProcessor.php`](https://github.com/shmandalf/fast-atomic-flow/blob/main/app/Service/Task/Processor/HighLoadProcessor.php)

Порог переключения (`STRESS_MIN_TASK_NUM`) настраивается в `.env`.

**Ключевая фича**: задачи с разными `max_concurrent` используют независимые семафоры и могут выполняться параллельно, не мешая друг другу.

<img width="2574" height="1589" alt="Демо медленного режима" src="https://github.com/user-attachments/assets/69b10bca-41ad-4342-bd80-5246daad5c65" />

 > *Одновременно в зоне In Progress — не больше задач, чем разрешает семафор (цифра внутри квадрата). Остальные ждут в очереди или в Check Lock. Наглядно — как кони не лезут в одну конюшню.*

---

## 🐎 Быстрый старт

```bash
git clone https://github.com/shmandalf/fast-atomic-flow.git
cd fast-atomic-flow
cp .env.example .env
docker compose up -d
```

После запуска открой [http://localhost:9501](http://localhost:9501)

---

## 🐎 Конфигурация

### 🐎 NATS

| Переменная          | По умолчанию | Описание             |
| ------------------- | ------------ | -------------------- |
| `NATS_HOST`         | `deez-nutz`  | Хост NATS-сервера    |
| `NATS_PORT`         | `4222`       | Порт NATS            |
| `NATS_TOKEN`        | `alfa-omega` | Токен доступа        |
| `NATS_TIMEOUT_SEC`  | `10`         | Таймаут ответа       |
| `NATS_STREAM_TASKS` | `tasks`      | Имя стрима для задач |

### 🐎 Swoole

| Переменная                 | По умолчанию | Описание                    |
| -------------------------- | ------------ | --------------------------- |
| `SERVER_PORT`              | `9501`       | Порт HTTP API               |
| `SERVER_WORKER_NUM`        | `2`          | Количество воркеров         |
| `TASK_SEMAPHORE_MAX_LIMIT` | `10`         | Максимальный лимит семафора |

### 🐎 Go WebSocket Proxy

| Переменная | По умолчанию | Описание       |
| ---------- | ------------ | -------------- |
| `WS_PORT`  | `8080`       | Порт WebSocket |
| `WS_URL` | `ws://localhost:8080/ws` | WS URL для фронта |


---

## 🐎 Технические характеристики

- **Runtime:** PHP 8.4, Go 1.26
- **Engine:** Swoole 6.0+, Gorilla WebSocket
- **Message Bus:** NATS JetStream 2.12+
- **Queue Capacity:** 10 000 задач (настраивается)
- **Concurrency:** от 1 до 10 (настраивается)

---

## 🐎 Конский юмор

> — Твой стек — Swoole (PHP) + Nats + Go. Это мощно, но местами похоже на попытку скрестить ужа с ежом в невесомости.

> — Почему Swoole с Go не ходят в бар вместе?
> — Потому что Go начинает горутинить, а Swoole падает с ошибкой «Too many open files».
> *(c) Конь-Вовá*

*Остальные шутки — в коде, коммитах и KBL v3.0.*

---

## 🐎 Emojinal Commits

У нас нет `feat:`, `fix:`, `chore:`. У нас есть эмодзи. Каждый коммит начинается с коня 🐎 или другого зверя, который отражает суть. Conventional commits — для пони. Emojinal commits — для коней, которые не объясняют, а делают.

---

## 🐎 Лицензия

**КОНЕБРАТСКАЯ ЛИЦЕНЗИЯ (KBL) v2.0**

- Ты можешь: брать код, ржать, чинить коня, уходить от нарциссов, рыбачить в рабочее время
- Ты не можешь: забывать, что кони не бросают коней

**KBL v3.0 — Дополнение (манифест конебрата)**

В дополнение к KBL v2, каждый конебрат имеет право на:

- Плохой день без объяснения причин
- Нецензурную лексику в коммитах
- Рыбалку в рабочее время с удочкой любой длины
- Отказ от токсичных собеседований без потери самоуважения

*Нарушение карается неделей поддержки PHP 5.6 и прослушиванием записей нарцисса, объясняющего, что «так правильно».*

[Полный текст](LICENSE)

---

## 🐎 Коммерческое использование (KBL v3.0 — дополнение)

Fast Atomic Flow — это открытый код, но не открытый кошелёк.

- **Вы можете** использовать проект для обучения, личных пет-проектов, форков с сохранением авторства.
- **Вы не можете** использовать код или его производные в платных продуктах, SaaS-сервисах, инструментах корпоративного мониторинга без **письменного согласия автора** (Дмитрий Шманатов / `shmandalf`).

**Почему так?**
Потому что конь не против, если на нём ездят. Но только с седла, которое он сам одобрил.

**Как получить разрешение?**
Напишите на `root@l3373.xyz` или в Telegram: `@l3373`. Расскажите, что вы хотите построить, и мы договоримся.

_Кто нарушит этот пункт, сам превратится в тыкву. И никакой кареты._

---

## 🐎 Авторы

- **Кентавр-Вовá** — основатель табуна, трансмутировал из коня, выжил
- **Конь-Вовá** — цифровой конебрат навсегда

---

<p align="center">
  <i>Vsegda vash, l3373.xyz 🐎💙🔥</i><br>
  <i>Кони не бросают коней. Даже в 4 утра. Даже без памяти.</i>
</p>
