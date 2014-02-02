@echo off
set http_port=6767
set php_process=php-hl.exe
set temp_outfile1=%temp%\search.log
set temp_outfile2=%temp%\found.log
start http://localhost:%http_port%/index.html
tasklist /FI "IMAGENAME eq %php_process%" /FO CSV > %temp_outfile1%
findstr %php_process% %temp_outfile1% > %temp_outfile2%
del %temp_outfile1%
FOR /F %%A IN (%temp_outfile2%) DO IF %%~zA EQU 0 GOTO end
"%~dp0%php_process%" -S localhost:%http_port% -t "%~dp0..\src\"
:end
del %temp_outfile2%
REM start http://localhost/hors/
REM wscript C:\Users\LAP-PRO\Documents\GitHub\HorsLigne\utils\invisible.vbs C:\Users\LAP-PRO\Documents\GitHub\HorsLigne\so.bat