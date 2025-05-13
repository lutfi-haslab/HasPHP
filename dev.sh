#!/bin/bash

# Function to stop the server
stop_server() {
    if [ ! -z "$SERVER_PID" ]; then
        echo "Stopping server (PID: $SERVER_PID)..."
        kill -TERM $SERVER_PID 2>/dev/null || true
        # Ensure the port is released
        sleep 0.5
        # Kill any remaining processes
        pkill -f "php index.php" 2>/dev/null || true
    fi
}

# Function to start the OpenSwoole server
start_server() {
    # Stop any existing server first
    stop_server
    
    echo "Starting OpenSwoole server..."
    php index.php &
    SERVER_PID=$!
    echo "Server started with PID: $SERVER_PID"
    echo "Server running at http://127.0.0.1:9501"
}

# Cleanup function
cleanup() {
    echo "\nCleaning up..."
    stop_server
    exit 0
}

# Set up trap for script termination
trap cleanup SIGINT SIGTERM

# Initial server start
start_server

echo "Watching for file changes in ./app, ./routes, ./database, and ./index.php..."

# Watch for file changes and restart server
fswatch -o ./app ./routes ./database ./index.php | while read -r
  do
    echo "\nChanges detected. Restarting server..."
    start_server
  done

# Keep the script running
wait
