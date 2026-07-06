$ErrorActionPreference = "Stop"
$dir = "c:\Users\ferdi\Desktop\randevumcepteuygulamaweb\Ferogo-laravel\sunum"
$slideDir = Join-Path $dir "slaytlar"
$sesDir = Join-Path $dir "ses"
$pptxPath = Join-Path $dir "FERXGO-Sunum-Sesli.pptx"

# sureleri oku
$dur = @{}
Get-Content (Join-Path $sesDir "durations.txt") | ForEach-Object {
  $line = $_ -replace "^﻿",""
  if ($line -match "^\s*(\d\d)\s+([\d\.]+)") { $dur[$matches[1]] = [double]$matches[2] }
}

$ppt = New-Object -ComObject PowerPoint.Application
$ppt.Visible = -1  # msoTrue (PowerPoint COM gerektirir)
$ppt.DisplayAlerts = 1  # ppAlertsNone
$pres = $ppt.Presentations.Add(-1)  # WithWindow=msoTrue

# A4 yatay slayt boyutu (nokta cinsinden)
$pres.PageSetup.SlideWidth  = 841.89
$pres.PageSetup.SlideHeight = 595.28
$W = $pres.PageSetup.SlideWidth
$H = $pres.PageSetup.SlideHeight

for ($i=1; $i -le 16; $i++) {
  $n = $i.ToString("00")
  $png = Join-Path $slideDir "slide$n.png"
  $wav = Join-Path $sesDir  "s$n.wav"
  $slide = $pres.Slides.Add($i, 12)  # ppLayoutBlank

  # tam ekran gorsel
  $pic = $slide.Shapes.AddPicture($png, 0, -1, 0, 0, $W, $H)

  # ses (gomulu, ekran disina konumla ki hoparlor ikonu gorunmesin)
  $audio = $slide.Shapes.AddMediaObject2($wav, 0, -1, -80, -80, 40, 40)

  # otomatik oynat (slayt acilinca)
  $seq = $slide.TimeLine.MainSequence
  $eff = $seq.AddEffect($audio, 83, 0, 2)  # 83=MediaPlay, trigger 2=WithPrevious

  # otomatik ilerle: ses suresi + tampon
  $adv = 3.0
  if ($dur.ContainsKey($n)) { $adv = $dur[$n] + 0.9 }
  $t = $slide.SlideShowTransition
  $t.AdvanceOnTime = -1
  $t.AdvanceTime = $adv
  $t.AdvanceOnClick = -1   # tiklamayla da ilerlenebilsin
  $t.EntryEffect = 3844    # fade (ppEffectFadeSmoothly)
}

# gosteri zamanlamalari kullansin
$pres.SlideShowSettings.AdvanceMode = 2  # ppSlideShowUseSlideTimings

$pres.SaveAs($pptxPath, 24)  # ppSaveAsOpenXMLPresentation
$pres.Close()
$ppt.Quit()
Write-Output ("PPTX_DONE " + $pptxPath)