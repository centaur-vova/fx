package embed

import "embed"

//go:embed lua/*.lua
var luaFS embed.FS

func LoadLua(name string) string {
	data, _ := luaFS.ReadFile("lua/" + name)
	return string(data)
}
