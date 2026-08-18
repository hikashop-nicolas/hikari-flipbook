#!/bin/sh
# Render every distributable image from the SVG sources in assets/.
# Requires rsvg-convert (brew install librsvg). Outputs are reproducible.
set -e
cd "$(dirname "$0")/../assets"

# Joomla / JED and the house convention
rsvg-convert -w 884  -h 344 logo.svg      -o logo.png
rsvg-convert -w 1200 -h 525 banner.svg    -o banner.png

# wordpress.org plugin assets
rsvg-convert -w 1544 -h 500 banner-wp.svg -o banner-1544x500.png
rsvg-convert -w 772  -h 250 banner-wp.svg -o banner-772x250.png
rsvg-convert -w 256  -h 256 icon.svg      -o icon-256x256.png
rsvg-convert -w 128  -h 128 icon.svg      -o icon-128x128.png

# generic square icon for docs, favicons and the demo site
rsvg-convert -w 512  -h 512 icon.svg      -o icon-512.png

echo "rendered:"
ls -l *.png
