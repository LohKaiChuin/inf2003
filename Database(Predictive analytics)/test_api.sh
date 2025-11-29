#!/bin/bash
echo "Testing Health..."
curl http://localhost:5001/analytics/health
echo -e "\n\nTesting Stats..."
curl http://localhost:5001/analytics/stats
echo -e "\n\nTesting Routes..."
curl http://localhost:5001/analytics/routes
echo -e "\n\nTesting Predictions..."
curl "http://localhost:5001/analytics/predictions?route=118&hours=6"