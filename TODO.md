# 📝 TODO

### 🔴 High Priority
- [ ] **Real-time Pipeline Monitoring**:
  - [x] Implement a live counter for **Incomplete Tasks** (tasks currently being processed).
  - [ ] Integrate "In-Flight" task count into the global metrics broadcasting.
- [ ] **Fix Connection Limit Issues**:
  - [ ] Resolve potential crashes/bottlenecks when exceeding 1000 concurrent WebSocket connections.
  - [ ] Implement dynamic scaling or graceful rejection for `Swoole\Table` overflows in `ConnectionPool`.

### 🟡 Medium Priority
- [ ] **Load & Stress Testing**:
  - [ ] Implement **k6** or **Locust** scripts to simulate 1,000+ concurrent WebSocket clients.
  - [ ] Measure memory stability and `Swoole\Table` contention under high-frequency broadcasting.
- [ ] **Architectural Refactoring**:
  - [x] Decouple `server.php` by moving event handlers into dedicated classes.
  - [ ] Implement a simple Dependency Injection (DI) container for cleaner bootstrap.
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
- [ ] **Graceful Shutdown**:
  - [ ] Add signal handling (`SIGTERM`) for safe worker exit.
