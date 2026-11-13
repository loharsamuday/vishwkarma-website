# Telegram Bot API Reverse Proxy

## Why is this needed? (यह क्यों जरूरी है?)
InfinityFree (free tier hosting like `*.great-site.net`) intentionally blocks outbound requests and DNS resolution to `api.telegram.org` to prevent spam bots.
As a result, PHP `curl` returns:
```
cURL Error: Could not resolve host: api.telegram.org
```

---

## 2 Solutions (समाधान के 2 रास्ते)

### Option 1: Deploy Free Vercel Proxy (No credit card needed)
1. Go to [https://vercel.com](https://vercel.com) and log in with GitHub.
2. Create a new GitHub repository (or upload the folder `tools/telegram_proxy/vercel`).
3. Import that repository in Vercel and click **Deploy**.
4. You will get a free HTTPS domain, for example: `https://my-tg-proxy.vercel.app`.
5. Open `config/config.php` on your InfinityFree server and update:
   ```php
   define('TG_API_BASE_URL', 'https://my-tg-proxy.vercel.app');
   ```
6. Now InfinityFree will route all Telegram requests through your Vercel proxy, and Telegram Bot will connect successfully!

---

### Option 2: Upload `tg_proxy.php` to any other PHP Hosting or Subdomain
If you or a friend have any other paid/standard PHP host or VPS (e.g. Hostinger, cPanel, etc.):
1. Upload `tools/telegram_proxy/php/tg_proxy.php` to that server.
2. Set `TG_API_BASE_URL` in `config/config.php`:
   ```php
   define('TG_API_BASE_URL', 'https://your-other-domain.com/tg_proxy.php');
   ```

---

### Option 3 (Permanent Best Practice): Migrate to Standard Hosting
If you use Hostinger (starts around ₹69/month), Namecheap, or Oracle Cloud Always Free VPS:
- Telegram API connects directly without proxy.
- Incoming webhooks work in real time.
- Cron jobs and background daemons run continuously.
