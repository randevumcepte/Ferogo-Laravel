$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Drawing
$dir = "c:\Users\ferdi\Desktop\randevumcepteuygulamaweb\Ferogo-laravel\sunum"
$slideDir = Join-Path $dir "slaytlar"
New-Item -ItemType Directory -Force -Path $slideDir | Out-Null
Get-ChildItem $slideDir -Filter *.png | Remove-Item -Force
$src = [System.Drawing.Image]::FromFile((Join-Path $dir "full.png"))
$w = 1123; $h = 794
for ($i=0; $i -lt 16; $i++) {
  $n = ($i+1).ToString("00")
  $rect = New-Object System.Drawing.Rectangle(0, ($i*$h), $w, $h)
  $crop = New-Object System.Drawing.Bitmap($w, $h)
  $g = [System.Drawing.Graphics]::FromImage($crop)
  $g.DrawImage($src, (New-Object System.Drawing.Rectangle(0,0,$w,$h)), $rect, [System.Drawing.GraphicsUnit]::Pixel)
  $crop.Save((Join-Path $slideDir "slide$n.png"), [System.Drawing.Imaging.ImageFormat]::Png)
  $g.Dispose(); $crop.Dispose()
}
$src.Dispose()
Write-Output "CROP_DONE"