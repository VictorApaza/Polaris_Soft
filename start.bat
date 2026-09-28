@echo off
title Polaris Soft

REM Carpeta donde está este .bat
set "ROOT=%~dp0"

echo ========================================
echo        INICIANDO POLARIS SOFT
echo ========================================
echo.

echo Iniciando Laravel...
start "Laravel - Polaris Soft" cmd /k "cd /d "%ROOT%Polaris-backend" && php artisan serve"

timeout /t 2 /nobreak >nul

echo Iniciando React...
start "React - Polaris Frontend" cmd /k "cd /d "%ROOT%polaris-frontend" && npm run dev"

echo.
echo ========================================
echo Laravel: http://127.0.0.1:8000
echo React:   http://localhost:5173
echo ========================================

exit