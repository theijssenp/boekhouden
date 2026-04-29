# Progress Log

## Code review fixes - 2026-04-28

### Fase: REVIEW ✅
- Volledige multi-axis code review uitgevoerd via code-reviewer subagent
- 4 kritieke (C1-C4), 5 belangrijke (I1-I5), 4 suggesties (N1-N4) geïdentificeerd

### Fase: BUILD 🔄 (bezig met fixes)

**Fix volgorde en status:**
1. ✅ C1 - Open redirect in login.php gefixt (whitelist van toegestane redirects)
2. ✅ C3 - generate_password_hashes.php + update_password_hashes.php verwijderd
3. ✅ C4 - Schema migratie (relations tabel + relation_id kolom) uitgevoerd
4. ✅ I5 - Session cookie SameSite/Strict + httponly flags toegevoegd in auth_functions.php
5. ✅ C2 - CSRF functies in auth_functions.php (generate_csrf_token, validate_csrf_token, require_csrf_token, csrf_field)
5a. ✅ add_income.php - CSRF field + check geïntegreerd
5b. ✅ add_expense.php - CSRF field + check geïntegreerd
5c. ✅ add_relation.php - CSRF field + check geïntegreerd
5d. ✅ edit.php - CSRF field + check geïntegreerd
5e. ✅ edit_relation.php - CSRF field + check geïntegreerd
5f. ✅ delete.php - POST-only DELETE met CSRF token
5g. ✅ admin_users.php - CSRF field + check geïntegreerd
5h. ✅ admin_categories.php - CSRF field + check geïntegreerd
6. ✅ I1 - DELETE POST-only gemaakt met CSRF token validatie
7. 🔲 I2 - Server-side input validatie toevoegen (amount, date, description)
8. 🔲 I3 - Demo credentials veiliger maken
9. 🔲 I4 - FPDF vervangen door TCPDF/Dompdf voor UTF-8 ondersteuning
10. 🔲 N1-N4 - Code kwaliteit (VAT functie hergebruik, CSS/JS verplaatsen, etc.)
