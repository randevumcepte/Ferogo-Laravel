$ErrorActionPreference = "Stop"
Get-Process POWERPNT -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 3
$dir = "c:\Users\ferdi\Desktop\randevumcepteuygulamaweb\Ferogo-laravel\sunum"
$slideDir = Join-Path $dir "slaytlar"
$pptxPath = Join-Path $dir "FERXGO-Sunum.pptx"
if (Test-Path $pptxPath) { Remove-Item $pptxPath -Force }

function Retry([scriptblock]$b){ for($k=1;$k -le 20;$k++){ try { return & $b } catch { Start-Sleep -Milliseconds 800 } }; throw "retry failed" }

$ppt = New-Object -ComObject PowerPoint.Application
$ppt.Visible = -1
$ppt.DisplayAlerts = 1
Start-Sleep -Seconds 2
$pres = Retry { $ppt.Presentations.Add(-1) }
Start-Sleep -Seconds 2
Retry { $pres.PageSetup.SlideWidth  = 841.89 }
Retry { $pres.PageSetup.SlideHeight = 595.28 }
$W = $pres.PageSetup.SlideWidth
$H = $pres.PageSetup.SlideHeight

for ($i=1; $i -le 16; $i++) {
  $n = $i.ToString("00")
  $png = Join-Path $slideDir "slide$n.png"
  $slide = Retry { $pres.Slides.Add($i, 12) }
  Retry { [void]$slide.Shapes.AddPicture($png, 0, -1, 0, 0, $W, $H) }
}

Retry { $pres.SaveAs($pptxPath, 24) }
Start-Sleep -Seconds 1
try { $pres.Close() } catch {}
try { $ppt.Quit() } catch {}
Write-Output ("PPTX_DONE " + $pptxPath)