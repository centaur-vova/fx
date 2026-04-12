package main

import (
	"encoding/json"
	"fmt"
	"log"
	"os"
	"strconv"
	"time"

	"github.com/nats-io/nats.go"
	"github.com/olekukonko/tablewriter"
)

type SwooleStatus struct {
	Status string `json:"status"`
	Tasks  int    `json:"tasks"`
	Pid    int    `json:"pid"`
	Time   string `json:"time"`
}

func main() {
	nc, err := nats.Connect("nats://localhost:4222", nats.Token("alfa-omega"))
	if err != nil {
		log.Fatal(err)
	}
	defer nc.Close()

	fmt.Print("\033[H\033[2J")

	for {
		msg, err := nc.Request("admin.status", nil, 1*time.Second)
		if err != nil {
			fmt.Printf("\r❌ Waiting for Swoole... %v", err)
			time.Sleep(1 * time.Second)
			continue
		}

		var stats SwooleStatus
		if err := json.Unmarshal(msg.Data, &stats); err != nil {
			continue
		}

		fmt.Print("\033[H")
		table := tablewriter.NewWriter(os.Stdout)

		table.Header([]string{"SERVER PID", "STATUS", "ACTIVE TASKS", "REMOTE TIME"})

		table.Append([]string{
			strconv.Itoa(stats.Pid),
			stats.Status,
			strconv.Itoa(stats.Tasks),
			stats.Time,
		})

		table.Render()
		time.Sleep(1 * time.Second)
	}
}
