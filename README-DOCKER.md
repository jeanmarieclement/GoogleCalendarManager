# Docker Setup per Google Calendar Manager

Questo documento spiega come utilizzare Docker per testare la libreria Google Calendar Manager.

## Prerequisiti

- Docker installato sul tuo sistema
- Docker Compose installato sul tuo sistema

## Avvio dell'ambiente di test

1. Assicurati di essere nella directory principale del progetto:

```bash
cd /path/to/classGoogleCalendar
```

2. Avvia i container Docker:

```bash
docker-compose -f compose.yml up -d
```

3. Accedi all'applicazione di esempio tramite browser:

```
http://localhost:8080
```

## Struttura dell'ambiente Docker

- **App Container**: Un server Apache con PHP 7.4 che esegue l'applicazione
- Il container monta la directory del progetto come volume, quindi le modifiche ai file sono immediatamente visibili
- La directory `examples` è configurata come document root di Apache

## Configurazione

- Il file `config/calendar-config.php` viene creato automaticamente dal template se non esiste
- Le directory per token, logs e cache vengono create automaticamente con i permessi corretti

## Risoluzione dei problemi

Se riscontri problemi con l'autenticazione OAuth:

1. Verifica che le credenziali OAuth nel file `config/calendar-config.php` siano corrette
2. Assicurati che l'URI di reindirizzamento nelle impostazioni del progetto Google Cloud sia impostato su `urn:ietf:wg:oauth:2.0:oob`
3. Controlla i log di Apache:

```bash
docker logs google-calendar-manager
```

## Arresto dell'ambiente

Per fermare i container:

```bash
docker-compose -f compose.yml down
```

Per fermare e rimuovere tutti i container, le reti e i volumi:

```bash
docker-compose -f compose.yml down -v
```
