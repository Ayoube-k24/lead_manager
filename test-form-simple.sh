#!/bin/bash

# Script simple pour tester rapidement un formulaire
# Usage: ./test-form-simple.sh [FORM_UID]

FORM_UID="${1:-VOTRE_FORM_UID_ICI}"
BASE_URL="${2:-http://localhost:8000}"

echo "Test de soumission pour le formulaire: ${FORM_UID}"
echo ""

# Commande curl simple
curl -X POST "${BASE_URL}/forms/${FORM_UID}/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Origin: https://external-landing-page.com" \
  -d '{
    "email": "test@example.com",
    "name": "Test User",
    "phone": "+33 6 12 34 56 78"
}' \
  -v

echo ""
echo ""
echo "Pour tester avec d'autres données, modifiez le JSON dans le script."


