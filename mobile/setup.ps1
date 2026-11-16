# Membuat folder android/ untuk kedua aplikasi Flutter, lalu menambahkan izin internet.
# Jalankan SEKALI dari folder mobile/:   powershell -ExecutionPolicy Bypass -File setup.ps1

$ErrorActionPreference = 'Stop'

if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
    Write-Host 'Flutter belum terpasang (atau belum ada di PATH). Lihat mobile/README.md langkah 1.' -ForegroundColor Red
    exit 1
}

$apps = @(
    @{ Folder = 'webview_app'; Name = 'study_analyzer_web' },
    @{ Folder = 'native_app';  Name = 'study_analyzer' }
)

foreach ($app in $apps) {
    $dir = Join-Path $PSScriptRoot $app.Folder
    Write-Host "`n=== $($app.Folder) ===" -ForegroundColor Cyan
    Push-Location $dir
    try {
        # File yang sudah ada (lib/, pubspec.yaml) TIDAK ditimpa; hanya file yang belum ada yang dibuat.
        flutter create --platforms=android --org com.studyanalyzer --project-name $app.Name .
        if ($LASTEXITCODE -ne 0) { throw 'flutter create gagal.' }

        # Test bawaan template merujuk ke "MyApp" yang tidak ada di project ini.
        $defaultTest = Join-Path $dir 'test\widget_test.dart'
        if (Test-Path $defaultTest) { Remove-Item $defaultTest -Confirm:$false }

        # AndroidManifest: izin internet + izinkan http:// (untuk tes di XAMPP tanpa HTTPS) + nama aplikasi.
        $manifest = Join-Path $dir 'android\app\src\main\AndroidManifest.xml'
        $xml = [IO.File]::ReadAllText($manifest)
        if ($xml -notmatch 'android.permission.INTERNET') {
            $xml = $xml -replace '(<manifest[^>]*>)', "`$1`n    <uses-permission android:name=`"android.permission.INTERNET`"/>"
        }
        if ($xml -notmatch 'usesCleartextTraffic') {
            $xml = $xml -replace '<application', '<application android:usesCleartextTraffic="true"'
        }
        $xml = $xml -replace 'android:label="[^"]*"', 'android:label="Study Analyzer"'
        [IO.File]::WriteAllText($manifest, $xml)

        flutter pub get
        if ($LASTEXITCODE -ne 0) { throw 'flutter pub get gagal.' }
    } finally {
        Pop-Location
    }
}

Write-Host "`nSelesai! Atur alamat server di webview_app/lib/main.dart dan native_app/lib/config.dart, lalu jalankan 'flutter run'." -ForegroundColor Green
