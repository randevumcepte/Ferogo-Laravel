$ErrorActionPreference = "Stop"
$dir = "c:\Users\ferdi\Desktop\randevumcepteuygulamaweb\Ferogo-laravel\sunum"
$ff = Join-Path $env:LOCALAPPDATA "Microsoft\WinGet\Links\ffmpeg.exe"
if (-not (Test-Path $ff)) { $ff = (Get-ChildItem (Join-Path $env:LOCALAPPDATA "Microsoft\WinGet\Packages") -Recurse -Filter ffmpeg.exe | Select-Object -First 1).FullName }
$chrome = "C:\Program Files\Google\Chrome\Application\chrome.exe"
if (-not (Test-Path $chrome)) { $chrome = "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" }
$mp4 = Join-Path $dir "FERXGO-Reklam-Filmi.mp4"
if (Test-Path $mp4) { Remove-Item $mp4 -Force }
$url = "file:///" + (((Join-Path $dir "ferxgo-reklam-filmi.html") -replace '\\','/')) + "?auto=1"
$prof = Join-Path $env:TEMP ("chr_" + (Get-Random))

Get-Process chrome -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $chrome } | Stop-Process -Force -ErrorAction SilentlyContinue

# Chrome tam ekran, otomatik oynat
$args = @("--kiosk","--autoplay-policy=no-user-gesture-required","--disable-infobars","--no-first-run","--no-default-browser-check","--disable-features=Translate","--user-data-dir=$prof",$url)
$cp = Start-Process -FilePath $chrome -ArgumentList $args -PassThru
Start-Sleep -Seconds 3

# ffmpeg ile masaustunu 151 sn kaydet
& $ff -f gdigrab -framerate 30 -i desktop -t 151 -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2" -c:v libx264 -preset veryfast -pix_fmt yuv420p -crf 20 -movflags +faststart -y "$mp4" 2>$null

# Chrome kapat
try { Stop-Process -Id $cp.Id -Force -ErrorAction SilentlyContinue } catch {}
Get-Process chrome -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $chrome } | Stop-Process -Force -ErrorAction SilentlyContinue

if (Test-Path $mp4) { Write-Output ("MP4_DONE " + [math]::Round((Get-Item $mp4).Length/1MB,1) + " MB") } else { Write-Output "MP4_MISSING" }