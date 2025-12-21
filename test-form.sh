#!/bin/bash

# Script de test pour l'API de soumission de formulaire
# Usage: ./test-form.sh [FORM_UID] [BASE_URL]

# Configuration par défaut
FORM_UID="${1:-VOTRE_FORM_UID_ICI}"
BASE_URL="${2:-http://localhost:8000}"

API_URL="${BASE_URL}/forms/${FORM_UID}/submit"

echo "=========================================="
echo "Test de soumission de formulaire"
echo "=========================================="
echo "UID du formulaire: ${FORM_UID}"
echo "URL de l'API: ${API_URL}"
echo ""

# Test 1: Requête preflight OPTIONS (CORS)
echo "1. Test CORS Preflight (OPTIONS)..."
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X OPTIONS "${API_URL}" \
  -H "Origin: https://external-landing-page.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type")

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')

if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Preflight OK (Code: $HTTP_CODE)"
    echo "Headers CORS présents dans la réponse"
else
    echo "❌ Preflight échoué (Code: $HTTP_CODE)"
fi
echo ""

# Test 2: Soumission avec succès
echo "2. Test de soumission avec succès..."
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "${API_URL}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Origin: https://external-landing-page.com" \
  -d '{
    "email": "test@example.com",
    "name": "Test User"
}')

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')

echo "Code HTTP: $HTTP_CODE"
echo "Réponse:"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"

if [ "$HTTP_CODE" = "201" ]; then
    echo "✅ Soumission réussie!"
else
    echo "❌ Échec de la soumission"
fi
echo ""

# Test 3: Vérification des headers CORS dans la réponse
echo "3. Vérification des headers CORS..."
CORS_HEADERS=$(curl -s -I -X POST "${API_URL}" \
  -H "Content-Type: application/json" \
  -H "Origin: https://external-landing-page.com" \
  -d '{"email":"test@example.com"}' | grep -i "access-control")

if [ -n "$CORS_HEADERS" ]; then
    echo "✅ Headers CORS présents:"
    echo "$CORS_HEADERS"
else
    echo "❌ Headers CORS manquants"
fi
echo ""

# Test 4: Test avec données invalides (validation)
echo "4. Test avec données invalides (validation)..."
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "${API_URL}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "email-invalide"
}')

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')

echo "Code HTTP: $HTTP_CODE"
echo "Réponse:"
echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"

if [ "$HTTP_CODE" = "422" ]; then
    echo "✅ Validation fonctionne correctement (erreur attendue)"
else
    echo "⚠️  Code inattendu"
fi
echo ""

# Test 5: Test avec formulaire inactif
echo "5. Test avec formulaire inactif..."
echo "⚠️  Ce test nécessite un formulaire avec is_active=false"
echo ""

echo "=========================================="
echo "Tests terminés"
echo "=========================================="







