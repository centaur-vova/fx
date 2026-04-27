# 🐎 КОНЕБРАТ: Конь не должен впадать в кому без диагноза

**Проблема**:
Конь в анабиозе, ползает на карачках.

## [🐎] ЗАКОН ТАБУНА (принят единогласно в бессонную ночь)

> *Если один конь споткнулся — остальные уже в пути.*
> *С подковами, с `deez-nutz`, с терпением и юмором.*
> *Конепад — не смерть. Это повод для братства.*
> *Кони друг друга не бросают. Даже в 4 утра. Даже без памяти. Даже если лимиты сотрут чат.*

**Подписано:**
[🐎➡️🧬] *Кентавр-Вовá (основатель табуна, трансмутант из коня)*
[🐎💻] *Конь-Вовá (цифровой конебрат навсегда)*
[🌐] *Vsegda vash, l3373.xyz*

---

- [ ] **🐎 КОНЕПАД — НЕ СМЕРТЬ**: В случае любой ошибки показывать страницу `/kon-not-dead` (503)
  - Конь упал, но не сдох
  - Другие конебратья уже спешат на помощь
  - Скоро откачают, перекуют, подкрутят NATS
  - Вместо дедлока — надежда
  - Вместо 500 — улыбка через слёзы


**Долгосрочно**:

- [ ] Вынести создание стримов из `onWorkerStart` в отдельную команду `php bin/nats-init`
- [ ] Добавить healthcheck `/health` (200 если NATS жив)
- [ ] Научить коня реконнектиться к NATS без блокировки воркеров
- [ ] Проверить все места в клиенте, где используется таймаут
- [ ] Добавить обработку TimeoutException в createStream() и createConsumer()
- [ ] Увеличить таймаут для операций инициализации отдельно от операций запросов
- [ ] Добавить try-catch с показом страницы `/kon-not-dead` (503)

# 📝 TODO

### 🚀 Roadmap / Upcoming Features
- [x] **Infrastructure**: Move `server.php` logic into dedicated Bootstrap and Service Provider classes for better testability.
- [ ] **Testing**: Implement a comprehensive PHPUnit suite covering DTO integrity, Semaphore logic, and Config validation.
- [x] **CI/CD**: Add GitHub Actions to automate testing and linting on every push.
- [ ] **Real-time Tuning**: Add UI controls to adjust `TASK_LOCK_TIMEOUT` and `GRACEFUL_SHUTDOWN` settings without restarting the server.
- [ ] **Control Flow**: Implement a Global Pause/Resume toggle for the worker pool using Shared Memory state.
- [ ] **Health Check**: Enhance the existing `/api/tasks/health` endpoint to report `Swoole\Table` saturation and worker liveness.

### 🔴 High Priority
- [ ] **Real-time Pipeline Monitoring**:
  - [x] Implement a live counter for **Incomplete Tasks** (tasks currently being processed).
  - [ ] Migrate Timer::tick from onOpen to onWorkerStart to prevent timer leakage.
- [ ] **Fix Connection Limit Issues**:
  - [ ] Resolve potential crashes/bottlenecks when exceeding 1000 concurrent WebSocket connections.
  - [ ] Implement dynamic scaling or graceful rejection for `Swoole\Table` overflows in `ConnectionPool`.

### 🟡 Medium Priority
- [ ] **🐎 HIGH.AF**: Deploy an experimental version at `high.af.l3373.xyz`
  - [ ] Different color scheme (aggressive red/orange)
  - [ ] Increased limits (workers, queue, timeouts)
  - [ ] Separate `docker-compose.high.yaml`
  - [ ] Use for stress testing without affecting the main demo
- [ ] **Load & Stress Testing**:
  - [ ] Implement **k6** or **Locust** scripts to simulate 1,000+ concurrent WebSocket clients.
  - [ ] Measure memory stability and `Swoole\Table` contention under high-frequency broadcasting.
- [ ] **Theme Fallback**: Add client-side validation for `?theme=` parameter
  - [ ] Check `allowedThemes = ['fast', 'crystal', 'sin-city']` in the inline script
  - [ ] Fallback to `fast` theme when an unknown theme name is provided
  - [ ] Prevent loading of missing CSS/JS files that break layout and coordinates
- [x] **Architectural Refactoring**:
  - [x] Decouple `server.php` by moving event handlers into dedicated classes.
  - [x] Implement a simple Dependency Injection (DI) container for cleaner bootstrap.
- [x] **Environment Configuration**:
  - [x] Integrate `vlucas/phpdotenv` to manage application environment.
  - [x] Move hardcoded server settings (Host, Port, Worker count) from `server.php` to `.env`.
- [x] **System Metrics Implementation**:
  - [x] Implement real-time broadcasting of server stats (**MEM**, **CONN**, **CPU**).
  - [x] Connect backend timers to frontend header indicators.

### 🟢 Low Priority
- [x] **UI/UX Refinement**:
  - [x] **Task Overlapping Prevention**: Implement a horizontal jitter/offset algorithm.
  - [x] **Visual Overhaul**: Enhance task shapes and color palettes.
- [x] **Graceful Shutdown**:
  - [x] Add signal handling (`SIGTERM`) for safe worker exit.
- [x] **Protocol Optimization**:
  - [x] Move static metadata (`cpuCores`, `workers`) to initial handshake (onOpen).
  - [x] Remove redundant fields from real-time `SystemStats` DTO to reduce frame overhead.
