#!/bin/bash

mkdir -p ./web/build/static/icons
ln -rs ./assets/websites/static/icons/* ./web/build/static/icons

mkdir -p ./web/build/static/images
ln -rs ./assets/websites/static/images/* ./web/build/static/images
ln -rs ./assets/images/* ./web/build/static/images

mkdir -p ./web/build/julie
ln -rs ./assets/dbbe-julie-frontend/dist/*.js ./web/build/julie
ln -rs ./assets/dbbe-julie-frontend/dist/*.css ./web/build/julie
ln -rs ./assets/dbbe-julie-frontend/dist/fontawesome-webfont* ./web/build/julie
