-- Redis rate limiter (sliding window)
-- KEYS[1]   : rate limit key (e.g., "rate_limit:1.2.3.4")
-- ARGV[1]   : current timestamp in microseconds (now)
-- ARGV[2]   : window threshold in microseconds (now - window_sec * 1_000_000)
-- ARGV[3]   : limit (max requests per window)
-- ARGV[4]   : TTL in seconds (window_sec + 60)
-- Returns   : 1 if allowed, 0 if blocked

local key     = KEYS[1]
local now     = tonumber(ARGV[1])
local threshold = tonumber(ARGV[2])
local limit   = tonumber(ARGV[3])
local ttl     = tonumber(ARGV[4])

-- Remove old records outside the window
redis.call('ZREMRANGEBYSCORE', key, '-inf', threshold)

-- Count remaining records in current window
local count = redis.call('ZCARD', key)

-- Check if limit is exceeded
if count >= limit then
    return 0
end

-- Add current request
redis.call('ZADD', key, now, now)

-- Set TTL (refresh expiry)
redis.call('EXPIRE', key, ttl)

return 1