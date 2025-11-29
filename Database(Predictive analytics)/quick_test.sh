#!/bin/bash
# Quick test script to verify everything is working

echo "========================================"
echo "  Quick System Check"
echo "========================================"
echo ""

# Check 1: SSH Tunnel
echo "1. Checking SSH tunnel on port 33060..."
if lsof -i :33060 | grep -q LISTEN; then
    echo "   ✅ SSH tunnel is running"
else
    echo "   ❌ SSH tunnel is NOT running"
    echo "   → Start it with: ssh -L 33060:127.0.0.1:3306 inf2003-dev@104.198.169.207"
fi
echo ""

# Check 2: PHP Server
echo "2. Checking PHP server on port 8000..."
if lsof -i :8000 | grep -q LISTEN; then
    echo "   ✅ PHP server is running"
else
    echo "   ❌ PHP server is NOT running"
    echo "   → Start it with: php -S localhost:8000"
fi
echo ""

# Check 3: Database Connection
echo "3. Testing database connection via API..."
response=$(curl -s "http://localhost:8000/Analytics_api.php?action=health")
if echo "$response" | grep -q '"status":"healthy"'; then
    echo "   ✅ Database connection successful"
    echo "$response" | python3 -m json.tool 2>/dev/null | head -5
else
    echo "   ❌ Database connection failed"
    echo "   Response: $response"
fi
echo ""

echo "========================================"
echo "If all checks pass, run:"
echo "  python test_live_predictions.py"
echo "========================================"
