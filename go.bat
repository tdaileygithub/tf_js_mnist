SET PATH=%~dp0\php-7.3.0-Win32-VC15-x64;%PATH%
taskkill /f /im nginx.exe
taskkill /f /im php-cgi.exe
start /MIN "php" %~dp0\php-7.3.0-Win32-VC15-x64\php-cgi.exe -b 127.0.0.1:9123

title NGINX
E:
cd %~dp0\nginx-1.14.2
nginx