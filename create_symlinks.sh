#!/bin/bash

mkdir -p ./public/build/static/icons
ln -frs ./assets/websites/static/icons/* ./public/build/static/icons

mkdir -p ./public/build/static/images
ln -frs ./assets/websites/static/images/* ./public/build/static/images
ln -frs ./assets/images/* ./public/build/static/images

mkdir -p ./public/build/julie
ln -frs ./assets/dbbe-julie-frontend/dist/*.js ./public/build/julie
ln -frs ./assets/dbbe-julie-frontend/dist/*.css ./public/build/julie
ln -frs ./assets/dbbe-julie-frontend/dist/fontawesome-webfont* ./public/build/julie
