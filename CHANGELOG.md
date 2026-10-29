# Changelog

## [Unreleased]

### Adăugat
- `SamedayCarrier` peste SDK-ul oficial `sameday-courier/php-sdk`: servicii, cotație, AWB, etichetă A4/A6, tracking, anulare, lockere easybox, `trackMany` prin status-sync.
- Client HTTP peste `Http::` (merge cu `Http::fake()`), token în `Cache`.
- Tabela `sameday_lockers`, comenzile `sameday:sync-lockers`, `sameday:services`, `sameday:pickup-points`.
- Regula de validare `SamedayLocker` și componenta `<x-sameday::locker-picker>`.
