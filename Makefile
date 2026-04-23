APP_NAME=recentphotos
NC_PATH=/var/www/nextcloud
PHP=php8.2
USER=www-data

# ----------------------
# NPM / Frontend
# ----------------------

install:
	npm install

build:
	npm run build

dev:
	npm run dev

watch:
	npm run watch

clean:
	rm -rf node_modules js package-lock.json

# ----------------------
# PHP / Nextcloud
# ----------------------

autoload:
	composer dump-autoload

enable:
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ app:enable $(APP_NAME)

disable:
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ app:disable $(APP_NAME)

upgrade:
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ upgrade

restart:
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ app:disable $(APP_NAME)
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ app:enable $(APP_NAME)

# ----------------------
# Indexing
# ----------------------

rebuild-index:
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ recentphotos:rebuild-index

rebuild-user:
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ recentphotos:rebuild-index --user $(USER_ID)

rebuild-path:
	sudo -u $(USER) $(PHP) $(NC_PATH)/occ recentphotos:rebuild-index --user $(USER_ID) --path "$(PATH)"

# ----------------------
# Full workflows
# ----------------------

full-build: install build autoload restart

quick-build: build autoload restart

deploy: build autoload upgrade restart

# ----------------------
# Help
# ----------------------

help:
	@echo ""
	@echo "Available targets:"
	@echo "  make build            - Build frontend"
	@echo "  make dev              - Dev build"
	@echo "  make watch            - Watch mode"
	@echo "  make clean            - Remove node modules and build"
	@echo ""
	@echo "  make autoload         - Composer autoload refresh"
	@echo "  make enable           - Enable app"
	@echo "  make disable          - Disable app"
	@echo "  make restart          - Disable + Enable app"
	@echo "  make upgrade          - Run Nextcloud upgrade"
	@echo ""
	@echo "  make rebuild-index    - Rebuild full index"
	@echo "  make rebuild-user USER_ID=valdearg"
	@echo "  make rebuild-path USER_ID=valdearg PATH='files/Photos/Pixiv'"
	@echo ""
	@echo "  make quick-build      - Build + reload app"
	@echo "  make deploy           - Build + upgrade + reload"
	@echo ""