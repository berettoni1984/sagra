# 🍽️ Sagra

**Sagra** è un’applicazione web realizzata in **Laravel**, **Filament** e **Horizon**, pensata per la gestione digitale di una **sagra** o evento gastronomico.  
Permette di gestire più **file di ordinazione**, organizzare prodotti e ingredienti, e tenere sotto controllo la disponibilità in tempo reale.

---

## 🚀 Funzionalità principali

- 🔢 **Gestione delle file di ordinazione**
    - Puoi creare una o più file indipendenti (es. “Fila 1”, “Fila 2”, ecc.).
    - Ogni ordine riceve un **numero progressivo per fila**.

- 🧾 **Gestione ordini**
    - Creazione rapida e intuitiva di ordini.
    - Storico degli ordini e statistiche base.

- 🍔 **Gestione prodotti e ingredienti**
    - Attiva o disattiva i prodotti in base alla disponibilità.
    - Associa ingredienti a ciascun prodotto.
    - Ogni ingrediente può avere una **quantità limitata**.
    - La disponibilità dei prodotti è calcolata automaticamente in base agli ingredienti.

- ⚙️ **Pannello amministrativo**
    - Interfaccia moderna e reattiva realizzata con **[Filament](https://filamentphp.com/)**.
    - Code e processi gestiti tramite **[Laravel Horizon](https://laravel.com/docs/horizon)**.
    - Pannello statistiche per monitorare ordini e disponibilità in tempo reale.

---

## 🧱 Stack Tecnologico

| Componente        | Descrizione |
|-------------------|-------------|
| **Laravel 12+**   | Framework backend PHP principale |
| **Filament 3**    | Admin panel e interfaccia di gestione |
| **Horizon**       | Supervisione delle queue e job |
| **Laravel Sail**  | Ambiente di sviluppo Docker |
| **MySQL / Redis** | Database e sistema cache/queue |
| **TailwindCSS**   | Styling del pannello Filament |

---

## ⚙️ Installazione (con Laravel Sail)

Assicurati di avere **Docker** e **Docker Compose** installati sul tuo sistema.

```bash
# Clona il repository
git clone https://github.com/berettoni1984/sagra.git
cd sagra

# Installa le dipendenze PHP
composer install

# Copia il file .env e configura le variabili (DB, APP_URL, ecc.)
cp .env.example .env

# Avvia Sail (costruirà i container al primo avvio)
./vendor/bin/sail up -d

# Genera la chiave dell'app
./vendor/bin/sail artisan key:generate

# Esegui le migrazioni e i seeder iniziali
./vendor/bin/sail artisan migrate --seed

# Installa e compila le risorse frontend (opzionale, se usi Filament)
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

L’applicazione sarà disponibile su:  
👉 **http://localhost**

---

## ⚡ Horizon e code

Per avviare **Laravel Horizon**, esegui:

```bash
./vendor/bin/sail artisan horizon
```

Puoi accedere al pannello Horizon all’indirizzo:  
👉 `http://localhost/horizon`

---

## 🔐 Accesso al pannello Filament

Il pannello di amministrazione **Filament** è disponibile su:  
👉 `http://localhost/`

Per creare un utente amministratore:

```bash
./vendor/bin/sail artisan make:filament-user
```

Segui le istruzioni per inserire **nome**, **email** e **password**.

---

## 🧩 Comandi utili

Per comodità, puoi creare un alias in bash o zsh:

```bash
alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'
```

In questo modo puoi usare semplicemente `sail` invece di `./vendor/bin/sail`.

| Descrizione | Comando |
|--------------|----------|
| Avvia l’ambiente | `sail up -d` |
| Ferma i container | `sail down` |
| Accedi al container | `sail shell` |
| Esegui le migrazioni | `sail artisan migrate` |
| Lancia Horizon | `sail artisan horizon` |
| Esegui i test | `sail test` |

---

## 📦 Deploy (produzione)

Per il deploy su un server:

1. Configura PHP, MySQL/PostgreSQL e Redis.
2. Esegui le migrazioni con `php artisan migrate --force`.
3. Avvia Horizon tramite **Supervisor**.
4. Aggiungi il cron per i comandi schedulati:

   ```bash
   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
   ```

---

## 🧠 Roadmap / Idee future

- [ ] Gestione prenotazioni
- [ ] Dashboard real-time per la cucina
- [ ] Statistiche dettagliate per prodotto e fascia oraria
- [ ] Stampa degli ordini
- [ ] Supporto per postazioni multiple (casse o cucine separate)

---

## 🤝 Contributi

Contributi e suggerimenti sono benvenuti!  
Apri una **issue** o invia una **pull request** per migliorare il progetto.

---

## 📄 Licenza

Distribuito sotto licenza.  
Consulta il file [LICENSE](LICENSE) per maggiori dettagli.

---

## 👨‍💻 Autore

**Simone Berettoni**  
GitHub: [@berettoni1984](https://github.com/berettoni1984)
