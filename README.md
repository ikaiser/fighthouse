# Fighthouse

Backend Slim 4 + Eloquent + PostgreSQL, SPA Vue 3 + Vite. Gestione utenti,
spese e wallet cashback 5% con scarico a soglie di 25€. Vedi
`docs/superpowers/specs/2026-08-16-fighthouse-wallet-design.md` per il design
completo.

## Sviluppo locale

1. `cp api/.env.example api/.env` e inserire le credenziali del PostgreSQL locale.
2. `cd api && composer install`
3. Creare il database: `createdb fighthouse` (o `psql postgres -c "CREATE DATABASE fighthouse;"`)
4. `vendor/bin/phinx migrate`
5. `php bin/seed-admin.php admin <password>`
6. `php -S 127.0.0.1:8080 -t public` (in un terminale)
7. `cd web && npm install && npm run dev` (in un altro terminale)
8. Aprire `http://localhost:5173/admin/login` (area admin) e
   `http://localhost:5173/wallet` (pagina pubblica). Il dev server di Vite
   proxya automaticamente `/api/*` verso `http://127.0.0.1:8080`.

### Eseguire i test

```
cd api && vendor/bin/phpunit
```

I test usano SQLite in-memory, non serve un database reale per farli girare.

## Deploy su hosting condiviso (cPanel)

1. In locale: `cd api && composer install --no-dev` (genera `vendor/` senza dipendenze di sviluppo).
2. In locale: `cd web && npm run build` (genera `web/dist/`).
3. Creare il database PostgreSQL dal pannello cPanel e aggiornare `api/.env` con le credenziali reali.
4. Eseguire le migrazioni contro il DB di produzione: `vendor/bin/phinx migrate` (da locale, se il DB è raggiungibile dall'esterno, oppure da un accesso SSH temporaneo se disponibile).
5. Eseguire `php bin/seed-admin.php <username> <password>` per creare il primo account admin.
6. Via FTP, caricare nella document root del dominio:
   - il contenuto di `web/dist/` (i file compilati della SPA)
   - la cartella `api/` (incluso `vendor/`, escluso `.env` se già configurato diversamente lato server)
   - `deploy/.htaccess` rinominato in `.htaccess` nella document root
7. Verificare `https://tuodominio.it/api/health` → `{"status":"ok"}` e `https://tuodominio.it/admin/login`.

**Nota:** verificare che l'hosting scelto supporti effettivamente PostgreSQL
(molti hosting condivisi cPanel offrono solo MySQL/MariaDB) prima del deploy
in produzione.
