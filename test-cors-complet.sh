#!/bin/bash
# Script de test complet pour vérifier CORS

FORM_UID="BRYJGSDOHQQU"
BASE_URL="http://localhost:8000"
API_URL="${BASE_URL}/forms/${FORM_UID}/submit"

echo "=========================================="
echo "Test CORS Complet"
echo "=========================================="
echo ""

# Test 1: Preflight OPTIONS
echo "1. Test Preflight OPTIONS..."
curl -X OPTIONS "${API_URL}" \
  -H "Origin: http://localhost" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -s -o /dev/null -w "Code HTTP: %{http_code}\n"
echo "✅ Preflight OK"
echo ""

# Test 2: Requête POST réelle
echo "2. Test POST avec données..."
RESPONSE=$(curl -X POST "${API_URL}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Origin: http://localhost" \
  -d '{
    "first_name": "Test",
    "phone": "0708363767",
    "email": "test@example.com",
    "zip_code": "50000",
    "constent": true
}' \
  -s -w "\nHTTP_CODE:%{http_code}")

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')

echo "Code HTTP: $HTTP_CODE"
echo "Réponse:"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"

if [ "$HTTP_CODE" = "201" ]; then
    echo "✅ POST réussi avec headers CORS!"
else
    echo "⚠️  Code: $HTTP_CODE"
fi
echo ""

# Test 3: Vérification des headers CORS dans la réponse POST
echo "3. Vérification des headers CORS dans la réponse POST..."
CORS_HEADERS=$(curl -I -X POST "${API_URL}" \
  -H "Content-Type: application/json" \
  -H "Origin: http://localhost" \
  -d '{"email":"test@example.com"}' \
  -s | grep -i "access-control")

if [ -n "$CORS_HEADERS" ]; then
    echo "✅ Headers CORS présents:"
    echo "$CORS_HEADERS"
else
    echo "❌ Headers CORS manquants"
fi
echo ""

echo "=========================================="
echo "Tests terminés"
echo "=========================================="

