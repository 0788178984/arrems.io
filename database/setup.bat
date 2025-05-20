@echo off
echo Setting up ARREMS database...
"C:\xampp\php\php.exe" setup_database.php
if %ERRORLEVEL% EQU 0 (
    echo Database setup completed successfully!
) else (
    echo Error during database setup!
)
pause 