#!/usr/bin/env bash

current=$PWD
dependencies=('art4/requests-psr18-adapter' 'clue/stream-filter' 'league/container' 'nyholm/psr7' 'php-http/discovery' 'php-http/message' 'php-http/message-factory' 'php-http/multipart-stream-builder' 'psr/container' 'psr/http-client' 'psr/http-message' 'spatie/data-transfer-object' 'tectalic/openai')

namespaces=('Art4\Requests' 'Clue\StreamFilter' 'League\Container' 'Nyholm\Psr7' 'Http\Discovery' 'Http\Message' 'Http\Message\Factory' 'Http\Message\MultipartStream' 'Psr\Container' 'Psr\Http\Client' 'Psr\Http\Message' 'Spatie\DataTransferObject' 'Tectalic\OpenAi')

for ((i = 0; i < ${#dependencies[@]}; ++i)); do
  output_dir="$current/includes/Vendor/${namespaces[$i]//\\/\/}"
  php-scoper add-prefix \
    --config="$current/scoper.php" \
    --force \
    --quiet \
    --output-dir="$output_dir" \
    --prefix="OM4\CopyCraft\Vendor" \
    --working-dir="vendor/${dependencies[$i]}/src"
done

# Fix for Http\Message\Factory
mv "$current"/includes/Vendor/Http/Message/Factory/* "$current"/includes/Vendor/Http/Message/
rmdir "$current"/includes/Vendor/Http/Message/Factory

# Special handling of psr/http-factory independently
php-scoper add-prefix \
  --no-config \
  --force \
  --quiet \
  --output-dir="$current/includes/Vendor/Psr/Http/Factory" \
  --prefix="OM4\CopyCraft\Vendor" \
  --working-dir="vendor/psr/http-factory/src"

mv "$current"/includes/Vendor/Psr/Http/Factory/* "$current"/includes/Vendor/Psr/Http/Message/
rmdir "$current"/includes/Vendor/Psr/Http/Factory

# Special handling of v1-compat inside art4/requests-psr18-adapter
php-scoper add-prefix \
  --no-config \
  --force \
  --quiet \
  --output-dir="$current/includes/Vendor/WpOrg/Requests" \
  --prefix="OM4\CopyCraft\Vendor" \
  --working-dir="vendor/art4/requests-psr18-adapter/v1-compat"

# remove autoload file
rm "$current"/includes/Vendor/WpOrg/Requests/autoload.php

# move Invalidargument to a PSR-4 autoladable location
mkdir "$current"/includes/Vendor/WpOrg/Requests/Exception
mv "$current"/includes/Vendor/WpOrg/Requests/InvalidArgument.php "$current"/includes/Vendor/WpOrg/Requests/Exception/InvalidArgument.php
