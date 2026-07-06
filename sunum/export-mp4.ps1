$ErrorActionPreference = "Stop"
Get-Process POWERPNT -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

$dir = "c:\Users\ferdi\Desktop\randevumcepteuygulamaweb\Ferogo-laravel\sunum"
$pptx = Join-Path $dir "FERXGO-Sunum-Sesli.pptx"
$mp4  = Join-Path $dir "FERXGO-Sunum-Video.mp4"
if (Test-Path $mp4) { Remove-Item $mp4 -Force }

$ppt = New-Object -ComObject PowerPoint.Application
$ppt.Visible = -1
$ppt.DisplayAlerts = 1
Start-Sleep -Seconds 2
$pres = $ppt.Presentations.Open($pptx, 0, 0, -1)
Start-Sleep -Seconds 3

# CreateVideo cagrisini RPC_E_CALL_REJECTED'e karsi tekrar dene
$ok = $false
for ($try=1; $try -le 30; $try++) {
  try {
    $pres.CreateVideo($mp4, $true, 5, 1080, 30, 90)
    $ok = $true; break
  } catch {
    Start-Sleep -Seconds 2
  }
}
if (-not $ok) { Write-Output "CREATEVIDEO_REJECTED"; $pres.Close(); $ppt.Quit(); exit 1 }

$maxWait = 540; $elapsed = 0
while ($true) {
  Start-Sleep -Seconds 3; $elapsed += 3
  $st = 0
  try { $st = $pres.CreateVideoStatus } catch { $st = -1 }
  if ($st -eq 3) { break }
  if ($st -eq 4) { Write-Output "VIDEO_FAILED"; break }
  if ($elapsed -ge $maxWait) { Write-Output "VIDEO_TIMEOUT"; break }
}
Start-Sleep -Seconds 2
try { $pres.Close() } catch {}
try { $ppt.Quit() } catch {}
if (Test-Path $mp4) { Write-Output ("VIDEO_DONE " + [math]::Round((Get-Item $mp4).Length/1MB,1) + " MB") } else { Write-Output "VIDEO_MISSING" }