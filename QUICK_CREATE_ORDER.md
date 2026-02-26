# Creazione Rapida Ordini

## Descrizione
La pagina di **Creazione Rapida** è un'interfaccia semplificata e veloce per creare ordini in modo efficiente.

## Come Accedere
1. Vai alla lista degli ordini
2. Clicca sul pulsante **"Creazione Rapida"** (⚡ icona fulmine, colore arancione)

Oppure naviga direttamente a: `/admin/orders/quick-create`

## Funzionalità

### 1. Selezione Coda
- La coda predefinita viene selezionata automaticamente
- Puoi cambiare la coda usando il menu a tendina in alto

### 2. Aggiunta Prodotti
Ci sono due modi per aggiungere prodotti:

#### A. Click sul Pulsante
- Clicca su qualsiasi prodotto per aggiungerlo all'ordine
- Se il prodotto è già nell'ordine, la quantità aumenta di 1

#### B. Tasti Rapidi (1-9)
- Premi i tasti numerici **1-9** sulla tastiera
- Ogni numero corrisponde ai primi 9 prodotti mostrati
- I numeri sono visualizzati in un badge blu nell'angolo superiore sinistro di ogni prodotto

### 3. Gestione Quantità
Una volta aggiunti i prodotti:
- **➖ (Meno)**: Diminuisce la quantità di 1 (se arriva a 0, rimuove il prodotto)
- **➕ (Più)**: Aumenta la quantità di 1
- **🗑️ (Cestino)**: Rimuove completamente il prodotto dall'ordine

### 4. Note e Opzioni
- **Note ordine**: Campo di testo per aggiungere note all'intero ordine
- **Gratuito**: Checkbox per impostare l'ordine come gratuito (se abilitato nella configurazione)

### 5. Creazione Ordine
- Clicca su **"Crea Ordine - Alt + s"** nell'intestazione
- Oppure premi **Alt + S** sulla tastiera
- L'ordine viene creato e si apre automaticamente la pagina di stampa

## Vantaggi Rispetto al Form Classico

| Creazione Classica | Creazione Rapida |
|-------------------|------------------|
| Form con repeater complesso | Interfaccia visuale con pulsanti |
| Selezione prodotto da dropdown | Click diretto sui prodotti |
| Aggiunta riga manuale | Aggiunta automatica |
| Più click per ogni prodotto | 1 click per prodotto |
| Nessun tasto rapido numerico | Tasti 1-9 per prodotti |
| Layout compatto | Layout espanso e chiaro |

## Note Tecniche
- I prodotti esauriti sono disabilitati e visualizzati in rosso
- Lo stock viene aggiornato automaticamente alla creazione
- Gli ingredienti vengono decrementati in base alle ricette
- Il numero ordine viene incrementato automaticamente dalla coda selezionata

## Screenshot delle Funzionalità
- Badge numerici sui prodotti (1-9) per tasti rapidi
- Indicatore stock su ogni prodotto
- Contatori quantità con pulsanti +/- 
- Totale ordine sempre visibile
- Design responsive per tablet e desktop

