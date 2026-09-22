# 📝 TODO

### 🔴 High Priority

### 🟡 Medium Priority
- [ ] **Unify THEME_DEFAULTS keys with YAML**:
  - [ ] Currently UPPER_SNAKE_CASE in defaults (REMOVE_DELAYS, PROGRESS_BAR), lower_snake_case in theme YAML (remove_delays, progress_bar)
  - [ ] getUISetting lowercases for YAML lookup but uses raw category for fallback → fallback breaks if category passed in wrong case
  - [ ] Decide on one convention and apply everywhere
- [ ] **Load & Stress Testing**:
  - [x] Implement **k6** scripts to simulate 10,000+ concurrent WebSocket clients
  - [ ] Measure memory stability and `Swoole\Table` contention under high-frequency broadcasting
  - [ ] Publish benchmark results in README or Wiki
- [ ] **Configuration**: Make WebSocket client send buffer configurable via `.env`
- [ ] **NATS**: Add batch injecting tasks into JetStream (ADR-50 fast-ingest batch publishing)

### 🟢 Low Priority
- [ ] **Health Check**: Enhance the existing `/api/tasks/health` endpoint to report `Swoole\Table` saturation and worker liveness
- [ ] **Fix Connection Limit Issues**:
  - [ ] Resolve potential crashes/bottlenecks when exceeding 1000 concurrent WebSocket connections.
  - [ ] Implement dynamic scaling or graceful rejection for `Swoole\Table` overflows in `ConnectionPool`
- [ ] **Smooth LOD scaling**:
  - [ ] Replace three-step LOD (normal / medium / point) with a continuous scale function
  - [ ] Suggested: comfort zone up to N tasks (full size), then linear interpolation down to min scale at M tasks
  - [ ] Configurable via theme (lod.comfort_max, lod.point_max, lod.scale_max, lod.scale_min)
- [ ] **Matrix-specific visuals**:
  - [ ] Consider removing task labels (numbers on squares) in Matrix theme only
  - [ ] Reduce base square size for Matrix (already at scale_normal: 0.8)
  - [ ] Evaluate if task_colors: 255 -> red should stay hardcoded or become theme-driven
- [ ] **Documentation**:
  - [ ] Document color_generator `*` prefix in Wiki (seed from task id, not mc)


