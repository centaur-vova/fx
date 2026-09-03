// Command bench is a native Go WebSocket load testing tool for Fast Atomic Flow.
// It spawns N concurrent connections to the WebSocket proxy, sends periodic pings,
// and measures message throughput under high concurrency to validate system stability.
package main

import (
	"context"
	"flag"
	"fmt"
	"log"
	"net/url"
	"os"
	"os/signal"
	"sync"
	"sync/atomic"
	"time"

	"github.com/gorilla/websocket"
)

func main() {
	connsPtr := flag.Int("conns", 1000, "Number of simultaneous connections")
	hostPtr := flag.String("host", "localhost:8080", "Target WS host")
	flag.Parse()

	u := url.URL{Scheme: "ws", Host: *hostPtr, Path: "/ws"}
	log.Printf("🏎️ Spawning %d native Go connections to %s", *connsPtr, u.String())

	var activeConns atomic.Int64
	var msgReceived atomic.Int64
	var wg sync.WaitGroup

	// Thread-safe registry of active connections for graceful shutdown
	var connMu sync.Mutex
	conns := make(map[*websocket.Conn]struct{})

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Catch Ctrl+C for graceful shutdown and final stats output
	interrupt := make(chan os.Signal, 1)
	signal.Notify(interrupt, os.Interrupt)
	go func() {
		<-interrupt
		log.Println("\n🛑 Graceful shutdown initiated. Closing all active WebSockets...")
		cancel()

		// Send proper Close Frame to server, then close socket
		connMu.Lock()
		for c := range conns {
			_ = c.WriteMessage(websocket.CloseMessage, websocket.FormatCloseMessage(websocket.CloseNormalClosure, "Client shutting down"))
			_ = c.Close()
		}
		connMu.Unlock()
	}()

	// Rate-limit handshake spawn to 2ms intervals to avoid overwhelming the kernel network stack
	limiter := time.NewTicker(time.Millisecond * 2)
	defer limiter.Stop()

	for i := 0; i < *connsPtr; i++ {
		select {
		case <-ctx.Done():
			log.Println("Context cancelled during spawn")
			wg.Wait()
			return
		case <-limiter.C:
			wg.Add(1)
			go func() {
				defer wg.Done()

				dialer := websocket.Dialer{
					HandshakeTimeout: 5 * time.Second,
				}

				c, _, err := dialer.DialContext(ctx, u.String(), nil)
				if err != nil {
					return
				}

				// Register connection for potential shutdown
				connMu.Lock()
				conns[c] = struct{}{}
				connMu.Unlock()

				defer func() {
					connMu.Lock()
					delete(conns, c)
					connMu.Unlock()
					_ = c.Close()
				}()

				activeConns.Add(1)
				defer activeConns.Add(-1)

				// Ping loop to mimic client-side keepalive (same interval as app.js)
				go func() {
					ticker := time.NewTicker(3 * time.Second)
					defer ticker.Stop()
					for {
						select {
						case <-ctx.Done():
							return
						case <-ticker.C:
							_ = c.WriteMessage(websocket.TextMessage, []byte(`{"event":"ping","data":{"ts":7273.7}}`))
						}
					}
				}()

				// Read raw frames directly from the underlying buffer with minimal processing
				for {
					_, _, err := c.ReadMessage()
					if err != nil {
						return
					}
					msgReceived.Add(1)
				}
			}()
		}
	}

	// Print live performance metrics every 2 seconds
	go func() {
		ticker := time.NewTicker(2 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				fmt.Printf("📊 Active Conns: %d | Total Messages Received: %d\n",
					activeConns.Load(),
					msgReceived.Load(),
				)
			}
		}
	}()

	wg.Wait()
	log.Printf("🏁 Benchmark finished. Total messages processed: %d", msgReceived.Load())
}
