#!/bin/bash
# Kopieer boekhouden bestanden naar lx en voer deploy uit
# Gebruik: ./push_to_lx.sh
set -e

REMOTE="lx"
SRC_DIR="/tmp/boekhouden_deploy"
LOCAL="/home/pieter/boekhouden"

echo "=== Bestanden klaarzetten op lx ==="

ssh $REMOTE "rm -rf $SRC_DIR && mkdir -p $SRC_DIR/php $SRC_DIR/css $SRC_DIR/sql $SRC_DIR/pdf"

scp $LOCAL/index.php $LOCAL/login.php $LOCAL/logout.php $LOCAL/favicon.svg $REMOTE:$SRC_DIR/
scp $LOCAL/css/style.css $REMOTE:$SRC_DIR/css/
scp $LOCAL/php/*.php $REMOTE:$SRC_DIR/php/
scp $LOCAL/pdf/* $REMOTE:$SRC_DIR/pdf/ 2>/dev/null || true
scp $LOCAL/sql/*.sql $REMOTE:$SRC_DIR/sql/

echo ""
echo "Bestanden staan klaar. Voer uit op lx:"
echo "  sudo deploy-boekhouden"