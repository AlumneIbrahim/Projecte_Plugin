#!/bin/bash

BASE_URL="http://localhost/wordpress"

echo "Testing WP-AI-Guard with real HTTP requests..."
echo "--------------------------------------------"

# 1. Normal request
echo "1. Normal Request: /"
curl -s -o /dev/null -I -w "%{http_code}\n" "$BASE_URL/"

# 2. XSS Attempt
echo "2. XSS Attempt: /?s=<script>alert(1)</script>"
curl -s -o /dev/null -I -w "%{http_code}\n" "$BASE_URL/?s=%3Cscript%3Ealert(1)%3C/script%3E"

# 3. SQL Injection
echo "3. SQL Injection: /?id=1 UNION SELECT user,pass FROM wp_users"
curl -s -o /dev/null -I -w "%{http_code}\n" "$BASE_URL/?id=1%20UNION%20SELECT%20user,pass%20FROM%20wp_users"

# 4. Directory Traversal
echo "4. Directory Traversal: /?file=../../../../etc/passwd"
curl -s -o /dev/null -I -w "%{http_code}\n" "$BASE_URL/?file=../../../../etc/passwd"

# 5. Event Handler XSS
echo "5. Event Handler XSS: /?q=<img src=x onerror=alert(1)>"
curl -s -o /dev/null -I -w "%{http_code}\n" "$BASE_URL/?q=%3Cimg%20src=x%20onerror=alert(1)%3E"

echo "--------------------------------------------"
echo "Tests completed. Please check the WP-AI-Guard dashboard."
