# Fighthouse — Gestione utenti, spese e wallet cashback 5%

Data: 2026-08-16

## Contesto e obiettivo

Backend con dashboard amministrativa per censire utenti (clienti), registrare le
loro spese e accreditare automaticamente un cashback del 5% su un wallet
personale. Il wallet accumulato può essere scaricato in blocchi da 25€ come
sconto su spese future. L'utente finale può consultare da cellulare, tramite
numero di telefono, il proprio saldo e lo storico movimenti. Il tutto deve
essere deployabile su un hosting condiviso classico (cPanel, FTP, niente
SSH/Docker necessari in produzione).

## Stack tecnico

- **Backend/API**: PHP 8.1+, [Slim 4](https://www.slimframework.com/) per il
  routing REST, [Eloquent](https://github.com/illuminate/database) (illuminate/database
  standalone) come ORM/query builder, MySQL come database.
- **Frontend**: Vue 3 + Vite, SPA compilata staticamente (`npm run build`) e
  caricata come asset statico sullo stesso dominio dell'API.
- **Deploy**: build locale (Composer per il backend, npm/Vite per il
  frontend), upload via FTP di `dist/` (SPA) e della cartella API (incluso
  `vendor/`) sull'hosting. Non serve accesso SSH né Composer/Node lato server.

## Modello dati (MySQL)

### `admins`
Singolo accesso condiviso all'area amministrativa (in tabella, non
hardcoded, per poter cambiare la password senza toccare il codice).

| campo | tipo | note |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| username | VARCHAR(255) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt/password_hash() |
| created_at | DATETIME | |

### `users`
Gli utenti/clienti censiti.

| campo | tipo | note |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| nome | VARCHAR(255) | |
| cognome | VARCHAR(255) | |
| telefono | VARCHAR(32) UNIQUE | usato anche come identificativo di login pubblico |
| email | VARCHAR(255) | |
| data_nascita | DATE | |
| wallet_balance | DECIMAL(10,2) DEFAULT 0 | cache del saldo, ricavabile anche da SUM(wallet_transactions.importo) |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### `expenses`
Le spese registrate per un utente.

| campo | tipo | note |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| user_id | INT FK -> users.id | |
| importo | DECIMAL(10,2) | importo lordo della spesa, > 0 |
| wallet_usato | DECIMAL(10,2) DEFAULT 0 | quanto wallet è stato scalato su questa spesa |
| data_spesa | DATE | |
| note | VARCHAR(500) NULL | opzionale |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### `wallet_transactions`
Ledger immutabile dei movimenti wallet: ogni accredito, scarico o rettifica.
Fonte di verità per lo storico, anche quando una spesa viene modificata o
cancellata.

| campo | tipo | note |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| user_id | INT FK -> users.id | |
| expense_id | INT FK -> expenses.id NULL | nullable: una rettifica di storno resta anche se la spesa viene eliminata |
| tipo | ENUM('accredito','scarico','rettifica') | |
| importo | DECIMAL(10,2) | con segno: positivo = accredito, negativo = scarico/rettifica negativa |
| saldo_dopo | DECIMAL(10,2) | snapshot del saldo progressivo dopo il movimento |
| created_at | DATETIME | |

## Logica di business

### Calcolo cashback
Il 5% si accredita **sempre sull'importo lordo della spesa**, indipendentemente
da quanto wallet viene eventualmente usato per pagarla nella stessa
transazione.

### Soglie di scarico
Il wallet si può scaricare **solo in blocchi da 25€, quando il saldo raggiunge
esattamente quella soglia**. L'importo massimo scaricabile in un dato momento
è `floor(wallet_balance / 25) * 25`. Non sono ammessi importi liberi o
frazionari.

### Inserimento spesa (flusso admin)
1. L'admin seleziona l'utente e inserisce `importo` (obbligatorio, > 0),
   `data_spesa`, `note` (opzionale).
2. Il sistema calcola la soglia massima scaricabile ora
   (`floor(wallet_balance / 25) * 25`). Se > 0, il form mostra l'opzione "usa
   wallet" con quell'importo come unico valore selezionabile.
3. Se l'admin conferma l'uso del wallet, `wallet_usato` = quella soglia,
   altrimenti 0.
4. In un'unica transazione DB:
   - crea la riga in `expenses`
   - crea una `wallet_transactions` di tipo `accredito` = `importo * 0.05`
   - se `wallet_usato > 0`, crea una `wallet_transactions` di tipo `scarico` =
     `-wallet_usato`
   - aggiorna `users.wallet_balance` di conseguenza

### Modifica spesa
Ricalcola la differenza tra vecchio e nuovo accredito/scarico associati a
quella spesa e crea una `wallet_transactions` di tipo `rettifica` per la
differenza netta, aggiornando `wallet_balance`. Se l'operazione porterebbe
`wallet_balance` sotto zero, viene rifiutata con errore 409 (validazione di
sicurezza; nella pratica non dovrebbe mai accadere perché scarico e accredito
di una spesa sono sempre gestiti insieme).

### Cancellazione spesa
Crea una `wallet_transactions` di tipo `rettifica` che annulla sia
l'accredito sia l'eventuale scarico collegati a quella spesa (importo netto
di storno), poi elimina la riga da `expenses`. Il movimento di storno resta
visibile nello storico wallet dell'utente anche dopo la cancellazione della
spesa originale.

## API (Slim, sotto `/api`)

**Auth**
- `POST /api/admin/login` — `{username, password}` → sessione cookie
- `POST /api/admin/logout`
- `POST /api/wallet/lookup` — `{telefono}` → dati utente + saldo + storico
  (accesso pubblico, nessuna sessione richiesta)

**Admin (richiede sessione attiva)**
- `GET /api/users` — elenco/ricerca utenti
- `POST /api/users` — crea utente
- `GET /api/users/{id}` — dettaglio utente
- `PUT /api/users/{id}` — modifica utente
- `DELETE /api/users/{id}` — elimina utente
- `GET /api/users/{id}/wallet` — saldo + storico transazioni wallet
- `GET /api/expenses?user_id=&from=&to=` — elenco spese con filtri
- `POST /api/expenses` — crea spesa (applica logica di accredito/scarico)
- `PUT /api/expenses/{id}` — modifica spesa (applica logica di rettifica)
- `DELETE /api/expenses/{id}` — elimina spesa (applica logica di storno)

## Frontend (Vue 3 + Vite)

- `/admin/login` — form di login
- `/admin/users` — elenco utenti, ricerca, form censimento (nome, cognome,
  telefono, email, data di nascita)
- `/admin/users/:id` — dettaglio utente: saldo wallet, storico movimenti,
  storico spese, azione "nuova spesa"
- `/admin/expenses/new` — form inserimento spesa (con logica soglia wallet)
- `/wallet` — pagina pubblica mobile-first: campo telefono → saldo e storico
  in sola lettura
- Router guard su `/admin/*`: verifica la sessione admin, redirect a login se
  assente
- Stato applicativo: Pinia (o semplici composables), niente Vuex

## Validazione ed errori

- `telefono`: univoco, formato validato lato API
- `importo` spesa: numero > 0
- `data_nascita`: data valida
- Errori API restituiti come JSON `{error: "messaggio"}` con status HTTP
  appropriato (400 validazione, 404 non trovato, 409 conflitto/operazione non
  consentita), mostrati come toast/alert nella SPA

## Deploy

1. In locale: `composer install --no-dev` per il backend, `npm run build` per
   il frontend.
2. Upload via FTP: cartella `dist/` (SPA compilata) nella document root del
   dominio; cartella `api/` (codice Slim + `vendor/`) a fianco.
3. `.htaccess` nella document root: instrada le richieste `/api/*` verso lo
   script di bootstrap di Slim; per tutte le altre rotte, fallback su
   `index.html` (necessario per il client-side routing di Vue).
4. Il database MySQL viene creato via pannello cPanel; le migrazioni Eloquent
   vengono eseguite una tantum (script CLI dedicato, eseguibile anche senza
   SSH tramite un endpoint protetto una tantum o eseguendo lo script in
   locale contro il DB remoto se esposto).

## Testing

- **PHPUnit** sulla logica di business critica: calcolo dell'accredito 5%,
  calcolo delle soglie di scarico, rettifiche su modifica/cancellazione
  spesa. È la parte con più rischio di bug numerici e va coperta con priorità
  alta.
- Test manuali/E2E leggeri sulla SPA, vista la scala contenuta del progetto.

## Note di sicurezza

Il login pubblico alla pagina `/wallet` avviene **solo tramite numero di
telefono**, senza password: chiunque conosca il numero di un utente può
vederne saldo e storico. Scelta esplicitamente richiesta, accettabile per un
contesto informale a bassa scala. Estendibile in futuro con un PIN a 4 cifre
senza modificare l'architettura di base.

## Fuori scope (per questa prima versione)

- Multi-tenancy (più attività/organizzazioni sullo stesso sistema)
- Notifiche SMS/email automatiche
- Più account amministratori distinti (per ora accesso admin singolo e
  condiviso)
- Import/export massivo dati
