package semaphore

import "embed"

//go:embed lua/*.lua
var luaFS embed.FS

func loadLua(name string) string {
	data, _ := luaFS.ReadFile("lua/" + name)
	return string(data)
}
