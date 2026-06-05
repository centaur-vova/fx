// Package embed provides access to embedded Lua scripts.
package embed

import "embed"

// luaFS contains the embedded Lua scripts from the lua/ directory.
//
//go:embed lua/*.lua
var luaFS embed.FS

// LoadLua reads and returns the content of a Lua script by name.
func LoadLua(name string) string {
	data, _ := luaFS.ReadFile("lua/" + name)
	return string(data)
}
