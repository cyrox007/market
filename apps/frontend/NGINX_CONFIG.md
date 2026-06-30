# Конфигурация Nginx для продакшена

## Проблема с /storage

Если изображения из `/storage/*` не открываются, проблема в конфигурации nginx location для storage.

## Правильная конфигурация Nginx

### Вариант 1: Laravel и React SSR на одном домене

```nginx
server {
    listen 80;
    server_name demo1.site.zone;
    
    root /home/test/web/demo1.site.zone/public_html/services/backend/public;
    index index.php index.html;

    # Location для Laravel storage
    location /storage {
        alias /home/test/web/demo1.site.zone/public_html/services/backend/storage/app/public;
        try_files $uri $uri/ =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Location для статических файлов React (assets)
    location /assets {
        alias /home/test/web/demo1.site.zone/public_html/apps/frontend/dist/client/assets;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Location для API Laravel
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Location для всех остальных запросов - проксируем на SSR сервер
    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # PHP для Laravel API
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Вариант 2: Если storage находится в другом месте

Проверьте, где находится симлинк `public/storage`:

```bash
ls -la /home/test/web/demo1.site.zone/public_html/services/backend/public/storage
```

Он должен указывать на:
```
/home/test/web/demo1.site.zone/public_html/services/backend/storage/app/public
```

Если симлинк не создан, создайте его:

```bash
cd /home/test/web/demo1.site.zone/public_html/services/backend
php artisan storage:link
```

### Вариант 3: Минимальная конфигурация для /storage

Если у вас уже есть конфигурация nginx, просто добавьте:

```nginx
location /storage {
    alias /home/test/web/demo1.site.zone/public_html/services/backend/storage/app/public;
    try_files $uri $uri/ =404;
    expires 30d;
    add_header Cache-Control "public, immutable";
}
```

**Важно:** 
- Путь должен быть **абсолютным** (начинаться с `/`)
- Убедитесь, что директория существует и доступна для чтения
- Проверьте права доступа: `chmod -R 755 storage/app/public`

## Проверка конфигурации

1. Проверьте синтаксис nginx:
```bash
sudo nginx -t
```

2. Перезагрузите nginx:
```bash
sudo systemctl reload nginx
# или
sudo service nginx reload
```

3. Проверьте, что файл доступен:
```bash
curl -I https://demo1.site.zone/storage/56/sovremennyi-interer-0002169526-preview.jpg
```

Должен вернуться статус `200 OK`.

## Отладка

Если файлы все еще не открываются:

1. Проверьте путь к storage:
```bash
ls -la /home/test/web/demo1.site.zone/public_html/services/backend/storage/app/public/56/
```

2. Проверьте логи nginx:
```bash
sudo tail -f /var/log/nginx/error.log
```

3. Проверьте права доступа:
```bash
ls -la /home/test/web/demo1.site.zone/public_html/services/backend/storage/app/public/
```

4. Убедитесь, что файл существует:
```bash
test -f /home/test/web/demo1.site.zone/public_html/services/backend/storage/app/public/56/sovremennyi-interer-0002169526-preview.jpg && echo "File exists" || echo "File not found"
```
