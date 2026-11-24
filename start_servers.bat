@echo off
title College Maintenance Server Launcher
color 0A
echo ========================================================
echo   Starting College Maintenance Management System
echo ========================================================

echo [1/2] Checking MySQL database...
tasklist /fi "imagename eq mysqld.exe" | findstr /i "mysqld.exe" >nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] MySQL is already running.
) else (
    echo [*] Starting MySQL...
    start "" /b "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 2 /nobreak >nul
)

echo [2/2] Starting Node.js Express server on port 8000...
cd /d "%~dp0college_maintenance_backend"
echo.
echo ========================================================
echo Server URL: http://localhost:8000/admin
echo Admin Login: admin@college.edu / admin123
echo Technician Login: AGT-001 / agent123
echo ========================================================
echo.
node server.js
pause
