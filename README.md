# 2FA<img src="https://github.com/Muqsit/2FA/blob/master/resources/GoogleAuthenticator.png" width="60" height="60" align="right"></img>
Secure your server with Two-step verification.

## Installing
- Download the compiled `.phar` file from Poggit-CI and upload it to your server's `plugins/` folder.
- Make sure you have PHP's graphics design library (`gd`) installed with libpng.
```bash
./configure
  --with-gd \
  --with-png-dir \
  
//If you are on Linux, you will need to install the C libraries before installing php-gd.
apt-get install libgd-dev
apt-get install libpng-dev

//Windows users can google up the required .dll files. Read http://php.net/manual/en/image.installation.php
```
- Start the server and then stop the server.
- Open plugins/2FA/config.yml and set '2FATitle' to your server's name.

## Usage
- Download a 2FA app (lets assume you chose Google Authenticator)
  - [Google Play](https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=en)
  - [App Store (iOS)](https://itunes.apple.com/us/app/google-authenticator/id388497605?mt=8)
  - [Windows Phone](https://www.microsoft.com/en-us/store/p/microsoft-authenticator/9nblgggzmcj6)
  - [Blackberry](https://appworld.blackberry.com/webstore/content/29401059/?lang=en)
- Execute `/2fa` to obtain your 2FA barcode along with the secret.
- Click the **+** sign (mostly located at the bottom right) in Google Authenticator.
- You have two options. Either manually 'Enter a provided key' or 'Scan a barcode'. If you want to enter a provided key, enter the 'Secret Key'. If you want to scan a barcode, scan the barcode given to you on the map.
- Once done, you'll find a slot on your Google Authenticator App. It will print out a random 6 digit number and refresh it every 30 seconds or so. The random 6 digit number is your 2FA code. Execute _/2fa [6-digit-code]_.
- Congratulations! You have set up 2FA! Now everytime you join the server, you'll have to type the 6-digit 2FA code that keeps on changing from time to time on your 2FA app. You can remove 2FA by executing /2fa remove or by entering the recovery codes that were provided to you on successfully linking your 2FA device.
