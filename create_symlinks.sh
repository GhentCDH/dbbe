#!/bin/bash

mkdir -p ./public/build/static/icons
ln -rs ./assets/websites/static/icons/* ./public/build/static/icons

mkdir -p ./public/build/static/images
ln -rs ./assets/websites/static/images/* ./public/build/static/images
ln -rs ./assets/images/* ./public/build/static/images

mkdir -p ./public/build/julie
ln -rs ./assets/dbbe-julie-frontend/dist/*.js ./public/build/julie
ln -rs ./assets/dbbe-julie-frontend/dist/*.css ./public/build/julie
ln -rs ./assets/dbbe-julie-frontend/dist/fontawesome-webfont* ./public/build/julie
