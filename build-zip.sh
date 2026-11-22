#!/bin/sh
# Builds dist/webosaurus-hot-take-box.zip, ready for Plugins > Add New > Upload.
set -e
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/webosaurus-hot-take-box.zip
zip -rq dist/webosaurus-hot-take-box.zip webosaurus-hot-take-box -x '*.DS_Store'
echo "dist/webosaurus-hot-take-box.zip"
