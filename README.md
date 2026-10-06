# Circle

Vue frontend with a PHP REST API and MySQL database.

## Local setup

1. Create the schema: `mysql -u root -p < database/schema.sql`.
2. Copy `api/config.example.php` to `api/config.php` and fill in MySQL and JWT settings.
3. Copy `.env.example` to `.env` and install frontend packages with `npm install`.
4. Run PHP API and frontend together with `npm run dev`.

The frontend uses `VITE_API_URL`. Database credentials are read only from `api/config.php`.

## Production

`npm run build` creates the frontend in `dist/` and copies the PHP API to `dist/api/`. Assets are generated for the `/social-vue/` deployment path, and the frontend uses the relative `./api` endpoint.

For security, `api/config.php` is never copied into `dist`. After uploading `dist/` to a PHP 8.0+ host with PDO MySQL, create `dist/api/config.php` from `dist/api/config.example.php` directly on the server.
