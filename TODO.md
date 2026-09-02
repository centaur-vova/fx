# 📝 TODO

### 🔴 High Priority

### 🟡 Medium Priority
- [ ] **Load & Stress Testing**:
  - [x] Implement **k6** scripts to simulate 10,000+ concurrent WebSocket clients
  - [ ] Measure memory stability and `Swoole\Table` contention under high-frequency broadcasting
  - [ ] Publish benchmark results in README or Wiki
- [ ] **Configuration**: Make WebSocket client send buffer configurable via `.env`
- [ ] **NATS**: Add batch injecting tasks into JetStream (ADR-50 fast-ingest batch publishing)
- [x] **Theme Fallback**: Add client-side validation for `?theme=` parameter
  - [x] Check `allowedThemes = ['fast', 'crystal', 'sin-city']` in the inline script
  - [x] Fallback to `fast` theme when an unknown theme name is provided
  - [x] Prevent loading of missing CSS/JS files that break layout and coordinates
- [x] **Architectural Refactoring**:
  - [x] Decouple `server.php` by moving event handlers into dedicated classes.
  - [x] Implement a simple Dependency Injection (DI) container for cleaner bootstrap
- [x] **Environment Configuration**:
  - [x] Integrate `vlucas/phpdotenv` to manage application environment
  - [x] Move hardcoded server settings (Host, Port, Worker count) from `server.php` to `.env`
- [x] **System Metrics Implementation**:
  - [x] Implement real-time broadcasting of server stats (**MEM**, **CONN**, **CPU**)
  - [x] Connect backend timers to frontend header indicators

### 🟢 Low Priority
- [ ] **Health Check**: Enhance the existing `/api/tasks/health` endpoint to report `Swoole\Table` saturation and worker liveness
- [ ] **Fix Connection Limit Issues**:
  - [ ] Resolve potential crashes/bottlenecks when exceeding 1000 concurrent WebSocket connections.
  - [ ] Implement dynamic scaling or graceful rejection for `Swoole\Table` overflows in `ConnectionPool`
- [x] **UI/UX Refinement**:
  - [x] **Task Overlapping Prevention**: Implement a horizontal jitter/offset algorithm
  - [x] **Visual Overhaul**: Enhance task shapes and color palettes
- [x] **Graceful Shutdown**:
  - [x] Add signal handling (`SIGTERM`) for safe worker exit
- [x] **Protocol Optimization**:
  - [x] Move static metadata (`cpuCores`, `workers`) to initial handshake (onOpen)
  - [x] Remove redundant fields from real-time `SystemStats` DTO to reduce frame overhead
