@echo off
:: Telegram Quiz Worker — Windows Startup Script
:: python/start_worker.bat

echo ======================================
echo  Telegram Quiz Worker - Starting...
echo ======================================

:: Change to the python directory
cd /d "%~dp0"

:: Check if .env exists
if not exist ".env" (
    echo ERROR: .env file not found!
    echo Please copy .env.example to .env and fill in your values.
    pause
    exit /b 1
)

:: Check Python
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python not found. Install Python 3.10+ and add to PATH.
    pause
    exit /b 1
)

:: Check dependencies
python -c "import pymysql, requests, dotenv, cryptography" >nul 2>&1
if errorlevel 1 (
    echo Installing dependencies...
    pip install -r requirements.txt
)

echo.
echo Starting worker... Press Ctrl+C to stop.
echo Logs are saved to: worker.log
echo.

:loop
python tg_quiz_worker.py
echo Worker stopped. Restarting in 10 seconds... (Press Ctrl+C to cancel)
timeout /t 10
goto loop
