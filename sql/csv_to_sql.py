#!/usr/bin/env python3
"""
Fixed CSV to SQL converter for kosten.csv specific format
Handles: spaces around commas, European number format, DD-MM-YYYY dates
"""

import csv
import sys
import os
from datetime import datetime

def parse_date(date_str):
    """Parse DD-MM-YYYY dates to YYYY-MM-DD"""
    if not date_str or str(date_str).strip() == '':
        return None
    
    date_str = str(date_str).strip()
    
    # Remove any whitespace
    date_str = date_str.replace(' ', '')
    
    # Try common date formats
    formats = [
        '%d-%m-%Y',      # 6-4-2025
        '%d/%m/%Y',      # 6/4/2025
        '%d.%m.%Y',      # 6.4.2025
        '%Y-%m-%d',      # 2025-04-06
        '%Y/%m/%d',      # 2025/04/06
    ]
    
    for fmt in formats:
        try:
            dt = datetime.strptime(date_str, fmt)
            return dt.strftime('%Y-%m-%d')
        except ValueError:
            continue
    
    # If no format matches, try to parse with flexible parsing
    try:
        # Split by non-digits
        import re
        parts = re.split(r'[^\d]+', date_str)
        if len(parts) >= 3:
            day, month, year = parts[0], parts[1], parts[2]
            if len(year) == 2:
                year = '20' + year
            return f"{year}-{month.zfill(2)}-{day.zfill(2)}"
    except:
        pass
    
    return date_str

def parse_amount(amount_str):
    """Parse European number format: " 49,90 " or "2.141,01" """
    if not amount_str:
        return 0.0
    
    amount_str = str(amount_str).strip()
    
    # Remove currency symbols and extra spaces
    amount_str = amount_str.replace('€', '').replace('$', '').replace(' ', '')
    
    # Handle negative numbers
    is_negative = amount_str.startswith('-')
    if is_negative:
        amount_str = amount_str[1:]
    
    # European format: thousand separator = dot, decimal = comma
    # Remove thousand separators (dots)
    if '.' in amount_str and ',' in amount_str:
        # Format like "2.141,01"
        amount_str = amount_str.replace('.', '')
        amount_str = amount_str.replace(',', '.')
    elif ',' in amount_str:
        # Format like "49,90"
        amount_str = amount_str.replace(',', '.')
    
    try:
        amount = float(amount_str)
        if is_negative:
            amount = -amount
        return amount  # Keep original sign for credit notes
    except ValueError:
        print(f"  Waarschuwing: Kon bedrag niet parsen: '{amount_str}'")
        return 0.0

def clean_string(value):
    """Clean string for SQL insertion"""
    if value is None:
        return ''
    value = str(value).strip()
    # Remove extra spaces
    value = ' '.join(value.split())
    # Escape single quotes for SQL
    value = value.replace("'", "''")
    return value

def convert_kosten_csv(csv_file, output_file='insert_kosten.sql'):
    """Convert kosten.csv to SQL INSERT statements"""
    
    if not os.path.exists(csv_file):
        print(f"Fout: Bestand '{csv_file}' niet gevonden.")
        return False
    
    print(f"CSV bestand lezen: {csv_file}")
    
    inserts = []
    row_count = 0
    error_count = 0
    
    try:
        # Read the file with proper encoding and handle spaces
        with open(csv_file, 'r', encoding='utf-8') as f:
            # Read first line to check format
            first_line = f.readline().strip()
            f.seek(0)
            
            # Check if there are spaces around commas
            if ' , ' in first_line:
                # Use custom reader to handle spaces
                reader = csv.reader(f, skipinitialspace=True)
                headers = next(reader)
                headers = [h.strip() for h in headers]
            else:
                # Use standard CSV reader
                reader = csv.DictReader(f)
                headers = reader.fieldnames
            
            print(f"Gevonden kolommen: {headers}")
            
            # Map column indices
            col_indices = {}
            for i, header in enumerate(headers):
                header_lower = header.strip().lower()
                if 'factuur' in header_lower:
                    col_indices['invoice'] = i
                elif 'omschrijving' in header_lower:
                    col_indices['description'] = i
                elif 'type' in header_lower:
                    col_indices['type'] = i
                elif 'categorie' in header_lower:
                    col_indices['category'] = i
                elif 'datum' in header_lower:
                    col_indices['date'] = i
                elif 'bedrag' in header_lower:
                    col_indices['amount'] = i
                elif 'btw' in header_lower:
                    col_indices['vat'] = i
            
            print(f"Gekoppelde kolommen: {col_indices}")
            
            # Process each row
            for row_num, row in enumerate(reader, 1):
                try:
                    # Extract values
                    invoice = clean_string(row[col_indices['invoice']]) if 'invoice' in col_indices else ''
                    description = clean_string(row[col_indices['description']]) if 'description' in col_indices else ''
                    trans_type = clean_string(row[col_indices['type']]) if 'type' in col_indices else 'Uitgave'
                    category = clean_string(row[col_indices['category']]) if 'category' in col_indices else ''
                    date_val = parse_date(row[col_indices['date']]) if 'date' in col_indices else None
                    amount_val = parse_amount(row[col_indices['amount']]) if 'amount' in col_indices else 0
                    vat_percent = clean_string(row[col_indices['vat']]) if 'vat' in col_indices else '0%'
                    
                    # Skip if essential data missing
                    if not date_val or not description or amount_val == 0:
                        print(f"  Rij {row_num} overgeslagen: ontbrekende data")
                        error_count += 1
                        continue
                    
                    # Determine transaction type
                    type_clean = trans_type.lower().strip()
                    if type_clean in ['uitgave', 'expense', 'kosten']:
                        transaction_type = 'uitgave'
                    else:
                        transaction_type = 'uitgave'  # Default for kosten.csv
                    
                    # Map category to ID
                    category_map = {
                        'Transportkosten': 2,
                        'Administratiekosten': 2,
                        'Hotelkosten': 2,
                        'Seguridad': 2,
                        'Andere kosten': 2,
                        'Uitgaven': 2,
                        'Inkomsten': 1,
                        'Overig': 3
                    }
                    category_id = category_map.get(category, 2)  # Default to Uitgaven (2)
                    
                    # Parse VAT percentage
                    vat_percentage = 0
                    if vat_percent and '%' in vat_percent:
                        try:
                            vat_percentage = float(vat_percent.replace('%', '').strip())
                        except:
                            vat_percentage = 0
                    
                    # Build INSERT statement
                    if invoice:
                        insert = f"INSERT INTO transactions (date, description, amount, type, category_id, invoice_number, vat_percentage) VALUES ('{date_val}', '{description}', {amount_val}, '{transaction_type}', {category_id}, '{invoice}', {vat_percentage});"
                    else:
                        insert = f"INSERT INTO transactions (date, description, amount, type, category_id, vat_percentage) VALUES ('{date_val}', '{description}', {amount_val}, '{transaction_type}', {category_id}, {vat_percentage});"
                    
                    inserts.append(insert)
                    row_count += 1
                    
                    # Show progress
                    if row_count % 10 == 0:
                        print(f"  Verwerkt: {row_count} rijen...")
                    
                except Exception as e:
                    print(f"  Fout in rij {row_num}: {e}")
                    error_count += 1
                    continue
        
        # Write to output file
        if inserts:
            with open(output_file, 'w', encoding='utf-8') as f:
                f.write(f'-- SQL INSERT statements gegenereerd van {csv_file}\n')
                f.write(f'-- Aangemaakt op: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}\n')
                f.write(f'-- Totaal rijen: {row_count}\n')
                f.write(f'-- Totaal fouten: {error_count}\n\n')
                f.write('\n'.join(inserts))
            
            print(f"\n✅ Succesvol gegenereerd: {output_file}")
            print(f"📊 Totaal INSERT statements: {row_count}")
            print(f"⚠️  Aantal fouten: {error_count}")
            
            # Show sample
            print("\n📝 Voorbeeld INSERT statements:")
            for i in range(min(3, len(inserts))):
                print(f"  {inserts[i]}")
            
            return True
        else:
            print("❌ Geen geldige data gevonden om te converteren.")
            print("   Controleer of het CSV bestand de juiste kolommen bevat.")
            return False
            
    except Exception as e:
        print(f"❌ Fout bij het lezen van CSV: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    """Main function"""
    print("=" * 60)
    print("CSV naar SQL Converter voor kosten.csv")
    print("=" * 60)
    
    csv_file = 'kosten.csv'
    
    if not os.path.exists(csv_file):
        print(f"Bestand '{csv_file}' niet gevonden.")
        print("Zorg dat kosten.csv in dezelfde directory staat.")
        return
    
    # Convert the file
    success = convert_kosten_csv(csv_file)
    
    if success:
        print("\n📋 Gebruiksaanwijzing:")
        print(f"1. Importeer de SQL file in MySQL: mysql -u root boekhouden < insert_kosten.sql")
        print("2. Controleer de data in de webapplicatie: http://localhost:8000")
        print("\n💡 Opmerkingen:")
        print("- Bedragen kunnen positief of negatief zijn (voor creditnota's)")
        print("- BTW percentages zijn opgeslagen in de database")
        print("- Type is altijd 'uitgave' voor dit kosten bestand")
        print("- Categorieën zijn gemapped naar database IDs")
    else:
        print("\n❌ Conversie mislukt.")

if __name__ == '__main__':
    main()