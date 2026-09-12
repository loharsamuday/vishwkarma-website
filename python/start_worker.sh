#!/bin/bash
# Telegram Quiz Worker — Linux/Production Startup Script
# python/start_worker.sh

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "======================================"
echo " Telegram Quiz Worker - Starting..."
echo "======================================"

if [ ! -f ".env" ]; then
    echo "ERROR: .env file not found!"
    echo "cp .env.example .env && nano .env"
    exit 1
fi

# Install dependencies if needed
pip3 install -r requirements.txt -q

echo "Starting worker... Press Ctrl+C to stop."
echo "Logs: $SCRIPT_DIR/worker.log"

# Auto-restart on crash
while true; do
    python3 tg_quiz_worker.py || true
    echo "Worker stopped. Restarting in 10s..."
    sleep 10
done
