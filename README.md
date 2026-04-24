# Recent Photos

Alternative for the NextCloud Photos module. 

- Uses own DB index
- Gives additional sorting options (Date added, Created, Modified, Name, Size)
- Gives additional display modes (Infinite Scroll or Pagination)
- Fixes issues with infinite scroll present in Photos module.
- Returns option to open path to image in a new tab.
- Replaces Viewer for media, returns the ability to click off image.

All AI generated, use at own risk

## Install / update

```bash
cd /var/www/nextcloud/apps
git clone git@github.com:valdearg/recentphotos.git
cd recentphotos
npm install
npm run build
composer dump-autoload
sudo -u www-data php8.2 /var/www/nextcloud/occ app:disable recentphotos
sudo -u www-data php8.2 /var/www/nextcloud/occ app:enable recentphotos
sudo -u www-data php8.2 /var/www/nextcloud/occ upgrade
sudo -u www-data php8.2 /var/www/nextcloud/occ recentphotos:rebuild-index
```

With Make:

```
Available targets:
    make build            - Build frontend
    make dev              - Dev build
    make watch            - Watch mode
    make clean            - Remove node modules and build

    make autoload         - Composer autoload refresh
    make enable           - Enable app
    make disable          - Disable app
    make restart          - Disable + Enable app
    make upgrade          - Run Nextcloud upgrade

    make rebuild-index    - Rebuild full index
    make rebuild-user USER_ID=valdearg
    make rebuild-path USER_ID=valdearg PATH='files/Photos/Pixiv'

    make quick-build      - Build + reload app
    make deploy           - Build + upgrade + reload
```
# Screenshots
## Grid view:
<img width="1918" height="920" alt="image" src="https://github.com/user-attachments/assets/81fae78f-6b51-41df-85e3-0e877083c921" />

## Slideshow mode:
<img width="1919" height="918" alt="image" src="https://github.com/user-attachments/assets/7a8a7f5b-8f69-49f8-887c-9edabb084abb" />

