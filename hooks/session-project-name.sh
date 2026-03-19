#!/usr/bin/env bash
# Claude Board SessionStart hook
# Auto-detects project name and hostname, sends to Claude Board API
# Installed via: hooks/install.sh <claude-board-url>

INPUT=$(cat)
SESSION_ID=$(echo "$INPUT" | jq -r '.session_id')
CWD=$(echo "$INPUT" | jq -r '.cwd')
PROJECT_NAME=$(basename "$CWD")
HOSTNAME_VAL=$(hostname)
ENDPOINT="${CLAUDE_BOARD_URL:-http://localhost:8080}"

if [ "$SESSION_ID" != "null" ] && [ -n "$PROJECT_NAME" ] && [ "$PROJECT_NAME" != "/" ]; then
    curl -s -X POST "$ENDPOINT/api/sessions/$SESSION_ID/project" \
        -H "Content-Type: application/json" \
        -d "{\"project_name\":\"$PROJECT_NAME\",\"hostname\":\"$HOSTNAME_VAL\"}" \
        --max-time 2 > /dev/null 2>&1 &
fi

exit 0
