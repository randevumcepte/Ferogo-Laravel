$ErrorActionPreference = "Stop"
$dir = "c:\Users\ferdi\Desktop\randevumcepteuygulamaweb\Ferogo-laravel\sunum"
$sesDir = Join-Path $dir "ses"
New-Item -ItemType Directory -Force -Path $sesDir | Out-Null

$texts = @(
 "Ferixgo reklam ve sponsorluk sunumuna hoş geldiniz. Markanız artık bir ilanda değil, İzmir'in cebinde yaşayabilir.",
 "İki rakamı aklınızda tutun: yirmi iki bin, ve iki ay. Biz size reklam alanı satmıyoruz; yirmi iki bin kişilik büyüyen bir topluluğa her gün girme hakkı veriyoruz.",
 "Ferixgo, İzmir'in paylaşımlı yolculuk platformudur. Uber'in dünyada, Martı Tag'in Türkiye'de kanıtladığı modelin daha güvenli ve daha kaliteli hâli. Korsan taksi değil; doğrulanmış sürücülerle yolcuları buluşturan bir topluluk.",
 "Billboard yolda kalır, gazete çöpe gider, televizyon reklamı atlanır. Ferixgo ise insanın hayatının içine girer: cebine, evine, hatta ailesinin tam ortasına. Reklamınız asılı durmaz; günlük hayata misafir olur.",
 "Sadece iki ayda yirmi iki bin kayıt. Bu hızla altı ay sonra altmış bini, yıl sonunda yüz bini geçiyoruz. Bugün girmek, şirket daha küçükken hissesini almak gibidir.",
 "Bu sıradan bir kalabalık değil. Araç ve yol dünyasının tam içinde; karar veren, harcama gücü olan bir kitle. Otomotiv, sigorta ve inşaat gibi sektörlerin birebir hedefi.",
 "Bu model çoktan kanıtlandı. Uber, yolculuk ekranı reklamlarını yıllık bir milyar doları aşan bir işe çevirdi. Çünkü yolcunun dikkati o ekrana kilitli. Biz aynısını İzmir'e getirdik.",
 "Reklamın en değerli anı, esir dikkat anıdır. Billboard'u iki saniyede geçersiniz, sosyal medyayı kaydırırsınız. Ama araç beklerken gözünüz ekrana kilitlidir. Üstelik bizde reklam hedefli, tıklanabilir ve ölçülebilirdir.",
 "Reklamınız tam olarak şu ekranlarda görünür: ana sayfa alanı, yolculuk takip ekranı ve radar. Her alanda sektörler dönüşümlü çıkar, ama her sektörde tek marka. Rakibiniz bu alana giremez.",
 "Rakamlarla konuşalım. Sürücüler ve müşterilerle birlikte, markanız ayda yaklaşık üç milyon kez birinin cebinde beliriyor. Hangi billboard size bunu, üstelik tıklanabilir şekilde vaat edebilir?",
 "İşte asıl mesele: bu size ne kazandırır. İnşaat firmasıysanız yıl boyu sadece üç daire, otomobil firmasıysanız sadece yedi araç satmanız, tüm reklam bedelini çıkarır. Üstü sizin kârınızdır.",
 "Reklam bir kampanya değil, açık bir musluktur. Açık kaldıkça müşteri akar, kapatınca durur. Kitle her ay büyüdüğü için, kalan sponsor artan erişimi bedavaya alır.",
 "Sigorta, otomotiv, inşaat, akaryakıt, banka ve finans. Hepsi bu kitlenin tam ortasında. Restoran, kafe ve alışveriş merkezleri gibi yerel işletmeler de kendi paketinde yer alır.",
 "Her kategoride tek sponsor olur. Bugün girerseniz, bugünün şartını kilitlersiniz; kitle büyüse bile fiyatınız artmaz. Ama beklerseniz, o yeri rakibiniz kapar.",
 "Peki ismimiz ne anlatıyor? FER, kurucunun imzası Ferdi; arkasında ismi olan, güven veren bir marka. X, yolcu ile sürücünün yolunun kesiştiği buluşma noktası. GO ise harekettir: hadi, yola çık.",
 "İzmir yola çıkıyor ve her sabah büyüyor. Markanız yolcu koltuğunda mı olacak, yoksa dışarıda geçtiğini mi izleyecek? Değeri masada konuşalım. Bizimle iletişime geçin."
)

Add-Type -AssemblyName System.Runtime.WindowsRuntime
$asTask = ([System.WindowsRuntimeSystemExtensions].GetMethods() | Where-Object { $_.Name -eq 'AsTask' -and $_.GetParameters().Count -eq 1 -and $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1' })[0]
function Await($op,$t){ $task=$asTask.MakeGenericMethod($t).Invoke($null,@($op)); $task.Wait(); $task.Result }
[Windows.Media.SpeechSynthesis.SpeechSynthesizer,Windows.Media.SpeechSynthesis,ContentType=WindowsRuntime] | Out-Null
[Windows.Storage.Streams.DataReader,Windows.Storage.Streams,ContentType=WindowsRuntime] | Out-Null
$synth = New-Object Windows.Media.SpeechSynthesis.SpeechSynthesizer
$voice = [Windows.Media.SpeechSynthesis.SpeechSynthesizer]::AllVoices | Where-Object { $_.DisplayName -match 'Tolga' } | Select-Object -First 1
$synth.Voice = $voice

$manifest = @()
for ($i=0; $i -lt $texts.Count; $i++) {
  $n = ($i+1).ToString("00")
  $wav = Join-Path $sesDir ("s$n.wav")
  $stream = Await ($synth.SynthesizeTextToStreamAsync($texts[$i])) ([Windows.Media.SpeechSynthesis.SpeechSynthesisStream])
  $size = [uint32]$stream.Size
  $reader = New-Object Windows.Storage.Streams.DataReader($stream.GetInputStreamAt(0))
  Await ($reader.LoadAsync($size)) ([uint32]) | Out-Null
  $bytes = New-Object byte[] $size
  $reader.ReadBytes($bytes)
  [System.IO.File]::WriteAllBytes($wav,$bytes)
  # WAV suresi: byteRate offset 28 (4 byte LE), data ~ toplam - 44
  $byteRate = [BitConverter]::ToUInt32($bytes,28)
  $dataLen = $bytes.Length - 44
  $dur = [math]::Round($dataLen / $byteRate, 2)
  $manifest += ("$n`t$dur")
}
$manifest | Set-Content -Path (Join-Path $sesDir "durations.txt") -Encoding UTF8
Write-Output "AUDIO_DONE"