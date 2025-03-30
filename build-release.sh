#!/usr/bin/env bash
release_version="1.0.1"
set -euo pipefail
rm -R ./dist || true
mkdir -p ./dist/input
cp *.cfg ./dist/input
cp *.sh ./dist/input
cp LICENSE ./dist/input
cp README.md ./dist/input
cp -R ./dpkg ./dist/input
cp -R ./icons ./dist/input
cp -R ./webfrontend ./dist/input
cp .gitattributes ./dist/input
rm ./dist/input/.DS_Store || true
rm ./dist/input/build-release.sh || true
cd ./dist/input
find . -name ".DS_Store" -type f -exec rm -f {} +
zip -r "../Loxberry-Plugin-Vitoconnect-${release_version}.zip" .
cd ../..
rm -R ./dist/input