$ErrorActionPreference = "Stop"
$dir = "c:\Users\ferdi\Desktop\randevumcepteuygulamaweb\Ferogo-laravel\sunum"
$out = Join-Path $dir "sahneler"
New-Item -ItemType Directory -Force -Path $out | Out-Null
Get-ChildItem $out -Filter *.png -ErrorAction SilentlyContinue | Remove-Item -Force
$edge = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
if (-not (Test-Path $edge)) { $edge = "C:\Program Files\Microsoft\Edge\Application\msedge.exe" }
$base = "file:///" + (((Join-Path $dir "ferxgo-reklam-filmi.html") -replace '\\','/'))
for ($i=1; $i -le 14; $i++) {
  $n = $i.ToString("00")
  $png = Join-Path $out "sahne$n.png"
  $prof = Join-Path $env:TEMP ("es_" + $i + "_" + (Get-Random))
  $url = "$base`?still=$i"
  Start-Process -FilePath $edge -Wait -ArgumentList @("--headless=new","--disable-gpu","--user-data-dir=$prof","--window-size=1280,720","--screenshot=$png",$url)
  Start-Sleep -Milliseconds 400
}
$cnt = (Get-ChildItem $out -Filter *.png | Measure-Object).Count
Write-Output ("SCENES_DONE " + $cnt)