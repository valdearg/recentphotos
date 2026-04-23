build:
	npm install
	npm run build
	composer dump-autoload

enable:
	sudo -u www-data php /var/www/nextcloud/occ app:enable recentphotos

migrate:
	sudo -u www-data php /var/www/nextcloud/occ migrations:migrate recentphotos

rebuild-index:
	sudo -u www-data php /var/www/nextcloud/occ recentphotos:rebuild-index
