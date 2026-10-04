#!/bin/sh
# Met a jour uniquement le code. Ne touche pas aux donnees utilisateurs.
set -e
GIT_DIR="$HOME/dlpwaits-git"
SITE_DIR="$HOME/www"
if [ ! -d "$GIT_DIR/.git" ]; then
  git clone https://github.com/mattxhieu-cloud/dlpwaits.git "$GIT_DIR"
fi
git -C "$GIT_DIR" pull --ff-only
rsync -a --delete \
  --exclude '.git/' \
  --exclude 'connect2/users.json' \
  --exclude 'Waits/user_alerts.json' \
  --exclude 'Waits/show_alerts.json' \
  --exclude 'Waits/locks/' \
  --exclude 'chat/' \
  --exclude 'analytics/' \
  --exclude 'API/' \
  --exclude 'brand.json' \
  --exclude 'uploads/' \
  "$GIT_DIR/" "$SITE_DIR/"
