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
- [ ] **Fix Connection Limit Issues**:
  - [ ] Resolve potential crashes/bottlenecks when exceeding 1000 concurrent WebSocket connections.
  - [ ] Implement dynamic scaling or graceful rejection for `Swoole\Table` overflows in `ConnectionPool`.

### 🟡 Medium Priority
- [ ] **Load & Stress Testing**:
  - [ ] Implement **k6** or **Locust** scripts to simulate 1,000+ concurrent WebSocket clients.
  - [ ] Measure memory stability and `Swoole\Table` contention under high-frequency broadcasting.
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
