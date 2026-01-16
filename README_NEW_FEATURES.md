# Nieuwe Functionaliteiten - BTW, Kosten Baten Overzicht en Balans

## Overzicht
Er zijn drie nieuwe pagina's toegevoegd aan het boekhoudsysteem:

1. **BTW per Kwartaal** (`btw_kwartaal.php`) - BTW-berekeningen per kwartaal
2. **Kosten Baten Overzicht** (`profit_loss.php`) - Winst/verlies overzicht
3. **Balans** (`balans.php`) - Balansoverzicht

## Database Migratie
Om BTW-functionaliteit te gebruiken, moet de database worden bijgewerkt:

### Optie 1: Nieuwe database aanmaken (aanbevolen voor nieuwe installaties)
```bash
mysql -u root -p < schema_vat.sql
```

### Optie 2: Bestaande database bijwerken
```bash
mysql -u root -p boekhouden < migrate_vat.sql
```

### Optie 3: Handmatige migratie
Voer deze SQL-commando's uit in MySQL:
```sql
ALTER TABLE transactions 
ADD COLUMN vat_percentage DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN vat_included BOOLEAN DEFAULT FALSE,
ADD COLUMN vat_deductible BOOLEAN DEFAULT FALSE;
```

## Nieuwe Pagina's

### 1. BTW per Kwartaal (`btw_kwartaal.php`)
- Toont BTW-berekeningen per kwartaal
- Berekeningswijze:
  - Af te dragen BTW: BTW over alle inkomsten
  - Voorbelasting BTW: BTW over aftrekbare uitgaven
  - Netto BTW: Verschil tussen beide
- Ondersteunt BTW-tarieven: 0%, 9%, 21%
- Filterbaar per jaar en kwartaal

### 2. Kosten Baten Overzicht (`profit_loss.php`)
- Toont winst/verlies overzicht
- Breakdown per kwartaal
- Categorie-analyse
- Filterbaar per jaar

### 3. Balans (`balans.php`)
- Toont balansoverzicht (activa = passiva + eigen vermogen)
- Vereenvoudigde balans met:
  - Activa: liquide middelen, vorderingen, voorraden, vaste activa
  - Passiva: schulden, te betalen belastingen
  - Eigen vermogen: ingesteld kapitaal, winst/verlies
- Filterbaar per datum

## Wijzigingen in Bestaande Pagina's

### Transactie Toevoegen/Bewerken
- Nieuwe BTW-velden toegevoegd:
  - BTW percentage (0%, 9%, 21%)
  - "Bedrag is inclusief BTW" checkbox
  - "BTW is aftrekbaar" checkbox (alleen voor uitgaven)

### Hoofdpagina (`index.php`)
- Navigatiebalk toegevoegd met links naar alle pagina's

## Gebruik

### Stap 1: Database migreren
```bash
# Backup eerst je bestaande database
mysqldump -u root -p boekhouden > backup_boekhouden.sql

# Voer migratie uit
mysql -u root -p boekhouden < migrate_vat.sql
```

### Stap 2: BTW-gegevens invoeren
1. Ga naar bestaande transacties via `index.php`
2. Klik op "Bewerken" bij elke transactie
3. Vul BTW-gegevens in:
   - Selecteer BTW percentage
   - Geef aan of bedrag inclusief BTW is
   - Geef aan of BTW aftrekbaar is (voor uitgaven)

### Stap 3: Nieuwe transacties met BTW
Bij het toevoegen van nieuwe transacties kun je direct BTW-gegevens invullen.

## BTW Berekeningslogica

### Voorbeeld 1: Inkomsten met 21% BTW (inclusief)
- Bedrag: €121
- BTW percentage: 21%
- Inclusief BTW: Ja
- BTW bedrag: €121 - (€121 / 1.21) = €21
- Basisbedrag: €100

### Voorbeeld 2: Uitgaven met 9% BTW (exclusief, aftrekbaar)
- Bedrag: €100
- BTW percentage: 9%
- Inclusief BTW: Nee
- BTW bedrag: €100 × 0.09 = €9
- Totaal: €109
- Voorbelasting BTW: €9 (aftrekbaar)

## Belangrijke Notities

1. **Backup eerst**: Maak altijd een backup van je database voor migratie
2. **BTW-tarieven**: Standaard Nederlandse tarieven (0%, 9%, 21%)
3. **Aftrekbaarheid**: Alleen BTW op zakelijke uitgaven is aftrekbaar
4. **Kwartaalindeling**: Standaard kalenderkwartalen (Q1: jan-mrt, etc.)
5. **Balanscontrole**: De balans moet altijd kloppen (activa = passiva + eigen vermogen)

## Probleemoplossing

### Probleem: BTW-velden niet zichtbaar
**Oplossing**: Controleer of de migratie succesvol is uitgevoerd:
```sql
SHOW COLUMNS FROM transactions;
```

### Probleem: BTW-berekeningen kloppen niet
**Oplossing**: Controleer of:
1. BTW percentage correct is ingesteld
2. "Inclusief BTW" correct is aangevinkt
3. Transactietype (inkomst/uitgave) correct is

### Probleem: Balans klopt niet
**Oplossing**: Controleer of alle transacties zijn ingevoerd en of er geen dubbele of ontbrekende transacties zijn.