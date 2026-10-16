@echo off
setlocal
set "REVISTA_PHP=C:\xampp\php\php.exe"
if not exist "%REVISTA_PHP%" (
  echo PHP nao encontrado em C:\xampp\php\php.exe. Ajuste REVISTA_PHP neste arquivo.
  pause
  exit /b 1
)
schtasks /Create /TN "RevistaPG-RSS" /SC MINUTE /MO 5 /TR "\"%REVISTA_PHP%\" \"%~dp0rss-importar.php\"" /F
if errorlevel 1 (
  echo Nao foi possivel criar a tarefa. Confira as permissoes do Agendador de Tarefas.
) else (
  echo Atualizacao RSS agendada a cada 12 horas enquanto este computador estiver ligado.
)
pause
