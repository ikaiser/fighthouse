# Fighthouse — Coach/Atleta con doppio wallet 5%

Data: 2026-08-17

## Contesto e obiettivo

Gli utenti finora chiamati "utenti" sono in realtà **atleti**, e ogni atleta ha un
**coach** assegnato (relazione Coach 1—N Atleti, obbligatoria). Ogni spesa
registrata per un atleta accredita il 5% dell'importo lordo **sia all'atleta
sia al suo coach**, ciascuno con un proprio wallet indipendente. Il coach non
ha (per ora) un meccanismo di scarico: il suo saldo matura e basta. Modifiche
o cancellazioni di una spesa propagano la rettifica a entrambi i wallet.

## Stack e approccio

Nessun cambio di stack. Per il wallet del coach si usa una tabella dedicata
`coach_wallet_transactions`, parallela a `wallet_transactions` ma più
semplice (solo `accredito`/`rettifica`, mai `scarico`), invece di rendere
polimorfica la tabella esistente — evita di riscrivere/ri-verificare la
logica di `WalletService` già testata per l'atleta.

## Modello dati

### `coaches`
| campo | tipo | note |
|---|---|---|
| id | SERIAL PK | |
| nome | VARCHAR(255) | |
| cognome | VARCHAR(255) | |
| telefono | VARCHAR(32) UNIQUE | usato anche per il lookup pubblico wallet |
| email | VARCHAR(255) | |
| wallet_balance | DECIMAL(10,2) DEFAULT 0 | |
| created_at, updated_at | TIMESTAMP | |

### `athletes` (rinominata da `users`)
Stessi campi già esistenti (`nome`, `cognome`, `telefono` univoco, `email`,
`data_nascita`, `wallet_balance`) + `coach_id INT NOT NULL FK -> coaches.id
ON DELETE RESTRICT` (non si può eliminare un coach con atleti ancora
assegnati).

### `coach_wallet_transactions`
| campo | tipo | note |
|---|---|---|
| id | SERIAL PK | |
| coach_id | INT FK -> coaches.id | |
| expense_id | INT NULL | riferimento alla spesa che ha generato il movimento (nessun vincolo FK enforced, stesso motivo di `wallet_transactions.expense_id`) |
| tipo | VARCHAR(20) | `accredito` o `rettifica` (mai `scarico`) |
| importo | DECIMAL(10,2) | con segno |
| saldo_dopo | DECIMAL(10,2) | |
| created_at | TIMESTAMP | |

`expenses` e `wallet_transactions` restano invariate, tranne il rinominare
concettualmente la FK `user_id` → resta `user_id` a livello di colonna DB per
minimizzare le migrazioni (il nome colonna non è visibile all'utente finale;
solo `App\Models\Athlete` cambia nome a livello applicativo).

## Migrazione dei dati esistenti

Gli atleti già censiti in locale (dati di test) vengono assegnati a un coach
placeholder creato dalla migrazione stessa, poi il vincolo `NOT NULL` viene
applicato. Questo avviene dentro la migrazione Phinx con `up()`/`down()`
espliciti (non `change()`), perché include una manipolazione dati oltre allo
schema.

## Logica di business

`WalletService::createExpense/updateExpense/deleteExpense` restano il punto
unico di ingresso. Dopo aver registrato il movimento sull'atleta, la stessa
funzione registra un movimento identico (stesso tipo, stesso importo) sul
wallet del coach collegato all'atleta, tramite un nuovo helper
`recordCoachTransaction`. Non esiste `scarico` per il coach: se in futuro
servirà, si aggiungerà come estensione separata.

## API

- CRUD coach: `GET/POST /api/coaches`, `GET/PUT/DELETE /api/coaches/{id}`
  (protette da sessione admin, come le rotte atleti)
- `GET /api/coaches/{id}/wallet` — saldo + storico movimenti coach (admin)
- CRUD atleti: stesse rotte di prima ma su `/api/athletes` invece di
  `/api/users`; il payload di creazione/modifica richiede `coach_id`
- `POST /api/wallet/lookup` esteso: cerca il telefono prima tra gli atleti,
  poi tra i coach; la risposta include un campo `type: 'athlete' | 'coach'`
  così la SPA sa come etichettare i dati mostrati

## Frontend

- Rinomina completa "Utenti" → "Atleti" in menu, testi, rotte SPA
  (`/admin/users` → `/admin/athletes`), componenti (`UsersListView` →
  `AthletesListView`, ecc.)
- Nuova sezione admin "Coach": elenco + modale di creazione/modifica (stesso
  pattern della modale atleti)
- Form atleta (creazione/modifica): campo obbligatorio "Coach" con select
  popolata dai coach esistenti
- `/wallet` pubblica: la stessa pagina gestisce sia atleti sia coach in base
  al campo `type` restituito dal lookup

## Fuori scope (per questa versione)

- Scarico del wallet coach
- Coach multipli per atleta, o atleta senza coach
- Riassegnazione massiva di atleti a un altro coach dall'interfaccia (si fa
  comunque modificando l'atleta uno alla volta dal form)
