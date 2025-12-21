@echo off
REM Script Windows Batch pour tester rapidement un formulaire
REM Usage: test-form-rapide.bat [FORM_UID]

set FORM_UID=%1
if "%FORM_UID%"=="" set FORM_UID=BRYJGSDOHQQU

set BASE_URL=http://localhost:8000
set API_URL=%BASE_URL%/forms/%FORM_UID%/submit

echo ==========================================
echo Test de soumission de formulaire
echo ==========================================
echo UID du formulaire: %FORM_UID%
echo URL de l'API: %API_URL%
echo.

echo Test de soumission...
curl.exe -X POST "%API_URL%" ^
  -H "Content-Type: application/json" ^
  -H "Accept: application/json" ^
  -H "Origin: https://external-landing-page.com" ^
  -d "{\"first_name\":\"Test\",\"phone\":\"0708363767\",\"email\":\"test@example.com\",\"zip_code\":\"50000\",\"constent\":true}"

echo.
echo.
echo Test termine!
pause







