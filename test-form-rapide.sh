#!/bin/bash
# Script simple pour tester rapidement un formulaire
# Usage: ./test-form-rapide.sh [FORM_UID]

FORM_UID="${1:-BRYJGSDOHQQU}"
BASE_URL="http://localhost:8000"
API_URL="${BASE_URL}/forms/${FORM_UID}/submit"

echo "=========================================="
echo "Test de soumission de formulaire"
echo "=========================================="
echo "UID du formulaire: ${FORM_UID}"
echo "URL de l'API: ${API_URL}"
echo ""

echo "Test de soumission..."
curl -X POST "${API_URL}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Origin: https://external-landing-page.com" \
  -d '{
    "first_name": "Test",
    "phone": "0708363767",
    "email": "test@example.com",
    "zip_code": "50000",
    "constent": true
}' \
  -w "\n\nCode HTTP: %{http_code}\n"

echo ""
echo "Test terminé!"









