#!/bin/bash

mkdir -p ./public/build/ckeditor/lang
cp ./node_modules/ckeditor/lang/en.js ./public/build/ckeditor/lang
cp -R ./node_modules/ckeditor/plugins ./public/build/ckeditor
mkdir -p ./public/build/ckeditor/skins
cp -R ./node_modules/ckeditor/skins/moono-lisa ./public/build/ckeditor/skins
cp ./node_modules/ckeditor/ckeditor.js ./public/build/ckeditor
cp ./node_modules/ckeditor/config.js ./public/build/ckeditor
cp ./node_modules/ckeditor/contents.css ./public/build/ckeditor
cp ./node_modules/ckeditor/styles.js ./public/build/ckeditor

# remove unnecessary language files
find public/build/ckeditor -name "lang" -print0 | while IFS= read -r -d $'\0' line; do
    ls -d -1 "$line/"*.* | grep -v "en.js" | xargs -r rm
done

mkdir -p ./public/build/static/icons
cp ./assets/websites/static/icons/* ./public/build/static/icons
mkdir -p ./public/build/static/images
cp ./assets/websites/static/images/* ./public/build/static/images
cp ./assets/images/* ./public/build/static/images

mkdir -p ./public/build/julie
cp ./assets/dbbe-julie-frontend/dist/*.js ./public/build/julie
cp ./assets/dbbe-julie-frontend/dist/*.css ./public/build/julie
cp ./assets/dbbe-julie-frontend/dist/fontawesome-webfont* ./public/build/julie
