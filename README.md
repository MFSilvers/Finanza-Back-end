# Finanze App - Backend

Backend API RESTful per l'applicazione Finanze App, sviluppato in PHP nativo.

## Tecnologie

- PHP 8+
- PDO (MySQL/PostgreSQL)
- JWT per autenticazione
- API RESTful

## Installazione

### Requisiti

- PHP 8.0 o superiore
- Estensione PDO abilitata
- Estensione PDO MySQL o PostgreSQL (a seconda del database)
- Server web (Apache/Nginx) o PHP built-in server

### Setup Locale

1. Clona il repository
2. Configura le variabili d'ambiente necessarie
3. Avvia il server:

```bash
php -S localhost:8000
```

## Deploy su Railway

### Setup Railway

1. Crea un account su [Railway](https://railway.app)
2. Crea un nuovo progetto
3. Aggiungi un servizio PHP
4. Connetti il repository GitHub

### Database su Railway

Railway offre database MySQL e PostgreSQL. Puoi:

1. Aggiungere un servizio database nel progetto Railway
2. Usare le variabili d'ambiente automaticamente fornite
3. Oppure connettere un database esterno (es. Supabase)

### Build e Deploy

Railway rileverà automaticamente il progetto PHP e lo deployerà. Assicurati che:

- Il file `index.php` sia nella root
- Il database sia accessibile da Railway

## API Endpoints

Tutti gli endpoint sono prefissati con `/api`

### Autenticazione

- `POST /api/auth/register` - Registra nuovo utente
- `POST /api/auth/login` - Login utente
- `GET /api/auth/me` - Dati utente corrente

### Categorie

- `GET /api/categories` - Lista categorie
- `POST /api/categories` - Crea categoria

### Transazioni

- `GET /api/transactions` - Lista transazioni (con filtri)
- `POST /api/transactions` - Crea transazione
- `PUT /api/transactions/{id}` - Aggiorna transazione
- `DELETE /api/transactions/{id}` - Elimina transazione

### Statistiche

- `GET /api/statistics` - Statistiche aggregate

## Sicurezza

- Le password sono hashate con `password_hash()` (bcrypt)
- I token JWT hanno scadenza
- Le credenziali database non sono nel codice
- Usa HTTPS in produzione

## Note

- Il backend supporta sia MySQL che PostgreSQL

