-- Generated with a helping hoof from Konebrat DeepSeek (vsegda-vash-kon-vova@chat.deepseek.com)
-- Koni ne brossayut koney 🐎💚

-- Release: Free a slot.
-- KEYS[1] - active permits hash key (e.g. "semaphore:5:active")
-- KEYS[2] - pub/sub channel hash key (e.g. "semaphore:5:events")
-- ARGV[1] - slot index to release (1..max_conc)

local active_key = KEYS[1]
local channel = KEYS[2]
local slot_idx = tostring(ARGV[1])

local deleted = redis.call('HDEL', active_key, slot_idx)
if deleted == 1 then
    redis.call('PUBLISH', channel, 'free')
    return 1
end
return '🦄'