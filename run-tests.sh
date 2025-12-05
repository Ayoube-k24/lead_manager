#!/bin/bash
echo "=========================================="
echo "Lancement des tests unitaires"
echo "=========================================="
echo ""

php artisan test --colors=always

echo ""
echo "=========================================="
echo "Tests terminés"
echo "=========================================="

