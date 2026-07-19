New-Item -ItemType Directory -Force -Path "c:\xampp\htdocs\vishwkarma\vendor"
Invoke-WebRequest -Uri "https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip" -OutFile "c:\xampp\htdocs\vishwkarma\vendor\phpmailer.zip"
Expand-Archive -Path "c:\xampp\htdocs\vishwkarma\vendor\phpmailer.zip" -DestinationPath "c:\xampp\htdocs\vishwkarma\vendor" -Force
Rename-Item -Path "c:\xampp\htdocs\vishwkarma\vendor\PHPMailer-6.9.1" -NewName "PHPMailer"
Remove-Item -Path "c:\xampp\htdocs\vishwkarma\vendor\phpmailer.zip"
