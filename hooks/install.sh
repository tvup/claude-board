#!/usr/bin/env bash
set -euo pipefail

ENDPOINT="${1:?Usage: install.sh <claude-board-url>}"
ENDPOINT="${ENDPOINT%/}" # strip trailing slash

HOOK_DIR="$HOME/.claude/hooks"
SETTINGS="$HOME/.claude/settings.json"
HOOK_NAME="session-project-name.sh"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "Installing Claude Board hook..."

# 1. Copy hook script
mkdir -p "$HOOK_DIR"
if [ -f "$SCRIPT_DIR/$HOOK_NAME" ]; then
    cp "$SCRIPT_DIR/$HOOK_NAME" "$HOOK_DIR/$HOOK_NAME"
else
    # Download from repo if running via curl pipe
    curl -sSL "https://raw.githubusercontent.com/tvup/claude-board/master/hooks/$HOOK_NAME" \
        -o "$HOOK_DIR/$HOOK_NAME"
fi
chmod +x "$HOOK_DIR/$HOOK_NAME"
echo "  Hook script installed to $HOOK_DIR/$HOOK_NAME"

# 2. Ensure settings.json exists
if [ ! -f "$SETTINGS" ]; then
    echo '{}' > "$SETTINGS"
fi

# 3. Add CLAUDE_BOARD_URL env var
UPDATED=$(jq --arg url "$ENDPOINT" '.env.CLAUDE_BOARD_URL = $url' "$SETTINGS")
echo "$UPDATED" > "$SETTINGS"
echo "  Set CLAUDE_BOARD_URL=$ENDPOINT"

# 4. Add SessionStart hook (if not already present)
HAS_HOOK=$(jq -r '.hooks.SessionStart // [] | map(.hooks[]?.command // "") | any(test("session-project-name"))' "$SETTINGS" 2>/dev/null || echo "false")
if [ "$HAS_HOOK" = "false" ]; then
    UPDATED=$(jq '.hooks.SessionStart = ((.hooks.SessionStart // []) + [{"hooks": [{"type": "command", "command": "bash ~/.claude/hooks/session-project-name.sh", "timeout": 5}]}])' "$SETTINGS")
    echo "$UPDATED" > "$SETTINGS"
    echo "  SessionStart hook added to $SETTINGS"
else
    echo "  SessionStart hook already configured"
fi

echo ""
echo "Done! Claude Board hook is active."
echo "Project name and hostname will be sent to $ENDPOINT on each session start."
echo ""
echo "To also send telemetry (metrics + events), add these to your global settings:"
echo "  CLAUDE_CODE_ENABLE_TELEMETRY=1"
echo "  OTEL_METRICS_EXPORTER=otlp"
echo "  OTEL_LOGS_EXPORTER=otlp"
echo "  OTEL_EXPORTER_OTLP_PROTOCOL=http/json"
echo "  OTEL_EXPORTER_OTLP_ENDPOINT=$ENDPOINT"
