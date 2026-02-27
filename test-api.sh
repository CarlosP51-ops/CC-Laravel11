#!/bin/bash

# Script de test de l'API d'authentification
# Usage: bash test-api.sh

API_URL="http://localhost:8000/api"
echo "🧪 Test de l'API d'authentification"
echo "=================================="
echo ""

# Test 1: Inscription
echo "📝 Test 1: Inscription d'un client"
echo "-----------------------------------"
REGISTER_RESPONSE=$(curl -s -X POST "$API_URL/register" \
  -H "Accept: application/json" \
  -F "name=Test User" \
  -F "email=test$(date +%s)@example.com" \
  -F "password=password123" \
  -F "password_confirmation=password123" \
  -F "phone=+33612345678" \
  -F "role=customer")

echo "$REGISTER_RESPONSE" | jq '.'
TOKEN=$(echo "$REGISTER_RESPONSE" | jq -r '.token')
echo ""

# Test 2: Récupérer l'utilisateur connecté
echo "👤 Test 2: Récupérer l'utilisateur connecté"
echo "-------------------------------------------"
curl -s -X GET "$API_URL/user" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

# Test 3: Déconnexion
echo "🚪 Test 3: Déconnexion"
echo "----------------------"
curl -s -X POST "$API_URL/logout" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

# Test 4: Connexion
echo "🔐 Test 4: Connexion"
echo "--------------------"
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"email\":\"$(echo $REGISTER_RESPONSE | jq -r '.user.email')\",\"password\":\"password123\"}")

echo "$LOGIN_RESPONSE" | jq '.'
echo ""

echo "✅ Tests terminés!"
