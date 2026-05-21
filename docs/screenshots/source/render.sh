#!/usr/bin/env bash
# Renders the HTML preview pages in ./source to PNG screenshots in ../
# Requires: wkhtmltoimage (apt-get install wkhtmltopdf).

set -euo pipefail
cd "$(dirname "$0")"

mobile=(
  00-splash
  01-customer-login
  02-customer-otp
  03-customer-home
  03b-customer-destination-search
  04-customer-fare-estimate
  05-customer-searching
  06-customer-active-ride
  06b-customer-ride-complete
  07-driver-online
  08-driver-incoming-offer
  09-driver-active-ride
)

for name in "${mobile[@]}"; do
  echo "render: ${name}"
  wkhtmltoimage \
    --quiet --enable-local-file-access \
    --width 393 --height 852 \
    --quality 95 --format png \
    "${name}.html" "../${name}.png"
done

echo "render: 10-admin-live-monitor"
wkhtmltoimage \
  --quiet --enable-local-file-access \
  --width 1280 --height 900 \
  --quality 95 --format png \
  10-admin-live-monitor.html ../10-admin-live-monitor.png

echo
echo "Done."
ls -lh ../*.png
