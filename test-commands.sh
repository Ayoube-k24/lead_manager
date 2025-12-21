#!/bin/bash

# Script de test complet pour Lead Manager
# Utilisation: ./test-commands.sh

echo "=========================================="
echo "  Tests Lead Manager - Vérification 100%"
echo "=========================================="
echo ""

# Couleurs pour la sortie
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Formater le code avec Pint
echo -e "${YELLOW}1. Formatage du code avec Pint...${NC}"
vendor/bin/pint --dirty
echo ""

# 2. Tests des Contrôleurs (Phase 1)
echo -e "${YELLOW}2. Tests des Contrôleurs...${NC}"
php artisan test tests/Feature/Controllers/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Contrôleurs: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Contrôleurs: FAIL${NC}"
    exit 1
fi
echo ""

# 3. Tests des Observers (Phase 7)
echo -e "${YELLOW}3. Tests des Observers...${NC}"
php artisan test tests/Feature/Observers/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Observers: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Observers: FAIL${NC}"
    exit 1
fi
echo ""

# 4. Tests des Models (Phase 3)
echo -e "${YELLOW}4. Tests des Models...${NC}"
php artisan test tests/Feature/Models/ tests/Unit/Models/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Models: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Models: FAIL${NC}"
    exit 1
fi
echo ""

# 5. Tests des Middlewares (Phase 4)
echo -e "${YELLOW}5. Tests des Middlewares...${NC}"
php artisan test tests/Feature/Middleware/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Middlewares: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Middlewares: FAIL${NC}"
    exit 1
fi
echo ""

# 6. Tests des Services (Phase 2)
echo -e "${YELLOW}6. Tests des Services...${NC}"
php artisan test tests/Feature/Services/ tests/Unit/Services/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Services: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Services: FAIL${NC}"
    exit 1
fi
echo ""

# 7. Tests des Jobs (Phase 5)
echo -e "${YELLOW}7. Tests des Jobs...${NC}"
php artisan test tests/Feature/Jobs/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Jobs: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Jobs: FAIL${NC}"
    exit 1
fi
echo ""

# 8. Tests des Events & Listeners (Phase 6)
echo -e "${YELLOW}8. Tests des Events & Listeners...${NC}"
php artisan test tests/Feature/Events/ tests/Feature/Listeners/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Events & Listeners: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Events & Listeners: FAIL${NC}"
    exit 1
fi
echo ""

# 9. Tests des Commands (Phase 8)
echo -e "${YELLOW}9. Tests des Commands...${NC}"
php artisan test tests/Feature/Commands/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests des Commands: PASS${NC}"
else
    echo -e "${RED}✗ Tests des Commands: FAIL${NC}"
    exit 1
fi
echo ""

# 10. Tests d'Intégration (Phase 12)
echo -e "${YELLOW}10. Tests d'Intégration...${NC}"
php artisan test tests/Feature/Integration/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests d'Intégration: PASS${NC}"
else
    echo -e "${RED}✗ Tests d'Intégration: FAIL${NC}"
    exit 1
fi
echo ""

# 11. Tests de Sécurité
echo -e "${YELLOW}11. Tests de Sécurité...${NC}"
php artisan test tests/Feature/Security/ --stop-on-failure
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests de Sécurité: PASS${NC}"
else
    echo -e "${RED}✗ Tests de Sécurité: FAIL${NC}"
    exit 1
fi
echo ""

# 12. TOUS LES TESTS - Vérification finale
echo -e "${YELLOW}12. Exécution de TOUS les tests (vérification finale)...${NC}"
php artisan test
if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}=========================================="
    echo -e "  ✓ TOUS LES TESTS PASSENT !"
    echo -e "  L'application fonctionne à 100%"
    echo -e "==========================================${NC}"
else
    echo ""
    echo -e "${RED}=========================================="
    echo -e "  ✗ CERTAINS TESTS ÉCHOUENT"
    echo -e "  Vérifiez les erreurs ci-dessus"
    echo -e "==========================================${NC}"
    exit 1
fi








