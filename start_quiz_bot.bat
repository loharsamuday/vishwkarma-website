@echo off
title Vishwakarma Telegram Quiz Bot Daemon
color 0A
echo ============================================================
echo   VISHWAKARMA TELEGRAM QUIZ BOT - POLLING SERVICE
echo ============================================================
echo.
echo Starting bot service via PHP CLI...
echo Bot is listening for Telegram messages, deep links, and button clicks.
echo Keep this window open while playing quizzes.
echo.
echo Press Ctrl+C anytime to stop.
echo ============================================================
echo.
C:\xampp\php\php.exe bin\tg_quiz_daemon.php
pause
