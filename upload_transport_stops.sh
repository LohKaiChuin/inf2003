#!/bin/bash

echo "=========================================="
echo "Uploading Transport Stops Files"
echo "=========================================="
echo ""
echo "Password: Inf2003#DevSecure!2025"
echo ""

# Upload transport_stops.php
echo "1. Uploading transport_stops.php..."
scp transport_stops.php inf2003-dev@104.198.169.207:/var/www/html/

# Upload cleaned poi.php and poi.js
echo "2. Uploading cleaned poi.php..."
scp poi.php inf2003-dev@104.198.169.207:/var/www/html/

echo "3. Uploading cleaned poi.js..."
scp js/poi.js inf2003-dev@104.198.169.207:/var/www/html/js/

# Upload transport_stops.js
echo "4. Uploading transport_stops.js..."
scp js/transport_stops.js inf2003-dev@104.198.169.207:/var/www/html/js/

# Upload transport_stops.css
echo "5. Uploading transport_stops.css..."
scp css/transport_stops.css inf2003-dev@104.198.169.207:/var/www/html/css/

echo ""
echo "=========================================="
echo "Upload Complete!"
echo "=========================================="
echo ""
echo "Access the new page at:"
echo "http://104.198.169.207/transport_stops.php"
echo ""
echo "Features:"
echo "- MRT & Bus stops with multilingual support"
echo "- Language switcher (EN, 中文, தமிழ், Bahasa)"
echo "- Interactive map with markers"
echo "- Searchable stops list"
echo "- POI page cleaned (multilingual removed)"
