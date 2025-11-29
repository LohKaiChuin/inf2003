#!/bin/bash
# SSH Tunnel Setup Script for MySQL Connection
# This creates a secure tunnel to the remote MySQL database

echo "================================================"
echo "  MySQL SSH Tunnel Setup"
echo "================================================"
echo ""
echo "Starting SSH tunnel to remote database..."
echo "Remote host: inf2003-dev@104.198.169.207"
echo "Local port:  33060 -> Remote MySQL port: 3306"
echo ""
echo "⚠️  You may need to enter your SSH password"
echo ""

# Start SSH tunnel
ssh -L 33060:127.0.0.1:3306 inf2003-dev@104.198.169.207

# This will keep the tunnel open until you press Ctrl+C
