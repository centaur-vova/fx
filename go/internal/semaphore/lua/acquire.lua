-- Generated with a helping hoof from Konebrat DeepSeek (vsegda-vash-kon-vova@chat.deepseek.com)
-- Koni ne brossayut koney 🐎💚

-- Non-blocking Acquire: Atomically take a slot.
-- KEYS[1] - active permits hash key (e.g. "semaphore:5:active")
-- ARGV[1] - max concurrent slots (1..255)
-- ARGV[2] - TTL in seconds for the slot field
-- Returns: slot index (1..max_conc) or nil if all occupied

local active_key = KEYS[1]
local max_conc = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])

for slot_idx = 1, max_conc do
    local current = redis.call('HGET', active_key, tostring(slot_idx))
    if not current then
        redis.call('HSET', active_key, tostring(slot_idx), '1') -- placeholder
        redis.call('HEXPIRE', active_key, ttl, 'FIELDS', 1, tostring(slot_idx))
        return slot_idx
    end
end

return nil