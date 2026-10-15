DOCKER := docker run --rm -v "$(PWD)":/app -w /app composer:2
DOCKER_HOST := docker run --rm --network host -v "$(PWD)":/app -w /app
PCOV_BOOTSTRAP := apk add --no-cache $$PHPIZE_DEPS >/dev/null && pecl install pcov >/dev/null && docker-php-ext-enable pcov

.PHONY: bench build cs cs-fix psalm test mutation rector rector-fix install normalize require-checker \
       test-coverage test-coverage-ci update-deps release-check bc-check audit-package \
       docs-api docs-build docs-dev docs-links docs-vale

install:
	$(DOCKER) composer install --no-interaction --no-progress --prefer-dist

bench:
	$(DOCKER) composer bench

build:
	$(DOCKER) composer build

cs:
	$(DOCKER) composer cs

cs-fix:
	$(DOCKER) composer cs:fix

psalm:
	$(DOCKER) composer psalm

test:
	$(DOCKER) composer test

test-coverage:
	$(DOCKER) sh -lc '$(PCOV_BOOTSTRAP) && composer test:coverage'

test-coverage-ci:
	$(DOCKER) sh -lc '$(PCOV_BOOTSTRAP) && composer test:coverage:ci'

mutation:
	$(DOCKER) sh -lc '$(PCOV_BOOTSTRAP) && composer mutation'

rector:
	$(DOCKER) composer rector

rector-fix:
	$(DOCKER) composer rector:fix

normalize:
	$(DOCKER) sh -c 'git config --global --add safe.directory /app; composer normalize'

require-checker:
	$(DOCKER) composer require-checker

update-deps:
	$(DOCKER) sh -c 'git config --global --add safe.directory /app; composer update -q; composer normalize'

release-check:
	$(DOCKER) composer release-check
	$(MAKE) mutation

bc-check:
	$(DOCKER) sh -c 'git config --global --add safe.directory /app; \
	  LATEST=$$(git describe --tags --abbrev=0 2>/dev/null || true); \
	  if [ -n "$$LATEST" ]; then \
	    composer bc-check -- --from=$$LATEST; \
	  else \
	    echo "No previous tag - skipping BC check"; \
	  fi'

help:
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  install          composer install"
	@echo "  build            full gate (validate + normalize + cs + psalm + test)"
	@echo "  cs               check code style (dry-run)"
	@echo "  cs-fix           fix code style"
	@echo "  psalm            static analysis"
	@echo "  test             run testo"
	@echo "  test-coverage    run testo with coverage"
	@echo "  test-coverage-ci run phpunit coverage for CI artifacts"
	@echo "  mutation         mutation testing"
	@echo "  rector           check rector (dry-run)"
	@echo "  rector-fix       apply rector fixes"
	@echo "  normalize        normalize composer.json"
	@echo "  require-checker  check composer dependencies"
	@echo "  update-deps      composer update + normalize"
	@echo "  bc-check         check backward compatibility against latest tag"
	@echo "  release-check    build + rector + bc-check + mutation"
	@echo "  docs-api         reflect the API of all five packages (needs the monorepo siblings)"
	@echo "  docs-build       build the documentation site (runs docs-api first)"
	@echo "  docs-dev         serve the documentation site locally"
	@echo "  docs-links       check external links on every page"
	@echo "  docs-vale        lint the hand-written pages"

# The documentation site (docs/). Node runs on the host; the reflection pass
# runs in the composer:2 image with the whole monorepo mounted, because it
# reads the four sibling packages next to this one.
docs-api:
	bash docs/scripts/reflect.sh

docs-build: docs-api
	cd docs && npm ci && npm run docs:build

docs-dev: docs-api
	cd docs && npm run docs:dev

docs-links:
	node docs/scripts/check-links.mjs

docs-vale:
	docker run --rm -v "$(PWD)":/w -w /w --entrypoint sh jdkato/vale:v3.9.1 -c 'vale sync >/dev/null && vale docs/index.md docs/guide docs/integration.md'

audit-package:
	@if [ -f ../bin/package-audit ]; then bash ../bin/package-audit "$(CURDIR)"; else echo "package-audit: available only inside the monorepo"; fi
