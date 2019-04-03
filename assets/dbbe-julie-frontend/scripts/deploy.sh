#!/bin/bash
cd ..
ng build --prod --env=prod
# deploy the disbiome website to the disbiome webservice
rsync -az -e ssh --progress --exclude dist/.git --delete dist/* dbbetwo.ugent.be:/var/www/html/
# overwrite the local config with the remote config
# ssh disbiomewebserver mv /var/www/html/app/config/appconfig.remote.ts /var/www/html/app/config/appconfig.ts
# recompile everything
# ssh disbiomewebserver 'cd /var/www/html ; npm run tsc