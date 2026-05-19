# Tests

## Running tests

```powershell
cd c:\Users\nsanni\Downloads\KidsStore\KidsStore\kidsstore
php artisan test
```

## Running a specific test class

```powershell
cd c:\Users\nsanni\Downloads\KidsStore\KidsStore\kidsstore
php artisan test --filter=AccountProfileTest
```

## Listing discovered tests

```powershell
cd c:\Users\nsanni\Downloads\KidsStore\KidsStore\kidsstore
vendor\bin\phpunit --list-tests
php artisan test --list-tests
```

## Notes

- The test suite uses SQLite in-memory DB via phpunit.xml.
- If tests are not discovered, ensure Composer autoload is up to date:

```powershell
cd c:\Users\nsanni\Downloads\KidsStore\KidsStore\kidsstore
composer dump-autoload
```

- If tests still show 0 discovered, run:

```powershell
vendor\bin\phpunit -c phpunit.xml --list-tests
```
