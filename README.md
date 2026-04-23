# Recent Photos v9

This package extends v8 with media support:

- GIF support with direct-file playback in the viewer
- Video support with inline HTML5 playback in the viewer
- `media_type` stored in the index
- media badges in the grid
- viewer now handles image / gif / video appropriately

## Install / update

```bash
cd /var/www/nextcloud/apps/recentphotos
rm -rf node_modules package-lock.json js/
npm install
npm run build
composer dump-autoload
sudo -u www-data php8.2 /var/www/nextcloud/occ app:disable recentphotos
sudo -u www-data php8.2 /var/www/nextcloud/occ app:enable recentphotos
sudo -u www-data php8.2 /var/www/nextcloud/occ upgrade
sudo -u www-data php8.2 /var/www/nextcloud/occ recentphotos:rebuild-index
```
# recentphotos
