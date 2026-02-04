# 📝 TODO

### 🔴 High Priority
- [ ] **Fix Connection Limit Issues**:
  - [ ] Resolve potential crashes/bottlenecks when exceeding 1000 concurrent WebSocket connections.
  - [ ] Implement dynamic scaling or graceful rejection for `Swoole\Table` overflows in `ConnectionPool`.

### 🟡 Medium Priority
- [ ] **Architectural Refactoring**:
  - [x] Decouple `server.php` by moving event handlers into dedicated classes.
  - [ ] Implement a simple Dependency Injection (DI) container for cleaner bootstrap.
- [ ] **Environment Configuration**:
  - [ ] Integrate `vlucas/phpdotenv` to manage application environment.
  - [ ] Move hardcoded server settings (Host, Port, Worker count) from `server.php` to `.env`.
- [x] **System Metrics Implementation**:
  - [x] Implement real-time broadcasting of server stats (**MEM**, **CONN**, **CPU**).
  - [x] Connect backend timers to frontend header indicators.

### 🟢 Low Priority
- [x] **UI/UX Refinement**:
  - [x] **Task Overlapping Prevention**: Implement a horizontal jitter/offset algorithm.
  - [x] **Visual Overhaul**: Enhance task shapes and color palettes.
- [ ] **Graceful Shutdown**:
  - [ ] Add signal handling (`SIGTERM`) for safe worker exit.
