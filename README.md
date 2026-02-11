# Eldorado Konferansehub / Eldorado Conference Hub

A comprehensive interactive conference solution system built with PHP 8.x, MySQL, and Tailwind CSS. Features include real-time polling, word clouds, Q&A moderation, OBS overlay display, multi-language support, and instant updates via Server-Sent Events.

## Features

### Admin Panel
- 🔐 User registration & authentication (bcrypt password hashing)
- 🌍 Multi-language support (Norwegian and English)
- 📅 Conference management with unique access codes
- 🎨 Overlay background options (Graphic or Transparent/Chroma key)
- ❓ Question creation (Single choice, Multiple choice, Rating, Word cloud)
- 🎛️ Live question control (activate/deactivate, show/hide results)
- 💬 Guest question moderation (approve/reject/display)
- 📊 Real-time results with Chart.js
- 📱 QR code generation for quick access

### Guest Interface (Mobile-First)
- 📱 Dark mode mobile interface
- 🌍 Multi-language support
- 🔑 Conference code entry
- 📝 Configurable registration fields
- 🗳️ Interactive voting
- 💬 Submit questions to moderators

### Overlay Display (OBS Compatible)
- 📺 Optimized for 3840x1152 resolution
- 🎨 Choose between graphic or transparent background
- 📊 Live charts and word clouds
- 📱 QR code for quick access
- 💬 Display approved guest questions
- ⚡ Instant updates via Server-Sent Events (SSE)

## Requirements

- PHP 8.x or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- Composer (for dependencies)

## Installation

### 1. Clone the Repository

```bash
cd /var/www/html
git clone https://github.com/yourusername/eldorado-konferansehub.git
cd eldorado-konferansehub
```

### 2. Create Database

```bash
mysql -u root -p < sql/setup.sql
```

Or manually create the database and import the schema.

### 3. Configure Database

Edit `config/database.php` with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'conference_interactive');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 4. Set Permissions

```bash
chmod 755 assets/qr
chmod 644 assets/qr/.htaccess
```

### 5. Configure Web Server

#### Apache

Ensure `.htaccess` files are enabled and the `mod_rewrite` module is loaded.

#### Nginx

Add the following to your server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## Usage

### 1. Create Admin Account

Navigate to `admin/register.php` and create your first admin account.

### 2. Create a Conference

Log in to the admin panel and create a new conference:
- Enter conference name and description
- Configure required registration fields (email, job title)
- Choose overlay background (graphic or transparent)
- A unique code will be auto-generated

### 3. Add Questions

Go to the Questions section and create questions:
- **Single Choice**: Participants select one option
- **Multiple Choice**: Participants can select multiple options
- **Rating**: 1-10 scale
- **Word Cloud**: Free text responses

### 4. Share with Participants

Share the guest URL with participants:
```
https://yoursite.com/guest/?code=YOUR_CODE
```

Or use the QR code available in the admin panel.

### 5. Control the Session

Use the Control Panel to:
- Activate questions for participants to see
- Show/hide results on the main display
- Moderate incoming guest questions

### 6. Display on Big Screen

Open the overlay URL in OBS or on a projector:
```
https://yoursite.com/overlay/display.php?code=YOUR_CODE
```

Choose between:
- **Graphic background**: Dark gradient background
- **Transparent background**: For chroma keying in OBS

## Language Support

The system supports two languages:
- **Norwegian (Norsk)** - Default
- **English**

Users can switch languages using the language selector in the interface. The language preference is stored in the session.

## Project Structure

```
conference-interactive/
├── admin/              # Admin panel files
│   ├── conferences/    # Conference management
│   ├── guest-questions/# Question moderation
│   └── questions/      # Question management
├── api/                # API endpoints
│   ├── events.php      # Server-Sent Events for instant updates
│   └── stats.php       # Statistics endpoint
├── assets/             # CSS, JS, QR codes
├── config/             # Configuration files
├── guest/              # Guest interface
├── includes/           # Shared PHP includes
│   ├── lang/           # Language files (no.php, en.php)
│   ├── auth.php        # Authentication functions
│   ├── footer.php      # Footer template
│   ├── functions.php   # Common functions
│   └── header.php      # Header template
├── overlay/            # OBS overlay display
├── sql/                # Database schema
└── README.md           # This file
```

## Server-Sent Events (SSE)

The system uses Server-Sent Events for instant updates to the overlay display:
- Participant count updates
- Response count updates
- Question activation/deactivation
- Results show/hide

This provides a much more responsive experience compared to traditional polling.

## Security Considerations

- All SQL queries use prepared statements (PDO)
- Passwords hashed with bcrypt
- CSRF tokens on all forms
- XSS protection with htmlspecialchars
- Session regeneration on login

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## License

MIT License - feel free to use this project for personal or commercial purposes.

## Contributing

Contributions are welcome! Please submit a pull request or open an issue.

## Support

For issues or questions, please open a GitHub issue or contact support.

---

**Eldorado Konferansehub** - Making conferences interactive! 🎉
