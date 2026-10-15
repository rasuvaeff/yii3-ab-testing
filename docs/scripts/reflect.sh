#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

# No PHP on the host — run the reflection pass in the composer:2 image, with
# the whole monorepo mounted so reflect-api.php finds the four sibling
# packages at ../<package> (monorepo layout). Each sibling needs its own
# vendor/ (composer install inside it).
docker run --rm -v "$(cd ../../.. && pwd)":/repo -w /repo/yii3-ab-testing/docs/scripts \
    composer:2 php reflect-api.php > api-snapshot.json
