# WP-AI-Guard 🛡️

**WP-AI-Guard** is an experimental WordPress security plugin that leverages Artificial Intelligence (Google Gemini) to detect and block malicious traffic in real-time.

## Features

-   **Real-time Monitoring**: Inspects all incoming requests (GET/POST) for suspicious patterns like SQL Injection and XSS.
-   **AI-Powered Analysis**: Integrates with Google Gemini API to perform deep analysis of suspicious logs.
-   **Automated Blocking**: Automatically blocks IPs with a high threat score (> 7) determined by the AI.
-   **Security Dashboard**: A dedicated admin page to view logs, threat levels, and AI explanations.

## Installation

1.  **Download/Clone**: Upload the `wp-ai-guard` folder to your `/wp-content/plugins/` directory.
2.  **Activate**: Navigate to the 'Plugins' menu in your WordPress admin dashboard and click 'Activate' for WP-AI-Guard.
3.  **Configure API Key**:
    For the AI analysis to work, you must provide a Gemini API Key from [Google AI Studio](https://aistudio.google.com/).
    -   **Option A (Recommended)**: Add the following line to your `wp-config.php` file:
        ```php
        define( 'WP_AI_GUARD_API_KEY', 'your_api_key_here' );
        ```
    -   **Option B**: If the constant is not defined, the plugin will look for an option in the database.

## How it Works

1.  **Pattern Detection**: The plugin monitors every request. If it detects characters like `<`, `>`, `'` or SQL keywords (`SELECT`, `UNION`, etc.), it logs the request as "suspicious".
2.  **AI Verification**: From the admin panel, you can trigger an AI Analysis. The plugin sends the suspicious log to Google Gemini with a specialized prompt.
3.  **Threat Scoring**: Gemini returns a JSON response with a `threat_level` (0-10), the type of attack, and a technical explanation.
4.  **Enforcement**: On every subsequent page load, the plugin checks the database. If an IP has a record with a `threat_level` greater than 7, the user is immediately blocked with a 403 Forbidden message.

## Database Schema

The plugin creates a custom table `{prefix}wpguard_logs` with the following fields:
- `id`: Primary key.
- `ip`: Visitor's IP address.
- `request_data`: JSON encoded URL and POST data.
- `threat_score`: Numeric score (0-10).
- `ai_analysis`: Type of attack and explanation from Gemini.
- `created_at`: Timestamp.

## Security Warning

*This plugin is intended for educational and experimental purposes. Always use established security solutions like Wordfence or Cloudflare for production environments.*

## License

GPLv2 or later.
